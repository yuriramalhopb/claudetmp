<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Roteador simples: rotas com parâmetros {id} (somente dígitos) e {slug}.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:array, middleware:array}> */
    private array $routes = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;
        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $callback($this);
        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $path = '/' . trim($this->groupPrefix . $path, '/');
        $regex = preg_replace(
            ['/\{id\}/', '/\{(\w+)\}/'],
            ['(?P<id>\d{1,10})', '(?P<$1>[a-zA-Z0-9_-]{1,100})'],
            str_replace('.', '\.', $path)
        );
        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /** @return array{handler:array, params:array, middleware:array} */
    public function match(string $method, string $path): array
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['handler' => $route['handler'], 'params' => $params, 'middleware' => $route['middleware']];
        }
        throw new HttpException($allowed ? 405 : 404);
    }
}
