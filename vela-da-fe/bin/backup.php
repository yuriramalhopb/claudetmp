<?php
declare(strict_types=1);

/** Backup do banco em storage/backups (php bin/backup.php). */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
$app = new App\Core\App($config, BASE_PATH);
$r = $app->backup()->run();
echo 'Backup criado: ' . $r['file'] . ' (' . round($r['size'] / 1024) . " KB)\n";
