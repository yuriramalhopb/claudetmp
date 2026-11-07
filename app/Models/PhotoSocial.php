<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Interação nas fotos: curtidas, comentários e marcações de trilheiros.
 */
final class PhotoSocial extends Model
{
    protected string $table = 'photo_comments';

    // ---- curtidas ----
    public function toggleLike(int $photoId, int $userId): bool
    {
        if ($this->db->execute('DELETE FROM photo_likes WHERE photo_id = ? AND user_id = ?', [$photoId, $userId]) > 0) {
            return false;
        }
        $this->db->insert('photo_likes', ['photo_id' => $photoId, 'user_id' => $userId, 'created_at' => self::now()]);
        return true;
    }

    public function liked(int $photoId, int $userId): bool
    {
        return (bool) $this->db->fetchValue('SELECT 1 FROM photo_likes WHERE photo_id = ? AND user_id = ?', [$photoId, $userId]);
    }

    public function likers(int $photoId): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.nickname FROM photo_likes l JOIN users u ON u.id = l.user_id
             WHERE l.photo_id = ? AND u.status = 'approved' ORDER BY l.created_at DESC",
            [$photoId]
        );
    }

    // ---- comentários ----
    public function comments(int $photoId): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, u.name, u.nickname, u.photo FROM photo_comments c JOIN users u ON u.id = c.user_id
             WHERE c.photo_id = ? AND u.status = 'approved' ORDER BY c.id",
            [$photoId]
        );
    }

    public function addComment(int $photoId, int $userId, string $body): int
    {
        return $this->db->insert('photo_comments', ['photo_id' => $photoId, 'user_id' => $userId, 'body' => $body, 'created_at' => self::now()]);
    }

    public function findComment(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM photo_comments WHERE id = ?', [$id]);
    }

    // ---- marcações ----
    public function tags(int $photoId): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.nickname, u.photo, t.tagged_by FROM photo_tags t JOIN users u ON u.id = t.user_id
             WHERE t.photo_id = ? AND u.status = 'approved' ORDER BY COALESCE(u.nickname, u.name)",
            [$photoId]
        );
    }

    public function tag(int $photoId, int $userId, int $byId): bool
    {
        if ($this->db->fetchValue('SELECT 1 FROM photo_tags WHERE photo_id = ? AND user_id = ?', [$photoId, $userId])) {
            return false;
        }
        $this->db->insert('photo_tags', ['photo_id' => $photoId, 'user_id' => $userId, 'tagged_by' => $byId, 'created_at' => self::now()]);
        return true;
    }

    public function findTag(int $photoId, int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM photo_tags WHERE photo_id = ? AND user_id = ?', [$photoId, $userId]);
    }

    public function untag(int $photoId, int $userId): void
    {
        $this->db->execute('DELETE FROM photo_tags WHERE photo_id = ? AND user_id = ?', [$photoId, $userId]);
    }

    /** Fotos em que o trilheiro foi marcado (perfil). */
    public function photosOfUser(int $userId, int $limit = 24): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.filename, p.caption FROM photo_tags t JOIN photos p ON p.id = t.photo_id
             WHERE t.user_id = ? AND p.status = 'approved' ORDER BY p.id DESC LIMIT ?",
            [$userId, $limit]
        );
    }
}
