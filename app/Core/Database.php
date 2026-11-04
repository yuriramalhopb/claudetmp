<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Camada fina sobre PDO. TODA consulta usa prepared statements reais
 * (emulação desligada), então valores do usuário nunca são concatenados no SQL.
 */
final class Database
{
    private PDO $pdo;
    private string $driver;

    public function __construct(array $config, string $basePath)
    {
        $this->driver = $config['driver'] ?? 'sqlite';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if ($this->driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                (int) $config['port'],
                $config['name']
            );
            $this->pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
            $this->pdo->exec("SET time_zone = '" . date('P') . "'");
        } elseif ($this->driver === 'sqlite') {
            $path = $config['path'];
            if (!str_starts_with($path, '/')) {
                $path = $basePath . '/' . $path;
            }
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            throw new RuntimeException('Driver de banco não suportado: ' . $this->driver);
        }
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
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

    /** Executa o arquivo de schema correspondente ao driver (idempotente). */
    public function migrate(string $schemaDir): void
    {
        $file = $schemaDir . '/schema.' . $this->driver . '.sql';
        if (!is_file($file)) {
            throw new RuntimeException('Schema não encontrado: ' . $file);
        }
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $statement) {
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
