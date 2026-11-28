<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Camada fina sobre PDO com dois drivers: MySQL/MariaDB (produção) e SQLite (zero configuração).
 * TODA consulta usa prepared statements reais, então valores do usuário nunca são concatenados no SQL.
 * Datas são sempre calculadas em PHP (nada de NOW()/DATE_ADD), para o SQL ser igual nos dois bancos.
 */
final class Database
{
    private PDO $pdo;
    private string $driver;

    public function __construct(array $config)
    {
        $this->driver = ($config['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        if ($this->driver === 'sqlite') {
            $path = (string) $config['sqlite_path'];
            if ($path !== ':memory:' && !is_dir(dirname($path))) {
                mkdir(dirname($path), 0750, true);
            }
            $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            if ($path !== ':memory:') {
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            }
            return;
        }
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'";
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], (int) $config['port'], $config['name']);
        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        $this->pdo->exec("SET time_zone = '" . date('P') . "'");
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    /** Expressão de ordenação aleatória do banco em uso. */
    public function random(): string
    {
        return $this->isSqlite() ? 'RANDOM()' : 'RAND()';
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /** Insere um registro. Nomes de tabela/coluna vêm do código e são validados. */
    public function insert(string $table, array $data): int
    {
        $this->assertIdentifier($table);
        $columns = array_keys($data);
        array_walk($columns, fn ($c) => $this->assertIdentifier($c));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(fn ($c) => ':' . $c, $columns))
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $this->assertIdentifier($table);
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $this->assertIdentifier($column);
            $sets[] = "$column = :set_$column";
            $params["set_$column"] = $value;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return $this->execute($sql, $params + $whereParams);
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        $this->assertIdentifier($table);
        try {
            $this->pdo->query("SELECT 1 FROM $table LIMIT 1");
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Cria o schema base (database/<driver>/schema.sql) e aplica, em ordem, as migrações
     * pendentes de database/<driver>/migrations/*.sql (cada uma roda uma única vez).
     *
     * @return string[] migrações aplicadas nesta execução
     */
    public function migrate(string $schemaDir): array
    {
        $dir = $schemaDir . '/' . $this->driver;
        $file = $dir . '/schema.sql';
        if (!is_file($file)) {
            throw new RuntimeException('Schema não encontrado: ' . $file);
        }
        if (!$this->tableExists('users')) {
            $this->runSqlFile($file);
        }
        $this->ensureMigrationsTable();
        $done = array_flip($this->appliedMigrations());
        $applied = [];
        foreach ($this->migrationFiles($dir) as $version => $path) {
            if (isset($done[$version])) {
                continue;
            }
            $this->runSqlFile($path);
            $this->insert('schema_migrations', ['version' => $version, 'applied_at' => date('Y-m-d H:i:s')]);
            $applied[] = $version;
        }
        return $applied;
    }

    /** Verificação barata feita a cada requisição: há algo para migrar? */
    public function needsMigration(string $schemaDir): bool
    {
        if (!$this->tableExists('schema_migrations')) {
            return true;
        }
        $files = $this->migrationFiles($schemaDir . '/' . $this->driver);
        return count($files) > 0 && count(array_intersect(array_keys($files), $this->appliedMigrations())) < count($files);
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        )');
    }

    private function appliedMigrations(): array
    {
        return array_column($this->fetchAll('SELECT version FROM schema_migrations'), 'version');
    }

    /** @return array<string,string> versão => caminho, em ordem */
    private function migrationFiles(string $dir): array
    {
        $files = [];
        foreach (glob($dir . '/migrations/*.sql') ?: [] as $path) {
            $files[basename($path, '.sql')] = $path;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function runSqlFile(string $file): void
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    private function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            throw new RuntimeException('Identificador SQL inválido: ' . $name);
        }
    }
}
