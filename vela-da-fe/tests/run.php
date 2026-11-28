<?php
declare(strict_types=1);

/**
 * Executa os testes automáticos (unitários + ponta a ponta com servidor PHP real).
 *
 *   php tests/run.php            (todos)
 *   php tests/run.php Pix        (só classes/métodos que contêm "Pix")
 *
 * Por padrão usa um SQLite temporário. Para testar no MySQL (o nome do banco precisa terminar em _test):
 *   TEST_DB_DRIVER=mysql TEST_DB_HOST=127.0.0.1 TEST_DB_NAME=vela_test TEST_DB_USER=root TEST_DB_PASS=... php tests/run.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$base = dirname(__DIR__);
$filter = $argv[1] ?? '';
$driver = getenv('TEST_DB_DRIVER') ?: 'sqlite';
$dbName = getenv('TEST_DB_NAME') ?: 'vela_test';
$sqlite = sys_get_temp_dir() . '/vela-test-' . getmypid() . '.sqlite';
$backupDir = sys_get_temp_dir() . '/vela-test-backups-' . getmypid();

if ($driver === 'mysql' && !preg_match('/_test$/', $dbName)) {
    fwrite(STDERR, "Por segurança, o banco de testes precisa terminar em _test (atual: {$dbName}).\n");
    exit(1);
}

$port = (int) (getenv('TEST_PORT') ?: 8766);
$env = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_NAME' => 'Vela da Fé', 'APP_URL' => "http://127.0.0.1:{$port}",
    'DB_DRIVER' => $driver, 'DB_SQLITE_PATH' => $sqlite,
    'DB_HOST' => getenv('TEST_DB_HOST') ?: '127.0.0.1', 'DB_PORT' => getenv('TEST_DB_PORT') ?: '3306',
    'DB_NAME' => $dbName, 'DB_USER' => getenv('TEST_DB_USER') ?: 'root', 'DB_PASS' => getenv('TEST_DB_PASS') ?: '',
    'ADMIN_NAME' => 'Admin Teste', 'ADMIN_EMAIL' => 'admin@teste.local', 'ADMIN_PASSWORD' => 'Admin12345',
    'MAIL_DRIVER' => 'log', 'PIX_KEY' => 'pix@teste.local', 'PIX_NAME' => 'Paroquia Teste', 'PIX_CITY' => 'Atibaia',
    'LOGIN_MAX_ATTEMPTS' => '5', 'BACKUP_DIR' => $backupDir,
];
foreach ($env as $k => $v) {
    putenv("{$k}={$v}");
}

if ($driver === 'mysql') {
    try {
        $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        fwrite(STDERR, 'Não foi possível preparar o banco de testes: ' . $e->getMessage() . "\n");
        exit(1);
    }
} else {
    @unlink($sqlite);
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
if (is_dir($backupDir)) {
    array_map('unlink', glob($backupDir . '/*') ?: []);
    @rmdir($backupDir);
}
if ($driver === 'sqlite') {
    foreach (['', '-wal', '-shm'] as $suffix) {
        @unlink($sqlite . $suffix);
    }
} else {
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}

printf("\n%d teste(s), %d verificação(ões), %d falha(s) em %.1fs\n", $passed + $failed, $assertions, $failed, microtime(true) - $t0);
if ($failures) {
    echo "\nFalhas:\n - " . implode("\n - ", $failures) . "\n";
}
exit($failed > 0 ? 1 : 0);
