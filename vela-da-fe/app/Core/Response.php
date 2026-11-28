<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        $this->headers = $headers + ['Content-Type' => 'text/html; charset=UTF-8'];
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /** Redireciona apenas para caminhos internos (evita open redirect). */
    public static function redirect(string $to, int $status = 302): self
    {
        if (!str_starts_with($to, '/') || str_starts_with($to, '//') || str_contains($to, '\\')) {
            $to = '/';
        }
        return new self('', $status, ['Location' => $to]);
    }

    public static function download(string $content, string $filename, string $contentType): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        return new self($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . str_replace(["\r", "\n"], '', $value));
            }
        }
        echo $this->body;
    }
}
