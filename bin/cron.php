<?php
declare(strict_types=1);

// Agendador único. Configure UMA linha no cron da hospedagem (a cada 5 minutos):
//   */5 * * * * php /caminho/do/site/bin/cron.php >> /caminho/do/site/storage/logs/cron.log 2>&1
//
// Tarefas: avisos de trilha nova, lembrete da véspera, notificações push, fila de
// e-mails, sincronização do Instagram, backup diário e limpezas (app/Services/Scheduler.php).
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';

// impede duas execuções simultâneas
$lock = fopen(BASE_PATH . '/storage/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('c') . " cron já em execução, saindo.\n";
    exit(0);
}

$app = new App\Core\App($config, BASE_PATH);
(new App\Services\Scheduler($app, static fn (string $m) => print(date('c') . ' ' . $m . "\n")))->runAll();
flock($lock, LOCK_UN);
