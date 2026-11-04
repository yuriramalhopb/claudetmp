<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sessão endurecida:
 *  - cookie HttpOnly, SameSite=Lax, Secure quando HTTPS
 *  - modo estrito (rejeita IDs não inicializados) e somente cookies
 *  - expiração por inatividade e regeneração periódica do ID
 *  - vínculo com o User-Agent para dificultar sequestro de sessão
 */
final class Session
{
    private const REGENERATE_EVERY = 900; // 15 min

    public function __construct(
        private readonly string $savePath,
        private readonly int $idleMinutes,
        private readonly bool $secure,
    ) {
    }

    public function start(string $userAgent): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0700, true);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) ($this->idleMinutes * 60));
        session_save_path($this->savePath);
        session_name($this->secure ? '__Host-ducks_sid' : 'ducks_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        $fingerprint = hash('sha256', $userAgent);

        $expired = isset($_SESSION['_last_activity']) && ($now - $_SESSION['_last_activity']) > $this->idleMinutes * 60;
        $hijack = isset($_SESSION['_fingerprint']) && !hash_equals($_SESSION['_fingerprint'], $fingerprint);
        if ($expired || $hijack) {
            $this->destroy();
            session_start();
        }

        $_SESSION['_fingerprint'] = $fingerprint;
        $_SESSION['_last_activity'] = $now;
        if (!isset($_SESSION['_created']) || $now - $_SESSION['_created'] > self::REGENERATE_EVERY) {
            $this->regenerate();
        }
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Lê e remove (para mensagens "flash"). */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
            session_destroy();
        }
    }
}
