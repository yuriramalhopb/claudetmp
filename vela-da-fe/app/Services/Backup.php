<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * Backup do banco sem depender de mysqldump/sqlite3 (funciona em hospedagem compartilhada):
 *  - SQLite: cópia consistente do arquivo (VACUUM INTO) compactada
 *  - MySQL : dump SQL tabela por tabela, em blocos, compactado (.sql.gz)
 * Mantém os últimos N dias em storage/backups (fora da pasta pública).
 */
final class Backup
{
    private const CHUNK = 500;

    public function __construct(
        private readonly Database $db,
        private readonly string $dir,
        private readonly int $keepDays,
    ) {
    }

    /** @return array{file:string, size:int} */
    public function run(): array
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true)) {
            throw new RuntimeException('Não foi possível criar a pasta de backup: ' . $this->dir);
        }
        @chmod($this->dir, 0700);
        $stamp = date('Ymd-His');
        for ($i = 2; glob($this->dir . "/vela-{$stamp}.*") !== []; $i++) {
            $stamp = date('Ymd-His') . '-' . $i;
        }
        $file = $this->dir . "/vela-{$stamp}." . ($this->db->isSqlite() ? 'sqlite.gz' : 'sql.gz');
        $this->db->isSqlite() ? $this->dumpSqlite($file) : $this->dumpMysql($file);
        $this->rotate();
        return ['file' => $file, 'size' => (int) filesize($file)];
    }

    /** @return array<int, array{name:string, size:int, time:int}> mais novos primeiro */
    public function list(): array
    {
        $out = [];
        foreach (glob($this->dir . '/vela-*.gz') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /** Caminho seguro de um backup existente (ou null). */
    public function path(string $name): ?string
    {
        if (!preg_match('/^vela-[0-9-]+\.(sql|sqlite)\.gz$/', $name)) {
            return null;
        }
        $path = $this->dir . '/' . $name;
        return is_file($path) ? $path : null;
    }

    private function dumpSqlite(string $file): void
    {
        $tmp = $file . '.tmp';
        @unlink($tmp);
        $this->db->pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $tmp) . "'");
        $in = fopen($tmp, 'rb');
        $gz = gzopen($file, 'wb6');
        if ($in === false || $gz === false) {
            throw new RuntimeException('Falha ao compactar o backup.');
        }
        while (!feof($in)) {
            gzwrite($gz, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($gz);
        unlink($tmp);
    }

    private function dumpMysql(string $file): void
    {
        $gz = gzopen($file . '.part', 'wb6');
        if ($gz === false) {
            throw new RuntimeException('Não foi possível gravar ' . $file);
        }
        $pdo = $this->db->pdo();
        gzwrite($gz, '-- Backup Vela da Fé ' . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
                continue;
            }
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM)[1];
            gzwrite($gz, "\nDROP TABLE IF EXISTS `{$table}`;\n{$create};\n");
            $offset = 0;
            while (true) {
                $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT " . self::CHUNK . " OFFSET {$offset}")->fetchAll(\PDO::FETCH_NUM);
                if ($rows === []) {
                    break;
                }
                $values = array_map(fn ($row) => '(' . implode(',', array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    $row
                )) . ')', $rows);
                gzwrite($gz, "INSERT INTO `{$table}` VALUES " . implode(",\n", $values) . ";\n");
                $offset += self::CHUNK;
            }
        }
        gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        rename($file . '.part', $file);
    }

    private function rotate(): void
    {
        foreach (glob($this->dir . '/vela-*.gz') ?: [] as $f) {
            if (filemtime($f) < time() - $this->keepDays * 86400) {
                @unlink($f);
            }
        }
    }
}
