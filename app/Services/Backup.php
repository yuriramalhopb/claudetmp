<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * Backup completo sem depender do mysqldump (funciona em hospedagem compartilhada):
 *  - banco: dump SQL compactado (.sql.gz), tabela por tabela, em blocos
 *  - fotos/banners: public/uploads compactado (.tar.gz)
 *  - mantém os últimos N dias; arquivos ficam em storage/backups (fora da web)
 */
final class Backup
{
    private const CHUNK = 500;

    public function __construct(
        private readonly Database $db,
        private readonly string $dir,
        private readonly string $uploadsDir,
        private readonly int $keepDays,
    ) {
    }

    /** @return array{db:string, uploads:?string, size:int} */
    public function run(bool $withUploads = true): array
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true)) {
            throw new RuntimeException('Não foi possível criar a pasta de backup: ' . $this->dir);
        }
        @chmod($this->dir, 0700);
        // nome único mesmo se dois backups ocorrerem no mesmo segundo
        $stamp = date('Ymd-His');
        for ($i = 2; glob($this->dir . "/ducks-{$stamp}-*") !== []; $i++) {
            $stamp = date('Ymd-His') . '-' . $i;
        }
        $dbFile = $this->dir . "/ducks-{$stamp}-banco.sql.gz";
        $this->dumpDatabase($dbFile);
        $uploads = $withUploads ? $this->archiveUploads($this->dir . "/ducks-{$stamp}-fotos.tar") : null;
        $this->rotate();
        return ['db' => $dbFile, 'uploads' => $uploads, 'size' => (int) filesize($dbFile) + ($uploads ? (int) filesize($uploads) : 0)];
    }

    public function dumpDatabase(string $file): void
    {
        $gz = gzopen($file . '.part', 'wb6');
        if ($gz === false) {
            throw new RuntimeException('Não foi possível gravar ' . $file);
        }
        $pdo = $this->db->pdo();
        gzwrite($gz, "-- Backup Ducks Offroad " . date('c') . "\n-- Restaure com: php bin/restore.php " . basename($file) . "\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(\PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
                continue;
            }
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM)[1];
            gzwrite($gz, "\nDROP TABLE IF EXISTS `{$table}`;\n{$create};\n");
            $offset = 0;
            do {
                $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT " . self::CHUNK . " OFFSET {$offset}")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $values = array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row);
                    // PDO::quote (MySQL) escapa \n e \r: cada INSERT ocupa exatamente uma linha
                    gzwrite($gz, "INSERT INTO `{$table}` (`" . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', $values) . ");\n");
                }
                $offset += self::CHUNK;
            } while (count($rows) === self::CHUNK);
        }
        gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n-- fim\n");
        gzclose($gz);
        rename($file . '.part', $file);
        @chmod($file, 0600);
    }

    public function archiveUploads(string $tarFile): ?string
    {
        if (!is_dir($this->uploadsDir)) {
            return null;
        }
        $tar = new \PharData($tarFile);
        $tar->buildFromDirectory($this->uploadsDir, '/\.jpg$/');
        $tar->compress(\Phar::GZ);
        unset($tar);
        @unlink($tarFile);
        $gz = $tarFile . '.gz';
        @chmod($gz, 0600);
        return $gz;
    }

    /** Restaura um dump .sql.gz gerado por este serviço. */
    public function restoreDatabase(string $file): int
    {
        $gz = gzopen($file, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Arquivo não encontrado: ' . $file);
        }
        $pdo = $this->db->pdo();
        $statement = '';
        $count = 0;
        while (($line = gzgets($gz)) !== false) {
            if ($statement === '' && (str_starts_with($line, '--') || trim($line) === '')) {
                continue;
            }
            $statement .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                $pdo->exec($statement);
                $statement = '';
                $count++;
            }
        }
        gzclose($gz);
        return $count;
    }

    public function restoreUploads(string $tarGz): int
    {
        $tar = new \PharData($tarGz);
        $n = 0;
        foreach (new \RecursiveIteratorIterator($tar) as $entry) {
            $rel = substr($entry->getPathname(), strlen('phar://' . realpath($tarGz)) + 1);
            // só aceita caminhos esperados (evita path traversal)
            if (!preg_match('#^[a-z]+/(thumbs/)?[a-f0-9]{32}\.jpg$#', $rel)) {
                continue;
            }
            $dest = $this->uploadsDir . '/' . $rel;
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0755, true);
            }
            copy($entry->getPathname(), $dest);
            $n++;
        }
        return $n;
    }

    /** @return array<int, array{name:string, size:int, time:int}> */
    public function list(): array
    {
        $out = [];
        foreach (glob($this->dir . '/ducks-*.gz') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    public function rotate(): int
    {
        $limit = time() - $this->keepDays * 86400;
        $n = 0;
        foreach (glob($this->dir . '/ducks-*.gz') ?: [] as $f) {
            if (filemtime($f) < $limit && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    public function path(string $name): string
    {
        if (!preg_match('/^ducks-\d{8}-\d{6}(-\d{1,3})?-(banco\.sql|fotos\.tar)\.gz$/', $name)) {
            throw new RuntimeException('Nome de backup inválido.');
        }
        return $this->dir . '/' . $name;
    }
}
