<?php
declare(strict_types=1);

/**
 * Sincroniza as publicações do Instagram. Agende no cron, ex.:
 *   0 * * * * php /var/www/ducks-offroad/bin/instagram-sync.php >> /var/www/ducks-offroad/storage/logs/cron.log 2>&1
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}

$config = require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Services\ImageUploader;
use App\Services\InstagramService;

$db = new Database($config->get('db'), BASE_PATH);
$service = new InstagramService(
    new InstagramPost($db),
    new Setting($db),
    new ImageUploader(BASE_PATH . '/public/uploads', (int) $config->get('security.upload_max_mb', 8) * 1048576),
    new Logger(BASE_PATH . '/storage/logs'),
    (string) $config->get('instagram.token', ''),
    (int) $config->get('instagram.sync_minutes', 60)
);

if (!$service->isConfigured()) {
    echo date('c') . " Instagram não configurado.\n";
    exit(0);
}
try {
    $n = $service->sync();
    echo date('c') . " {$n} post(s) sincronizado(s).\n";
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
// limpeza de registros antigos do limitador de tentativas
(new App\Core\RateLimiter($db))->purgeExpired();
