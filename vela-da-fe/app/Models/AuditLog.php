<?php
declare(strict_types=1);

namespace App\Models;

/** Registro das ações da equipe no painel. */
final class AuditLog extends Model
{
    protected string $table = 'audit_log';

    public function record(?int $userId, string $action, string $details, string $ip): void
    {
        $this->db->insert('audit_log', [
            'user_id' => $userId,
            'action' => mb_substr($action, 0, 60),
            'details' => mb_substr($details, 0, 500),
            'ip' => mb_substr($ip, 0, 45),
            'created_at' => self::now(),
        ]);
    }

    /** @return array{items:array, total:int, pages:int, page:int} */
    public function paginate(int $page, int $perPage): array
    {
        $total = $this->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = $this->db->fetchAll(
            'SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ? OFFSET ?',
            [$perPage, ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public function purgeOld(int $days = 365): void
    {
        $this->db->execute('DELETE FROM audit_log WHERE created_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
