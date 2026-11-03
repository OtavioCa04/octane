<?php

declare(strict_types=1);

namespace GarageHub\Core;

use PDO;

/**
 * Acesso ao banco com PDO e consultas preparadas.
 * A conexão só é aberta na primeira consulta, para que falhas de conexão
 * caiam no tratamento de erros da API.
 */
final class Db
{
    private ?PDO $pdo = null;

    public function __construct(
        public readonly string $driver,
        private readonly array $config,
    ) {
    }

    public static function doAmbiente(): self
    {
        return new self(Env::get('DB_DRIVER', 'mysql'), [
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::get('DB_PORT', '3306'),
            'database' => Env::get('DB_DATABASE', 'garagehub'),
            'username' => Env::get('DB_USERNAME', 'root'),
            'password' => Env::get('DB_PASSWORD', ''),
        ]);
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if ($this->driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . $this->config['database']);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
            );
            $pdo = new PDO($dsn, $this->config['username'], $this->config['password']);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return $this->pdo = $pdo;
    }

    public function executar(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function um(string $sql, array $params = []): ?array
    {
        $linha = $this->executar($sql, $params)->fetch();
        return $linha === false ? null : $linha;
    }

    public function todos(string $sql, array $params = []): array
    {
        return $this->executar($sql, $params)->fetchAll();
    }

    public function valor(string $sql, array $params = []): mixed
    {
        $valor = $this->executar($sql, $params)->fetchColumn();
        return $valor === false ? null : $valor;
    }

    /** Insere uma linha e devolve o id gerado. */
    public function inserir(string $tabela, array $dados): int
    {
        $colunas = array_keys($dados);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tabela,
            implode(', ', $colunas),
            implode(', ', array_map(fn ($c) => ":{$c}", $colunas)),
        );
        $this->executar($sql, $dados);
        return (int) $this->pdo()->lastInsertId();
    }

    public function atualizar(string $tabela, int $id, array $dados): void
    {
        $sets = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($dados)));
        $this->executar("UPDATE {$tabela} SET {$sets} WHERE id = :__id", $dados + ['__id' => $id]);
    }

    public static function agora(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
