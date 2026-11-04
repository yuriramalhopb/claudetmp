<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Registro de auditoria das ações administrativas e eventos de segurança.
 */
final class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    public function record(?int $userId, string $action, string $details = '', string $ip = ''): void
    {
        $this->db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => mb_substr($action, 0, 80),
            'details' => mb_substr($details, 0, 2000) ?: null,
            'ip' => $ip ?: null,
            'created_at' => self::now(),
        ]);
    }

    public function latest(int $limit = 200): array
    {
        return $this->db->fetchAll(
            'SELECT l.*, u.name AS user_name FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT ?',
            [$limit]
        );
    }
}
