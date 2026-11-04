<?php
declare(strict_types=1);

namespace App\Models;

final class Photo extends Model
{
    protected string $table = 'photos';

    public function byAlbum(int $albumId, int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, u.name AS author, u.nickname AS author_nick FROM photos p
             LEFT JOIN users u ON u.id = p.uploaded_by
             WHERE p.album_id = ? AND p.status = 'approved' ORDER BY p.id DESC LIMIT ? OFFSET ?",
            [$albumId, $limit, $offset]
        );
    }

    public function countByAlbum(int $albumId): int
    {
        return $this->count("album_id = ? AND status = 'approved'", [$albumId]);
    }

    public function latest(int $limit): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, a.title AS album_title FROM photos p LEFT JOIN albums a ON a.id = p.album_id
             WHERE p.status = 'approved' ORDER BY p.id DESC LIMIT ?",
            [$limit]
        );
    }

    public function pending(): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, a.title AS album_title, u.name AS author FROM photos p
             LEFT JOIN albums a ON a.id = p.album_id LEFT JOIN users u ON u.id = p.uploaded_by
             WHERE p.status = 'pending' ORDER BY p.id"
        );
    }

    public function adminByAlbum(int $albumId): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, u.name AS author FROM photos p LEFT JOIN users u ON u.id = p.uploaded_by WHERE p.album_id = ? ORDER BY p.id DESC',
            [$albumId]
        );
    }

    public function filenamesByAlbum(int $albumId): array
    {
        return array_column($this->db->fetchAll('SELECT filename FROM photos WHERE album_id = ?', [$albumId]), 'filename');
    }

    public function filenamesByUser(int $userId): array
    {
        return array_column($this->db->fetchAll('SELECT filename FROM photos WHERE uploaded_by = ?', [$userId]), 'filename');
    }

    public function create(int $albumId, string $filename, ?string $caption, ?int $userId, string $status): int
    {
        return $this->db->insert('photos', [
            'album_id' => $albumId,
            'filename' => $filename,
            'caption' => $caption ?: null,
            'uploaded_by' => $userId,
            'status' => $status,
            'created_at' => self::now(),
        ]);
    }

    public function approve(int $id): void
    {
        $this->db->update('photos', ['status' => 'approved'], 'id = :id', ['id' => $id]);
    }

    public function deleteByUser(int $userId): void
    {
        $this->db->execute('DELETE FROM photos WHERE uploaded_by = ?', [$userId]);
    }
}
