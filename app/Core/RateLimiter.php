<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitador de tentativas persistido no banco (funciona com vários processos PHP).
 * Chaves são guardadas como hash para não armazenar e-mails/IPs em texto puro.
 */
final class RateLimiter
{
    public function __construct(private readonly Database $db)
    {
    }

    public function tooManyAttempts(string $key, int $max): bool
    {
        $row = $this->row($key);
        return $row !== null && (int) $row['hits'] >= $max && (int) $row['reset_at'] > time();
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $hash = $this->hash($key);
        $now = time();
        $row = $this->row($key);
        if ($row === null || (int) $row['reset_at'] <= $now) {
            $this->db->execute('DELETE FROM rate_limits WHERE rl_key = ?', [$hash]);
            $this->db->insert('rate_limits', ['rl_key' => $hash, 'hits' => 1, 'reset_at' => $now + $decaySeconds]);
            return 1;
        }
        $this->db->execute('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?', [$hash]);
        return (int) $row['hits'] + 1;
    }

    /** Conta uma tentativa e informa se o limite foi excedido. */
    public function attempt(string $key, int $max, int $decaySeconds): bool
    {
        if ($this->tooManyAttempts($key, $max)) {
            return false;
        }
        $this->hit($key, $decaySeconds);
        return true;
    }

    public function availableIn(string $key): int
    {
        $row = $this->row($key);
        return $row ? max(0, (int) $row['reset_at'] - time()) : 0;
    }

    public function clear(string $key): void
    {
        $this->db->execute('DELETE FROM rate_limits WHERE rl_key = ?', [$this->hash($key)]);
    }

    public function purgeExpired(): void
    {
        $this->db->execute('DELETE FROM rate_limits WHERE reset_at < ?', [time()]);
    }

    private function row(string $key): ?array
    {
        return $this->db->fetch('SELECT hits, reset_at FROM rate_limits WHERE rl_key = ?', [$this->hash($key)]);
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
