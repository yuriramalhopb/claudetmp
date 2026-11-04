<?php
declare(strict_types=1);

namespace App\Models;

final class Message extends Model
{
    protected string $table = 'messages';

    public function create(array $data, string $ip): int
    {
        return $this->db->insert('messages', [
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?: null,
            'message' => $data['message'],
            'ip_hash' => hash('sha256', $ip),
            'created_at' => self::now(),
        ]);
    }

    public function latest(int $limit = 200): array
    {
        return $this->db->fetchAll('SELECT * FROM messages ORDER BY is_read ASC, created_at DESC LIMIT ?', [$limit]);
    }

    public function markRead(int $id): void
    {
        $this->db->update('messages', ['is_read' => 1], 'id = :id', ['id' => $id]);
    }
}
