<?php
declare(strict_types=1);

/**
 * Rotinas automáticas. Agende no cron do servidor, a cada 5 minutos:
 *   *\/5 * * * *  php /caminho/do/site/bin/cron.php >> /dev/null 2>&1
 * (envia e-mails, avisa sobre velas que vão se apagar, cancela Pix vencidos, limpa dados antigos e
 *  faz o backup do banco uma vez por dia).
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';

$lock = fopen(BASE_PATH . '/storage/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // já está rodando
}
$app = new App\Core\App($config, BASE_PATH);
$scheduler = new App\Services\Scheduler($app);
$daily = $scheduler->dailyIsDue();
$report = $daily ? $scheduler->runDaily() : $scheduler->runLight();
if ($daily) {
    try {
        $app->backup()->run();
    } catch (Throwable $e) {
        $app->logger->error('Backup diário: ' . $e->getMessage());
    }
}
echo date('c') . ' ' . json_encode($report) . "\n";
