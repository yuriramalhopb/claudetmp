<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Mailer;

/**
 * Fila de e-mails: a página responde na hora e o envio acontece no cron,
 * com novas tentativas (backoff) em caso de falha do servidor SMTP.
 */
final class MailQueue extends Model
{
    protected string $table = 'mail_queue';
    private const MAX_ATTEMPTS = 5;

    public function push(string $to, string $subject, string $body, int $delaySeconds = 0): int
    {
        return $this->db->insert('mail_queue', [
            'to_email' => mb_strtolower($to),
            'subject' => mb_substr($subject, 0, 200),
            'body' => $body,
            'send_after' => date('Y-m-d H:i:s', time() + $delaySeconds),
            'created_at' => self::now(),
        ]);
    }

    /** @return array{sent:int, failed:int} */
    public function process(Mailer $mailer, int $limit = 50): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM mail_queue WHERE sent_at IS NULL AND attempts < ? AND send_after <= ? ORDER BY id LIMIT ?',
            [self::MAX_ATTEMPTS, self::now(), $limit]
        );
        $sent = $failed = 0;
        foreach ($rows as $row) {
            try {
                $mailer->send($row['to_email'], $row['subject'], $row['body']);
                $this->db->update('mail_queue', ['sent_at' => self::now(), 'last_error' => null], 'id = :id', ['id' => (int) $row['id']]);
                $sent++;
            } catch (\Throwable $e) {
                $attempts = (int) $row['attempts'] + 1;
                $this->db->update('mail_queue', [
                    'attempts' => $attempts,
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                    'send_after' => date('Y-m-d H:i:s', time() + 60 * (2 ** $attempts)),
                ], 'id = :id', ['id' => (int) $row['id']]);
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    public function stats(): array
    {
        return [
            'pending' => $this->count('sent_at IS NULL AND attempts < ?', [self::MAX_ATTEMPTS]),
            'failed' => $this->count('sent_at IS NULL AND attempts >= ?', [self::MAX_ATTEMPTS]),
            'sent_24h' => $this->count('sent_at >= ?', [date('Y-m-d H:i:s', time() - 86400)]),
            'last_error' => $this->db->fetchValue('SELECT last_error FROM mail_queue WHERE last_error IS NOT NULL ORDER BY id DESC LIMIT 1'),
        ];
    }

    public function purgeOld(int $days = 30): void
    {
        $this->db->execute('DELETE FROM mail_queue WHERE sent_at IS NOT NULL AND sent_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
