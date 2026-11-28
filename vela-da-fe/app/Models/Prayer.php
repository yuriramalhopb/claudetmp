<?php
declare(strict_types=1);

namespace App\Models;

/** Biblioteca de orações do site. */
final class Prayer extends Model
{
    protected string $table = 'prayers';

    public function active(): array
    {
        return $this->db->fetchAll('SELECT id, title, slug, body FROM prayers WHERE active = 1 ORDER BY sort_order, title');
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM prayers WHERE slug = ? AND active = 1', [$slug]);
    }

    public function random(): ?array
    {
        return $this->db->fetch('SELECT id, title, slug, body FROM prayers WHERE active = 1 ORDER BY ' . $this->db->random() . ' LIMIT 1');
    }
}
