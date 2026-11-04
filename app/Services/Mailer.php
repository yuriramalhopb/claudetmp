<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Envio de e-mail. Com MAIL_ENABLED=false, grava em storage/logs/mail-*.log
 * (útil em desenvolvimento). Em produção pode-se trocar por SMTP (ex.: PHPMailer).
 */
final class Mailer
{
    public function __construct(
        private readonly bool $enabled,
        private readonly string $from,
        private readonly string $fromName,
        private readonly Logger $logger,
    ) {
    }

    public function send(string $to, string $subject, string $text): bool
    {
        // impede injeção de cabeçalhos
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject . $to)) {
            return false;
        }
        if (!$this->enabled) {
            $this->logger->write('MAIL', "Para: {$to} | Assunto: {$subject}", ['body' => $text], 'mail');
            return true;
        }
        $headers = [
            'From' => sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($this->fromName), $this->from),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
        if (!$ok) {
            $this->logger->error('Falha ao enviar e-mail', ['to' => $to]);
        }
        return $ok;
    }
}
