<?php
declare(strict_types=1);

namespace App\Models;

final class Album extends Model
{
    protected string $table = 'albums';

    public function allWithCover(): array
    {
        return $this->db->fetchAll(
            "SELECT a.*,
                (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id AND p.status = 'approved') AS total,
                (SELECT p.filename FROM photos p WHERE p.album_id = a.id AND p.status = 'approved' ORDER BY p.id DESC LIMIT 1) AS cover
             FROM albums a ORDER BY COALESCE(a.event_date, a.created_at) DESC"
        );
    }

    public function options(): array
    {
        return $this->db->fetchAll('SELECT id, title FROM albums ORDER BY created_at DESC');
    }

    public function create(string $title, ?string $description, ?string $eventDate): int
    {
        return $this->db->insert('albums', [
            'title' => $title,
            'description' => $description ?: null,
            'event_date' => $eventDate ?: null,
            'created_at' => self::now(),
        ]);
    }

    public function updateAlbum(int $id, string $title, ?string $description, ?string $eventDate): void
    {
        $this->db->update('albums', [
            'title' => $title,
            'description' => $description ?: null,
            'event_date' => $eventDate ?: null,
        ], 'id = :id', ['id' => $id]);
    }
}
