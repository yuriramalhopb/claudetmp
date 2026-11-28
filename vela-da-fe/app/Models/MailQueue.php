<?php
declare(strict_types=1);

namespace App\Models;

/** Fila de e-mails: o envio acontece fora do pedido do visitante (cron ou "reserva" após a resposta). */
final class MailQueue extends Model
{
    protected string $table = 'mail_queue';
    public const MAX_ATTEMPTS = 5;

    public function enqueue(string $to, string $subject, string $body): int
    {
        return $this->db->insert('mail_queue', [
            'to_email' => $to, 'subject' => mb_substr($subject, 0, 200), 'body' => $body,
            'status' => 'pending', 'attempts' => 0, 'created_at' => self::now(),
        ]);
    }

    public function pending(int $limit = 30): array
    {
        return $this->db->fetchAll("SELECT * FROM mail_queue WHERE status = 'pending' ORDER BY id LIMIT ?", [$limit]);
    }

    public function markSent(int $id): void
    {
        $this->db->update('mail_queue', ['status' => 'sent', 'sent_at' => self::now(), 'last_error' => null], 'id = :id', ['id' => $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->execute(
            // "status" vem antes de "attempts": o MySQL avalia os SET da esquerda para a direita
            "UPDATE mail_queue SET status = CASE WHEN attempts + 1 >= ? THEN 'failed' ELSE 'pending' END, attempts = attempts + 1, last_error = ? WHERE id = ?",
            [self::MAX_ATTEMPTS, mb_substr($error, 0, 255), $id]
        );
    }

    public function stats(): array
    {
        $rows = $this->db->fetchAll('SELECT status, COUNT(*) AS n FROM mail_queue GROUP BY status');
        return array_column($rows, 'n', 'status');
    }

    public function purgeOld(int $days = 60): void
    {
        $this->db->execute("DELETE FROM mail_queue WHERE status = 'sent' AND sent_at < ?", [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
