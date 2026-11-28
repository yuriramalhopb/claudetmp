<?php
declare(strict_types=1);

/**
 * Restaura um backup de storage/backups:  php bin/restore.php vela-20261127-030000.sql.gz
 * ATENÇÃO: substitui TODOS os dados atuais. Peça confirmação digitando "sim".
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = require dirname(__DIR__) . '/app/bootstrap.php';
$name = basename($argv[1] ?? '');
$dir = (string) $config->get('backup.dir', 'storage/backups');
$path = (str_starts_with($dir, '/') ? $dir : BASE_PATH . '/' . $dir) . '/' . $name;
if (!preg_match('/^vela-[0-9-]+\.(sql|sqlite)\.gz$/', $name) || !is_file($path)) {
    fwrite(STDERR, "Uso: php bin/restore.php <arquivo de storage/backups>\n");
    exit(1);
}
$driver = (string) $config->get('db.driver', 'sqlite');
if (($driver === 'sqlite') !== str_ends_with($name, '.sqlite.gz')) {
    fwrite(STDERR, "Este backup é de outro tipo de banco (o sistema usa {$driver}).\n");
    exit(1);
}
echo "Isto vai SUBSTITUIR todos os dados atuais por {$name}. Digite sim para continuar: ";
if (strtolower(trim((string) fgets(STDIN))) !== 'sim') {
    exit("Cancelado.\n");
}
if ($driver === 'sqlite') {
    $target = (string) $config->get('db.sqlite_path');
    foreach (['-wal', '-shm'] as $suffix) {
        @unlink($target . $suffix);
    }
    $in = gzopen($path, 'rb');
    $out = fopen($target, 'wb');
    while (!gzeof($in)) {
        fwrite($out, (string) gzread($in, 1 << 20));
    }
    gzclose($in);
    fclose($out);
} else {
    $c = $config->get('db');
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $c['name']), $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $in = gzopen($path, 'rb');
    $buffer = '';
    $count = 0;
    while (($line = gzgets($in)) !== false) {
        if (str_starts_with($line, '--') || trim($line) === '') {
            continue;
        }
        $buffer .= $line;
        if (preg_match('/;\s*$/', $line)) {
            $pdo->exec($buffer); // o dump grava uma instrução por linha terminada em ";"
            $buffer = '';
            $count++;
        }
    }
    gzclose($in);
    echo "{$count} instruções executadas.\n";
}
echo "Backup restaurado.\n";
