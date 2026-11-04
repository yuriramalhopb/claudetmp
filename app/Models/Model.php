<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Base dos repositórios de dados.
 */
abstract class Model
{
    protected string $table = '';

    public function __construct(protected readonly Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]) > 0;
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", $params);
    }

    protected static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Converte strings vazias em NULL. */
    protected static function nullify(array $data): array
    {
        return array_map(static fn ($v) => $v === '' ? null : $v, $data);
    }

    /** Escapa curingas para uso em LIKE ... ESCAPE '!' (independe do modo NO_BACKSLASH_ESCAPES do MySQL). */
    protected static function like(string $term): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($term, 0, 80)) . '%';
    }
}
