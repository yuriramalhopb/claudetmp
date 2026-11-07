<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Este sistema requer PHP 8.1 ou superior.');
}

// Autoloader PSR-4 (App\ => app/)
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

// nos testes automáticos a configuração vem só das variáveis de ambiente
if (getenv('APP_ENV') !== 'testing') {
    Env::load(BASE_PATH . '/.env');
}
$config = new Config(require BASE_PATH . '/app/config.php');

date_default_timezone_set((string) $config->get('app.timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

// Nunca exibe erros na tela em produção; tudo vai para o log
error_reporting(E_ALL);
ini_set('display_errors', $config->get('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');
ini_set('expose_php', '0');

return $config;
