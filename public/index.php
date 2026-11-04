<?php
declare(strict_types=1);

// Servidor embutido do PHP (desenvolvimento): entrega arquivos estáticos diretamente.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = realpath(__DIR__ . $path);
    if ($file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)
        && !preg_match('/\.(php|phtml|phar|htaccess)$/i', $file)) {
        return false;
    }
}

$config = require dirname(__DIR__) . '/app/bootstrap.php';

$app = new App\Core\App($config, BASE_PATH);
$router = require BASE_PATH . '/app/routes.php';
$app->run($router);
