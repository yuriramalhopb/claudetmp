<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Criptografia simétrica autenticada (libsodium / XSalsa20-Poly1305) com a chave
 * APP_KEY do .env. Usada para guardar segredos no banco (ex.: segredo do 2FA),
 * de modo que um vazamento só do banco não exponha esses dados.
 */
final class Crypto
{
    private string $key;

    public function __construct(string $appKey)
    {
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = (string) base64_decode(substr($appKey, 7), true);
        }
        if (strlen($appKey) < 32) {
            throw new RuntimeException('APP_KEY ausente ou curta. Rode: php bin/install.php (gera a chave).');
        }
        // deriva uma subchave específica para este uso
        $this->key = hash_hkdf('sha256', $appKey, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'ducks-secretbox-v1');
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, 'v1:')) {
            throw new RuntimeException('Formato criptografado inválido.');
        }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Dado criptografado corrompido.');
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key
        );
        if ($plain === false) {
            throw new RuntimeException('Falha ao descriptografar (APP_KEY mudou?).');
        }
        return $plain;
    }
}
