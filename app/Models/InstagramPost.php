<?php
declare(strict_types=1);

namespace App\Models;

final class InstagramPost extends Model
{
    protected string $table = 'instagram_posts';

    /** Aceita somente links públicos de post/reel do Instagram. */
    public const PERMALINK_REGEX = '#^https://(www\.)?instagram\.com/(?:[A-Za-z0-9._]{1,30}/)?(p|reel|tv)/([A-Za-z0-9_-]{5,40})/?(\?.*)?$#';

    public function visible(int $limit, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM instagram_posts WHERE hidden = 0 ORDER BY COALESCE(posted_at, created_at) DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public function countVisible(): int
    {
        return $this->count('hidden = 0');
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM instagram_posts ORDER BY COALESCE(posted_at, created_at) DESC');
    }

    public function findByIgId(string $igId): ?array
    {
        return $this->db->fetch('SELECT * FROM instagram_posts WHERE ig_id = ?', [$igId]);
    }

    public function findByPermalink(string $permalink): ?array
    {
        return $this->db->fetch('SELECT * FROM instagram_posts WHERE permalink = ?', [$permalink]);
    }

    public function upsertFromApi(array $item, ?string $localFile): void
    {
        $existing = $this->findByIgId((string) $item['id']);
        $postedAt = isset($item['timestamp']) ? date('Y-m-d H:i:s', strtotime((string) $item['timestamp']) ?: time()) : null;
        $data = [
            'caption' => mb_substr((string) ($item['caption'] ?? ''), 0, 5000) ?: null,
            'media_type' => (string) ($item['media_type'] ?? 'IMAGE'),
            'posted_at' => $postedAt,
        ];
        if ($localFile !== null) {
            $data['local_file'] = $localFile;
        }
        if ($existing) {
            $this->db->update('instagram_posts', $data, 'id = :id', ['id' => (int) $existing['id']]);
            return;
        }
        // Um post adicionado manualmente antes pode ser "promovido" a post da API.
        $manual = $this->findByPermalink(self::normalizePermalink((string) $item['permalink']));
        if ($manual) {
            $this->db->update('instagram_posts', $data + ['ig_id' => (string) $item['id'], 'source' => 'api'], 'id = :id', ['id' => (int) $manual['id']]);
            return;
        }
        $this->db->insert('instagram_posts', $data + [
            'ig_id' => (string) $item['id'],
            'permalink' => self::normalizePermalink((string) $item['permalink']),
            'source' => 'api',
            'hidden' => 0,
            'created_at' => self::now(),
        ]);
    }

    public function addManual(string $permalink, ?string $caption): int
    {
        return $this->db->insert('instagram_posts', [
            'permalink' => self::normalizePermalink($permalink),
            'caption' => $caption ?: null,
            'media_type' => 'EMBED',
            'source' => 'manual',
            'hidden' => 0,
            'posted_at' => self::now(),
            'created_at' => self::now(),
        ]);
    }

    public function toggleHidden(int $id): void
    {
        $this->db->execute('UPDATE instagram_posts SET hidden = 1 - hidden WHERE id = ?', [$id]);
    }

    public static function normalizePermalink(string $url): string
    {
        if (preg_match(self::PERMALINK_REGEX, $url, $m)) {
            return 'https://www.instagram.com/' . $m[2] . '/' . $m[3] . '/';
        }
        return $url;
    }
}
