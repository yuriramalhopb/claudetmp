<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renderizador de templates PHP com layout. Todo texto vindo de usuário deve
 * ser impresso com e() (htmlspecialchars) — ver app/helpers.php.
 */
final class View
{
    private array $shared = [];

    public function __construct(private readonly string $path)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile('partials/' . $template, $data);
    }

    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#', $template)) {
            throw new \RuntimeException('Nome de template inválido.');
        }
        $file = $this->path . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template não encontrado: ' . $template);
        }
        $vars = array_merge($this->shared, $data);
        $view = $this;
        ob_start();
        try {
            (static function (string $__file, array $__vars, View $view): void {
                extract($__vars, EXTR_SKIP);
                require $__file;
            })($file, $vars, $view);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
