<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Setting
{
    private array $cache = [];

    public function __construct(private readonly Database $db)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (!array_key_exists($key, $this->cache)) {
            $value = $this->db->fetchValue('SELECT svalue FROM settings WHERE skey = ?', [$key]);
            $this->cache[$key] = $value === null ? null : (string) $value;
        }
        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        $this->db->transaction(function (Database $db) use ($key, $value): void {
            if ($db->fetchValue('SELECT 1 FROM settings WHERE skey = ?', [$key])) {
                $db->execute('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
            } else {
                $db->insert('settings', ['skey' => $key, 'svalue' => $value]);
            }
        });
        $this->cache[$key] = $value;
    }
}
