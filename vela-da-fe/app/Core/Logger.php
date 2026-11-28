<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public function __construct(private readonly string $dir)
    {
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function write(string $level, string $message, array $context = [], string $file = 'app'): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            str_replace(["\r", "\n"], ' ', $message),
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents($this->dir . '/' . $file . '-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
