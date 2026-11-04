<?php
declare(strict_types=1);

namespace App\Models;

final class Event extends Model
{
    protected string $table = 'events';

    public const LEVELS = ['leve' => 'Leve', 'moderado' => 'Moderado', 'pesado' => 'Pesado', 'extremo' => 'Extremo'];
    public const FIELDS = ['title', 'description', 'start_at', 'end_at', 'location', 'city', 'organizer', 'level', 'distance_km', 'price', 'external_link'];

    public function upcoming(string $type = '', string $level = '', string $search = '', int $limit = 100): array
    {
        [$where, $params] = $this->filters($type, $level, $search);
        return $this->db->fetchAll(
            "SELECT e.*, (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id) AS going
             FROM events e WHERE e.status = 'approved' AND COALESCE(e.end_at, e.start_at) >= ? AND {$where}
             ORDER BY e.start_at ASC LIMIT ?",
            array_merge([date('Y-m-d 00:00:00')], $params, [$limit])
        );
    }

    public function past(string $type = '', string $level = '', string $search = '', int $limit = 30): array
    {
        [$where, $params] = $this->filters($type, $level, $search);
        return $this->db->fetchAll(
            "SELECT e.*, (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id) AS going
             FROM events e WHERE e.status = 'approved' AND COALESCE(e.end_at, e.start_at) < ? AND {$where}
             ORDER BY e.start_at DESC LIMIT ?",
            array_merge([date('Y-m-d 00:00:00')], $params, [$limit])
        );
    }

    private function filters(string $type, string $level, string $search): array
    {
        $where = '1=1';
        $params = [];
        if ($type === 'ducks') {
            $where .= ' AND e.is_own = 1';
        } elseif ($type === 'outras') {
            $where .= ' AND e.is_own = 0';
        }
        if (isset(self::LEVELS[$level])) {
            $where .= ' AND e.level = ?';
            $params[] = $level;
        }
        if ($search !== '') {
            $where .= " AND (e.title LIKE ? ESCAPE '!' OR e.city LIKE ? ESCAPE '!' OR e.organizer LIKE ? ESCAPE '!' OR e.location LIKE ? ESCAPE '!')";
            $like = self::like($search);
            array_push($params, $like, $like, $like, $like);
        }
        return [$where, $params];
    }

    public function findApproved(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM events WHERE id = ? AND status = 'approved'", [$id]);
    }

    public function adminList(string $status = ''): array
    {
        $params = [];
        $where = '1=1';
        if (in_array($status, ['approved', 'pending'], true)) {
            $where = 'e.status = ?';
            $params[] = $status;
        }
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS author, (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id = e.id) AS going
             FROM events e LEFT JOIN users u ON u.id = e.created_by WHERE {$where}
             ORDER BY CASE WHEN e.status = 'pending' THEN 0 ELSE 1 END, e.start_at DESC",
            $params
        );
    }

    public function create(array $data, bool $isOwn, string $status, ?int $userId, ?string $cover): int
    {
        $row = $this->normalize($data);
        $row += [
            'is_own' => $isOwn ? 1 : 0,
            'status' => $status,
            'created_by' => $userId,
            'cover' => $cover,
            'created_at' => self::now(),
        ];
        return $this->db->insert('events', $row);
    }

    public function updateEvent(int $id, array $data, bool $isOwn, ?string $cover): void
    {
        $row = $this->normalize($data);
        $row['is_own'] = $isOwn ? 1 : 0;
        $row['updated_at'] = self::now();
        if ($cover !== null) {
            $row['cover'] = $cover;
        }
        $this->db->update('events', $row, 'id = :id', ['id' => $id]);
    }

    public function approve(int $id): void
    {
        $this->db->update('events', ['status' => 'approved', 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    private function normalize(array $data): array
    {
        $row = self::nullify(array_intersect_key($data, array_flip(self::FIELDS)));
        foreach (['start_at', 'end_at'] as $k) {
            if (!empty($row[$k])) {
                $row[$k] = str_replace('T', ' ', $row[$k]) . ':00';
            }
        }
        $row['distance_km'] = isset($row['distance_km']) ? (int) $row['distance_km'] : null;
        return $row;
    }

    // ---- Presença (RSVP) ----
    public function toggleRsvp(int $eventId, int $userId): bool
    {
        if ($this->isGoing($eventId, $userId)) {
            $this->db->execute('DELETE FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$eventId, $userId]);
            return false;
        }
        $this->db->insert('event_rsvps', ['event_id' => $eventId, 'user_id' => $userId, 'created_at' => self::now()]);
        return true;
    }

    public function isGoing(int $eventId, int $userId): bool
    {
        return (bool) $this->db->fetchValue('SELECT 1 FROM event_rsvps WHERE event_id = ? AND user_id = ?', [$eventId, $userId]);
    }

    public function attendees(int $eventId): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.nickname, u.photo, u.bike_model FROM event_rsvps r
             JOIN users u ON u.id = r.user_id WHERE r.event_id = ? AND u.status = 'approved' ORDER BY r.created_at",
            [$eventId]
        );
    }

    public function userAgenda(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT e.* FROM event_rsvps r JOIN events e ON e.id = r.event_id
             WHERE r.user_id = ? AND e.status = 'approved' AND e.start_at >= ? ORDER BY e.start_at",
            [$userId, date('Y-m-d 00:00:00')]
        );
    }
}
