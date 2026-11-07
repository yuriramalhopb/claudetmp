<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Envio de e-mail com três drivers:
 *  - log  : grava em storage/logs/mail-*.log (desenvolvimento)
 *  - smtp : SmtpTransport (recomendado em produção)
 *  - mail : função mail() do PHP
 * Em produção os envios passam pela fila (MailQueue) processada por bin/cron.php.
 */
final class Mailer
{
    public function __construct(
        private readonly string $driver,
        private readonly string $from,
        private readonly string $fromName,
        private readonly Logger $logger,
        private readonly ?SmtpTransport $smtp = null,
    ) {
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /** Lança exceção em caso de falha (a fila registra e tenta de novo). */
    public function send(string $to, string $subject, string $text): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject . $to)) {
            throw new \InvalidArgumentException('Destinatário ou assunto inválido.');
        }
        switch ($this->driver) {
            case 'smtp':
                if ($this->smtp === null) {
                    throw new \RuntimeException('SMTP não configurado (SMTP_HOST).');
                }
                $this->smtp->send($this->from, $this->fromName, $to, $subject, $text, self::toHtml($subject, $text));
                break;
            case 'mail':
                $headers = [
                    'From' => SmtpTransport::encodeHeader($this->fromName) . ' <' . $this->from . '>',
                    'MIME-Version' => '1.0',
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Content-Transfer-Encoding' => 'base64',
                ];
                if (!@mail($to, SmtpTransport::encodeHeader($subject), chunk_split(base64_encode($text)), $headers)) {
                    throw new \RuntimeException('mail() falhou.');
                }
                break;
            default:
                $this->logger->write('MAIL', "Para: {$to} | Assunto: {$subject}", ['body' => $text], 'mail');
        }
    }

    /** Versão HTML simples do e-mail (texto escapado, links clicáveis). */
    private static function toHtml(string $subject, string $text): string
    {
        $body = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), false);
        $body = (string) preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $body);
        return '<!doctype html><html><body style="margin:0;background:#f4f1ee;font-family:Arial,sans-serif">'
            . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden">'
            . '<div style="background:#14110f;color:#ff7a1a;padding:16px 24px;font-size:20px;font-weight:bold">🦆 Ducks Offroad</div>'
            . '<div style="padding:24px;color:#222;font-size:15px;line-height:1.6"><h2 style="margin-top:0">'
            . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>' . $body . '</div></div></body></html>';
    }
}
