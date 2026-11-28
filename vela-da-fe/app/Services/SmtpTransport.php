<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Cliente SMTP mínimo e sem dependências: STARTTLS ou SSL/TLS implícito,
 * certificado do servidor SEMPRE verificado, AUTH LOGIN/PLAIN.
 * Funciona com Gmail (senha de app), Outlook, Zoho, Hostinger, Locaweb, SES etc.
 */
final class SmtpTransport
{
    /** @var resource|null */
    private $socket = null;
    private array $capabilities = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption, // tls | ssl | none
        private readonly string $user,
        private readonly string $pass,
        private readonly int $timeout = 15,
    ) {
    }

    public function send(string $from, string $fromName, string $to, string $subject, string $text, string $html = ''): void
    {
        try {
            $this->connect();
            $this->command('MAIL FROM:<' . $from . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);
            $this->write($this->buildMessage($from, $fromName, $to, $subject, $text, $html) . "\r\n.");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            $this->close();
        }
    }

    private function connect(): void
    {
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $this->host,
            'SNI_enabled' => true,
        ]]);
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException("SMTP: não foi possível conectar em {$this->host}:{$this->port} ({$errstr})");
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
        $this->expect([220]);
        $this->ehlo();

        if ($this->encryption === 'tls') {
            if (!isset($this->capabilities['STARTTLS'])) {
                throw new RuntimeException('SMTP: o servidor não oferece STARTTLS.');
            }
            $this->command('STARTTLS', [220]);
            $ok = @stream_socket_enable_crypto(
                $this->socket,
                true,
                STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0)
            );
            if ($ok !== true) {
                throw new RuntimeException('SMTP: falha ao iniciar TLS (certificado inválido?).');
            }
            $this->ehlo();
        }

        if ($this->user !== '') {
            if ($this->encryption === 'none' && !in_array($this->host, ['127.0.0.1', 'localhost'], true)) {
                throw new RuntimeException('SMTP: recusado enviar senha sem criptografia. Use SMTP_ENCRYPTION=tls ou ssl.');
            }
            $auth = strtoupper($this->capabilities['AUTH'] ?? '');
            if (str_contains($auth, 'PLAIN')) {
                $this->command('AUTH PLAIN ' . base64_encode("\0" . $this->user . "\0" . $this->pass), [235], true);
            } else {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->user), [334], true);
                $this->command(base64_encode($this->pass), [235], true);
            }
        }
    }

    private function ehlo(): void
    {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '', (string) (gethostname() ?: 'localhost')) ?: 'localhost';
        $lines = $this->command('EHLO ' . $host, [250]);
        $this->capabilities = [];
        foreach (array_slice($lines, 1) as $line) {
            $parts = explode(' ', strtoupper(trim(substr($line, 4))), 2);
            $this->capabilities[$parts[0]] = $parts[1] ?? '';
        }
    }

    /** @return string[] linhas da resposta */
    private function command(string $cmd, array $codes, bool $secret = false): array
    {
        $this->write($cmd);
        try {
            return $this->expect($codes);
        } catch (RuntimeException $e) {
            if ($secret) {
                throw new RuntimeException('SMTP: autenticação recusada (usuário/senha incorretos?).');
            }
            throw $e;
        }
    }

    private function write(string $data): void
    {
        if (@fwrite($this->socket, $data . "\r\n") === false) {
            throw new RuntimeException('SMTP: conexão perdida.');
        }
    }

    /** @return string[] */
    private function expect(array $codes): array
    {
        $lines = [];
        while (true) {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP: sem resposta do servidor (timeout).');
            }
            $lines[] = rtrim($line, "\r\n");
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($lines[0], 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP: resposta inesperada: ' . mb_substr(end($lines), 0, 200));
        }
        return $lines;
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }

    private function buildMessage(string $from, string $fromName, string $to, string $subject, string $text, string $html): string
    {
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Auto-Submitted: auto-generated',
        ];
        if ($html === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($text), 76, "\r\n");
        } else {
            $boundary = 'b' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($text), 76, "\r\n")
                . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($html), 76, "\r\n")
                . "--{$boundary}--";
        }
        // base64 nunca começa linha com "." — não há risco de terminar o DATA antes da hora
        return implode("\r\n", $headers) . "\r\n\r\n" . rtrim($body, "\r\n");
    }

    public static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }
}
