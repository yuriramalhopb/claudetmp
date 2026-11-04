<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Encapsula a requisição HTTP. Entradas de texto são normalizadas (trim + UTF-8 válido).
 */
final class Request
{
    private array $routeParams = [];

    public function __construct(
        private readonly array $get,
        private readonly array $post,
        private readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function path(): string
    {
        $path = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');
        return $path;
    }

    public function fullUrl(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $this->get[$key] ?? $default;
        if (!is_string($value)) {
            return $default;
        }
        return self::clean($value);
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->get[$key] ?? $default;
        return is_string($value) ? self::clean($value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return preg_match('/^-?\d{1,10}$/', $value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 'on', 'true', 'yes'], true);
    }

    /** Retorna apenas os campos informados (whitelist), já limpos. */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key);
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || is_array($file['name'] ?? null)) {
            return null;
        }
        return ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? null : $file;
    }

    /** Normaliza <input type="file" multiple>. */
    public function files(string $key): array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !is_array($file['name'] ?? null)) {
            $single = $this->file($key);
            return $single ? [$single] : [];
        }
        $out = [];
        foreach (array_keys($file['name']) as $i) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => $file['name'][$i],
                'type' => $file['type'][$i],
                'tmp_name' => $file['tmp_name'][$i],
                'error' => $file['error'][$i],
                'size' => $file['size'][$i],
            ];
        }
        return $out;
    }

    public function ip(): string
    {
        // Não confiamos em X-Forwarded-For (pode ser forjado). Configure o proxy reverso se houver.
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isHttps(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (int) ($this->server['SERVER_PORT'] ?? 0) === 443;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }

    private static function clean(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        // remove caracteres de controle (exceto quebra de linha/tab)
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return trim($value);
    }
}
