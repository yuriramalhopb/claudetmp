<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cabeçalhos de segurança, incluindo Content-Security-Policy com nonce por requisição.
 * Nenhum script/estilo inline é permitido sem o nonce; as views não usam on*="" nem style="".
 */
final class SecurityHeaders
{
    private string $nonce;

    public function __construct()
    {
        $this->nonce = base64_encode(random_bytes(18));
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function send(bool $https): void
    {
        if (headers_sent()) {
            return;
        }
        $n = $this->nonce;
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$n}'",
            "style-src 'self' 'nonce-{$n}'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-src https://www.google.com https://maps.google.com",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ] + ($https ? [100 => 'upgrade-insecure-requests'] : []));

        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
