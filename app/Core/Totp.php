<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Senhas de uso único baseadas em tempo (RFC 6238 / RFC 4226) — compatível com
 * Google Authenticator, Microsoft Authenticator, Authy, 1Password etc.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;
    public const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $bin = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica o código aceitando ±1 intervalo (relógio do celular adiantado/atrasado).
     * Retorna o "step" usado (para impedir reuso do mesmo código) ou null.
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $current = intdiv($now ?? time(), self::PERIOD);
        for ($i = -1; $i <= 1; $i++) {
            $step = $current + $i;
            if ($lastStep !== null && $step <= $lastStep) {
                continue; // código já usado (proteção contra replay)
            }
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** @return string[] 10 códigos de recuperação legíveis (ex.: 7KQ4-M2XR) */
    public static function recoveryCodes(int $n = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = self::base32Encode(random_bytes(5)); // 8 caracteres, 40 bits
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }
        return $codes;
    }

    public static function normalizeRecovery(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $code));
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
