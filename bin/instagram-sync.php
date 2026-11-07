<?php
declare(strict_types=1);

/**
 * Sincroniza o Instagram agora (o bin/cron.php já faz isso automaticamente).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
$app = new App\Core\App($config, BASE_PATH);
if (!$app->instagram()->isConfigured()) {
    echo date('c') . " Instagram não configurado.\n";
    exit(0);
}
try {
    echo date('c') . ' ' . $app->instagram()->sync() . " post(s) sincronizado(s).\n";
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
