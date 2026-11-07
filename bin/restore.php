<?php
declare(strict_types=1);

/**
 * Restaura um backup. ATENÇÃO: substitui os dados atuais.
 * Uso:
 *   php bin/restore.php                         (lista os backups)
 *   php bin/restore.php ducks-AAAAMMDD-HHMMSS-banco.sql.gz [ducks-...-fotos.tar.gz]
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via linha de comando.\n");
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
$app = new App\Core\App($config, BASE_PATH);
$backup = $app->backup();

$files = array_slice($argv, 1);
$force = in_array('--sim', $files, true);
$files = array_values(array_filter($files, fn ($f) => $f !== '--sim'));

if (!$files) {
    echo "Backups disponíveis:\n";
    foreach ($backup->list() as $b) {
        printf("  %s  %6.1f MB  %s\n", date('d/m/Y H:i', $b['time']), $b['size'] / 1048576, $b['name']);
    }
    echo "\nUso: php bin/restore.php <arquivo-banco.sql.gz> [arquivo-fotos.tar.gz]\n";
    exit(0);
}

if (!$force) {
    echo "Isto vai SUBSTITUIR os dados atuais pelo backup. Antes, um backup de segurança do estado atual será criado.\nDigite RESTAURAR para continuar: ";
    if (trim((string) fgets(STDIN)) !== 'RESTAURAR') {
        exit("Cancelado.\n");
    }
}
$safety = $backup->run(false);
echo "✔ Backup de segurança do estado atual: " . basename($safety['db']) . "\n";

foreach ($files as $name) {
    $path = $backup->path(basename($name));
    if (str_contains($name, '-banco.sql.gz')) {
        $n = $backup->restoreDatabase($path);
        echo "✔ Banco restaurado ({$n} comandos).\n";
    } else {
        $n = $backup->restoreUploads($path);
        echo "✔ {$n} imagem(ns) restaurada(s).\n";
    }
}
