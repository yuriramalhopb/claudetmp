<?php
declare(strict_types=1);

/**
 * Cria um backup agora (banco + fotos). Uso: php bin/backup.php [--sem-fotos]
 * O cron já faz isso todo dia no horário BACKUP_HOUR.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
$app = new App\Core\App($config, BASE_PATH);

$r = $app->backup()->run(!in_array('--sem-fotos', $argv, true));
$app->model(App\Models\Setting::class)->set('backup_last_ok', date('Y-m-d H:i:s'));
$app->model(App\Models\Setting::class)->set('backup_last_size', (string) $r['size']);
echo "✔ Banco: {$r['db']}\n";
if ($r['uploads']) {
    echo "✔ Fotos: {$r['uploads']}\n";
}
echo '  Tamanho total: ' . round($r['size'] / 1048576, 2) . " MB\n";
