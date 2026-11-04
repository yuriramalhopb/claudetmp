<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Tokens de redefinição de senha: apenas o HASH SHA-256 é salvo,
 * validade de 60 minutos e uso único.
 */
final class PasswordReset extends Model
{
    protected string $table = 'password_resets';

    public function createToken(int $userId): string
    {
        $this->db->execute('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
        $token = bin2hex(random_bytes(32));
        $this->db->insert('password_resets', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => self::now(),
        ]);
        return $token;
    }

    public function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return $this->db->fetch(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
            [hash('sha256', $token), self::now()]
        );
    }

    public function markUsed(int $id): void
    {
        $this->db->update('password_resets', ['used_at' => self::now()], 'id = :id', ['id' => $id]);
    }
}
