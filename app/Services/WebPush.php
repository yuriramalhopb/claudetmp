<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Web Push sem dependências (RFC 8030 + RFC 8291 + RFC 8292):
 *  - mensagem criptografada ponta a ponta (aes128gcm, ECDH P-256 + HKDF)
 *  - autenticação do servidor com VAPID (JWT ES256)
 *  - só envia para serviços de push conhecidos (proteção contra SSRF)
 */
final class WebPush
{
    /** Serviços oficiais de push dos navegadores. */
    public const ALLOWED_HOSTS = ['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];
    private const SPKI_P256_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    private ?\OpenSSLAsymmetricKey $vapidKey = null;
    private string $vapidPublic = '';

    public function __construct(string $privateKeyB64, private readonly string $subject)
    {
        if ($privateKeyB64 === '') {
            return;
        }
        $pem = base64_decode($privateKeyB64, true);
        $key = $pem ? openssl_pkey_get_private($pem) : false;
        if ($key === false) {
            throw new RuntimeException('VAPID_PRIVATE_KEY inválida. Gere com: php bin/install.php');
        }
        $this->vapidKey = $key;
        $this->vapidPublic = self::publicKeyRaw($key);
    }

    public function isConfigured(): bool
    {
        return $this->vapidKey !== null;
    }

    /** Chave pública VAPID (base64url) usada no navegador em pushManager.subscribe(). */
    public function publicKey(): string
    {
        return self::b64u($this->vapidPublic);
    }

    /** @return array{private_b64:string, public_b64url:string} */
    public static function generateVapidKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false || !openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('OpenSSL não conseguiu gerar a chave EC.');
        }
        return ['private_b64' => base64_encode($pem), 'public_b64url' => self::b64u(self::publicKeyRaw($key))];
    }

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        if (strlen($endpoint) > 700 || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }
        if (strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Envia uma mensagem. Retorna o status HTTP (201 = ok; 404/410 = inscrição expirada).
     */
    public function send(string $endpoint, string $p256dh, string $auth, array $payload, int $ttl = 86400): int
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Push não configurado (VAPID).');
        }
        if (!self::isAllowedEndpoint($endpoint)) {
            throw new RuntimeException('Endpoint de push não permitido.');
        }
        $body = $this->encrypt(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            self::b64uDecode($p256dh),
            self::b64uDecode($auth)
        );
        $headers = [
            'TTL: ' . $ttl,
            'Urgency: normal',
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: vapid t=' . $this->vapidJwt($endpoint) . ', k=' . $this->publicKey(),
        ];
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($status === 0) {
            throw new RuntimeException('Falha de conexão com o serviço de push: ' . $err);
        }
        return $status;
    }

    /** Criptografia aes128gcm (RFC 8291). Público para os testes automáticos. */
    public function encrypt(string $plaintext, string $uaPublic, string $authSecret, ?string $salt = null): string
    {
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($authSecret) !== 16) {
            throw new RuntimeException('Chaves da inscrição de push inválidas.');
        }
        if (strlen($plaintext) > 3800) {
            throw new RuntimeException('Mensagem de push muito grande.');
        }
        $ephemeral = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asPublic = self::publicKeyRaw($ephemeral);
        $peer = openssl_pkey_get_public(self::rawToPem($uaPublic));
        if ($peer === false) {
            throw new RuntimeException('Chave pública do navegador inválida.');
        }
        $ecdh = openssl_pkey_derive($peer, $ephemeral, 32);
        if ($ecdh === false || strlen($ecdh) !== 32) {
            throw new RuntimeException('Falha no ECDH.');
        }
        $salt ??= random_bytes(16);
        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $tag = '';
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Falha ao criptografar a mensagem.');
        }
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    private function vapidJwt(string $endpoint): string
    {
        $aud = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $header = self::b64u((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64u((string) json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $this->subject], JSON_UNESCAPED_SLASHES));
        $data = $header . '.' . $claims;
        if (!openssl_sign($data, $der, $this->vapidKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Falha ao assinar o JWT VAPID.');
        }
        return $data . '.' . self::b64u(self::derToRaw($der));
    }

    /** Converte assinatura ECDSA DER em r||s (64 bytes), formato exigido pelo JWT. */
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7f;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $out;
    }

    private static function publicKeyRaw(\OpenSSLAsymmetricKey $key): string
    {
        $d = openssl_pkey_get_details($key);
        if (!isset($d['ec']['x'], $d['ec']['y'])) {
            throw new RuntimeException('Chave EC inválida.');
        }
        return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    private static function rawToPem(string $raw): string
    {
        $der = hex2bin(self::SPKI_P256_PREFIX) . $raw;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string
    {
        $out = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $out === false ? '' : $out;
    }
}
