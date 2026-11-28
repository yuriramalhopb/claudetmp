<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;

/** Equipe da igreja: administradores e moderadores. */
final class User extends Model
{
    protected string $table = 'users';

    public const ROLES = ['admin' => 'Administrador (acesso total)', 'moderator' => 'Moderador (velas, pagamentos e mensagens)'];
    public const STATUS = ['active' => 'Ativo', 'blocked' => 'Bloqueado'];

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        return (bool) $this->db->fetchValue(
            'SELECT 1 FROM users WHERE email = ? AND id <> ?',
            [mb_strtolower(trim($email)), $ignoreId ?? 0]
        );
    }

    public function create(string $name, string $email, string $password, string $role = 'admin'): int
    {
        return $this->db->insert('users', [
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password_hash' => Auth::hashPassword($password),
            'role' => $role,
            'status' => 'active',
            'session_version' => 1,
            'created_at' => self::now(),
        ]);
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT id, name, email, role, status, last_login_at, created_at FROM users ORDER BY name');
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $this->db->update('users', ['name' => $name, 'email' => mb_strtolower(trim($email)), 'updated_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    /** Troca a senha; por padrão derruba todas as sessões da pessoa (session_version + 1). */
    public function updatePassword(int $id, string $password, bool $bumpSession = true): void
    {
        $this->db->execute(
            'UPDATE users SET password_hash = ?, session_version = session_version + ?, updated_at = ? WHERE id = ?',
            [Auth::hashPassword($password), $bumpSession ? 1 : 0, self::now(), $id]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->execute(
            'UPDATE users SET status = ?, session_version = session_version + 1, updated_at = ? WHERE id = ?',
            [$status, self::now(), $id]
        );
    }

    public function setRole(int $id, string $role): void
    {
        $this->db->execute('UPDATE users SET role = ?, updated_at = ? WHERE id = ?', [$role, self::now(), $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->update('users', ['last_login_at' => self::now()], 'id = :id', ['id' => $id]);
    }

    public function countAdmins(): int
    {
        return $this->count("role = 'admin' AND status = 'active'");
    }
}
