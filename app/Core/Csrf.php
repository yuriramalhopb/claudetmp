<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Proteção CSRF por token sincronizado (um token forte por sessão, comparado em tempo constante).
 */
final class Csrf
{
    public const FIELD = '_csrf';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('_csrf_token');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf_token', $token);
        }
        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . $this->token() . '">';
    }

    public function validate(Request $request): void
    {
        $sent = $request->input(self::FIELD) ?: (string) $request->header('X-CSRF-Token');
        $token = $this->session->get('_csrf_token');
        if (!is_string($token) || $sent === '' || !hash_equals($token, $sent)) {
            throw new HttpException(419);
        }
    }

    public function rotate(): void
    {
        $this->session->forget('_csrf_token');
    }
}
