<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\WebPush;

final class PushSubscription extends Model
{
    protected string $table = 'push_subscriptions';

    public function save(?int $userId, string $endpoint, string $p256dh, string $auth, string $userAgent): void
    {
        $hash = hash('sha256', $endpoint);
        $data = ['user_id' => $userId, 'p256dh' => $p256dh, 'auth' => $auth, 'user_agent' => mb_substr($userAgent, 0, 255), 'failures' => 0];
        if ($this->db->fetchValue('SELECT 1 FROM push_subscriptions WHERE endpoint_hash = ?', [$hash])) {
            $this->db->update('push_subscriptions', $data, 'endpoint_hash = :h', ['h' => $hash]);
            return;
        }
        $this->db->insert('push_subscriptions', $data + ['endpoint' => $endpoint, 'endpoint_hash' => $hash, 'created_at' => self::now()]);
    }

    public function removeByEndpoint(string $endpoint, ?int $userId): void
    {
        $this->db->execute('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND (user_id IS NULL OR user_id = ?)', [hash('sha256', $endpoint), $userId ?? 0]);
    }

    public function countForUser(int $userId): int
    {
        return $this->count('user_id = ?', [$userId]);
    }

    /**
     * Envia as notificações pendentes para os aparelhos inscritos.
     * @return array{notifications:int, sent:int, removed:int, failed:int}
     */
    public function dispatchPending(WebPush $push, int $limit = 10): array
    {
        $stats = ['notifications' => 0, 'sent' => 0, 'removed' => 0, 'failed' => 0];
        if (!$push->isConfigured()) {
            return $stats;
        }
        $pending = $this->db->fetchAll('SELECT * FROM notifications WHERE pushed_at IS NULL ORDER BY id LIMIT ?', [$limit]);
        foreach ($pending as $n) {
            // marca antes de enviar: nunca duplica a notificação se o cron rodar em paralelo
            if ($this->db->execute('UPDATE notifications SET pushed_at = ? WHERE id = ? AND pushed_at IS NULL', [self::now(), (int) $n['id']]) === 0) {
                continue;
            }
            $stats['notifications']++;
            $sql = "SELECT s.* FROM push_subscriptions s JOIN users u ON u.id = s.user_id
                    WHERE u.status = 'approved' AND u.notify_push = 1";
            $params = [];
            if ($n['only_user_ids'] !== null) {
                $ids = array_filter(array_map('intval', explode(',', (string) $n['only_user_ids'])));
                if (!$ids) {
                    continue;
                }
                $sql .= ' AND s.user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = array_values($ids);
            }
            $payload = ['title' => $n['title'], 'body' => $n['body'], 'url' => $n['url'], 'tag' => 'ducks-' . $n['id']];
            foreach ($this->db->fetchAll($sql, $params) as $sub) {
                try {
                    $status = $push->send($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload);
                } catch (\Throwable) {
                    $status = 0;
                }
                if ($status >= 200 && $status < 300) {
                    $this->db->update('push_subscriptions', ['failures' => 0, 'last_success_at' => self::now()], 'id = :id', ['id' => (int) $sub['id']]);
                    $stats['sent']++;
                } elseif (in_array($status, [404, 410], true) || (int) $sub['failures'] >= 5) {
                    $this->delete((int) $sub['id']); // aparelho desinstalou/expirou
                    $stats['removed']++;
                } else {
                    $this->db->execute('UPDATE push_subscriptions SET failures = failures + 1 WHERE id = ?', [(int) $sub['id']]);
                    $stats['failed']++;
                }
            }
        }
        return $stats;
    }
}
