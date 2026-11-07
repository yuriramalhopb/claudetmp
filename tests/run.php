<?php
declare(strict_types=1);

/**
 * Executa os testes automáticos.
 *
 *   php tests/run.php              (todos)
 *   php tests/run.php Totp         (só classes/métodos que contêm "Totp")
 *
 * Usa um banco MySQL SEPARADO (o nome precisa terminar em _test), que é apagado e
 * recriado a cada execução. Configure por variáveis de ambiente:
 *   TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3306 TEST_DB_NAME=ducks_test TEST_DB_USER=root TEST_DB_PASS=...
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$base = dirname(__DIR__);
$filter = $argv[1] ?? '';

$dbName = getenv('TEST_DB_NAME') ?: 'ducks_test';
if (!preg_match('/_test$/', $dbName)) {
    fwrite(STDERR, "Por segurança, o banco de testes precisa terminar em _test (atual: {$dbName}).\n");
    exit(1);
}

// ---- ambiente de teste (nunca lê o .env) ----
require $base . '/app/Core/Crypto.php';
require $base . '/app/Services/WebPush.php';
$port = (int) (getenv('TEST_PORT') ?: 8765);
$env = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_NAME' => 'Ducks Offroad',
    'APP_URL' => "http://127.0.0.1:{$port}",
    'APP_KEY' => App\Core\Crypto::generateKey(),
    'DB_HOST' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('TEST_DB_PORT') ?: '3306',
    'DB_NAME' => $dbName,
    'DB_USER' => getenv('TEST_DB_USER') ?: 'root',
    'DB_PASS' => getenv('TEST_DB_PASS') ?: '',
    'ADMIN_NAME' => 'Admin Teste',
    'ADMIN_EMAIL' => 'admin@teste.local',
    'ADMIN_PASSWORD' => 'Admin12345',
    'MAIL_DRIVER' => 'log',
    'VAPID_PRIVATE_KEY' => App\Services\WebPush::generateVapidKeys()['private_b64'],
    'PIX_KEY' => 'pix@teste.local',
    'PIX_NAME' => 'Ducks Teste',
    'PIX_CITY' => 'Atibaia',
    'INSTAGRAM_SYNC_MINUTES' => '0',
    'LOGIN_MAX_ATTEMPTS' => '5',
    'BACKUP_DIR' => sys_get_temp_dir() . '/ducks-test-backups-' . getmypid(),
];
foreach ($env as $k => $v) {
    putenv("{$k}={$v}");
}

// ---- banco limpo ----
try {
    $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    fwrite(STDERR, 'Não foi possível preparar o banco de testes: ' . $e->getMessage() . "\n");
    exit(1);
}

$config = require $base . '/app/bootstrap.php';
spl_autoload_register(static function (string $class) use ($base): void {
    if (str_starts_with($class, 'Tests\\')) {
        $file = $base . '/tests/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require $base . '/tests/TestCase.php';

// arquivos existentes antes dos testes (para apagar só o que os testes criarem)
$watch = [$base . '/public/uploads', $base . '/storage/private'];
$snapshot = static function () use ($watch): array {
    $files = [];
    foreach ($watch as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            $files[$f->getPathname()] = true;
        }
    }
    return $files;
};
$before = $snapshot();

// ---- servidor HTTP para os testes de ponta a ponta ----
$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $base . '/public', $base . '/public/index.php'],
    [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $base,
    array_merge(getenv(), $env)
);
$ready = false;
for ($i = 0; $i < 50 && !$ready; $i++) {
    usleep(100000);
    $ready = @file_get_contents("http://127.0.0.1:{$port}/robots.txt") !== false;
}
if (!$ready) {
    fwrite(STDERR, "Servidor de testes não subiu na porta {$port}.\n");
    exit(1);
}
Tests\Feature\FeatureTestCase::$base = "http://127.0.0.1:{$port}";
Tests\Feature\FeatureTestCase::$app = new App\Core\App($config, BASE_PATH);

// ---- execução ----
$files = array_merge(glob($base . '/tests/Unit/*Test.php') ?: [], glob($base . '/tests/Feature/*Test.php') ?: []);
$passed = $failed = $assertions = 0;
$failures = [];
$t0 = microtime(true);
foreach ($files as $file) {
    $class = 'Tests\\' . basename(dirname($file)) . '\\' . basename($file, '.php');
    require_once $file;
    $methods = array_filter(get_class_methods($class), fn ($m) => str_starts_with($m, 'test'));
    $printedClass = false;
    foreach ($methods as $method) {
        if ($filter !== '' && stripos($class . '::' . $method, $filter) === false) {
            continue;
        }
        if (!$printedClass) {
            echo "\n" . basename(dirname($file)) . ' / ' . basename($file, 'Test.php') . "\n";
            $printedClass = true;
        }
        $test = new $class();
        try {
            $test->setUp();
            $test->$method();
            $test->tearDown();
            $passed++;
            echo "  \033[32m✔\033[0m {$method}\n";
        } catch (Throwable $e) {
            $failed++;
            $where = $e instanceof Tests\AssertionFailed ? '' : ' (' . get_class($e) . ' em ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            $failures[] = "{$class}::{$method}: " . $e->getMessage() . $where;
            echo "  \033[31m✘ {$method}\033[0m — " . $e->getMessage() . $where . "\n";
        }
        $assertions += $test->assertions;
    }
}

// ---- limpeza ----
proc_terminate($server);
foreach (array_keys(array_diff_key($snapshot(), $before)) as $f) {
    @unlink($f);
}
if (is_dir($env['BACKUP_DIR'])) {
    array_map('unlink', glob($env['BACKUP_DIR'] . '/*') ?: []);
    @rmdir($env['BACKUP_DIR']);
}
$pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");

printf("\n%d teste(s), %d verificação(ões), %d falha(s) em %.1fs\n", $passed + $failed, $assertions, $failed, microtime(true) - $t0);
if ($failures) {
    echo "\nFalhas:\n - " . implode("\n - ", $failures) . "\n";
}
exit($failed > 0 ? 1 : 0);
