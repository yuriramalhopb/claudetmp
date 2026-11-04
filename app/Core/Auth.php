<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Autenticação:
 *  - senhas com Argon2id (ou bcrypt), rehash automático
 *  - bloqueio por excesso de tentativas (por e-mail e por IP)
 *  - mensagem genérica (não revela se o e-mail existe) e tempo constante
 *  - regeneração do ID de sessão no login
 *  - "session_version": trocar senha/bloquear derruba todas as sessões do usuário
 */
final class Auth
{
    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(
        private readonly Session $session,
        private readonly User $users,
        private readonly RateLimiter $limiter,
        private readonly int $maxAttempts,
        private readonly int $lockMinutes,
    ) {
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, self::algo());
    }

    private static function algo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $keyEmail = 'login:email:' . $email;
        $keyIp = 'login:ip:' . $ip;

        if ($this->limiter->tooManyAttempts($keyEmail, $this->maxAttempts)
            || $this->limiter->tooManyAttempts($keyIp, $this->maxAttempts * 4)) {
            $wait = (int) ceil(max($this->limiter->availableIn($keyEmail), $this->limiter->availableIn($keyIp)) / 60);
            return ['ok' => false, 'error' => "Muitas tentativas. Tente novamente em {$wait} minuto(s)."];
        }

        $user = $this->users->findByEmail($email);
        // Verifica um hash falso quando o usuário não existe para manter o tempo de resposta constante.
        $hash = $user['password_hash'] ?? '$2y$10$UltwP5BoT5aNnT4xSnxBEOmPPbZpCNqC82bLgOwryihXzM1hWN7Cu';
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            $this->limiter->hit($keyEmail, $this->lockMinutes * 60);
            $this->limiter->hit($keyIp, $this->lockMinutes * 60);
            return ['ok' => false, 'error' => 'E-mail ou senha incorretos.'];
        }

        if ($user['status'] === 'blocked') {
            return ['ok' => false, 'error' => 'Seu acesso está bloqueado. Fale com a administração da turma.'];
        }

        if (password_needs_rehash($user['password_hash'], self::algo())) {
            $this->users->updatePassword((int) $user['id'], $password, false);
        }

        $this->limiter->clear($keyEmail);
        $this->login($user);
        return ['ok' => true];
    }

    public function login(array $user): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->session->set('session_version', (int) $user['session_version']);
        $this->users->touchLogin((int) $user['id']);
        $this->user = null;
        $this->loaded = false;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
        $this->loaded = true;
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $id = $this->session->get('user_id');
        if (!is_int($id)) {
            return null;
        }
        $user = $this->users->find($id);
        if ($user === null
            || $user['status'] === 'blocked'
            || (int) $user['session_version'] !== (int) $this->session->get('session_version')) {
            $this->session->forget('user_id');
            return null;
        }
        return $this->user = $user;
    }

    public function id(): ?int
    {
        $u = $this->user();
        return $u ? (int) $u['id'] : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isApproved(): bool
    {
        return ($this->user()['status'] ?? null) === 'approved';
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === 'admin' && $this->isApproved();
    }

    /** Recarrega o usuário e mantém ESTA sessão válida após trocar a senha. */
    public function refresh(): void
    {
        $id = $this->session->get('user_id');
        if (is_int($id) && ($user = $this->users->find($id)) !== null) {
            $this->session->set('session_version', (int) $user['session_version']);
        }
        $this->user = null;
        $this->loaded = false;
    }
}
