<?php

declare(strict_types=1);

namespace GarageHub\Local;

use GarageHub\Core\Db;
use GarageHub\Core\Texto;

final class CidadeRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    /** "São José dos Campos" → "sao jose dos campos" */
    public static function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Texto::semAcento($texto)));
    }

    /** Busca por nome, ignorando acentos: primeiro as que começam com o termo. */
    public function buscar(string $termo, ?string $uf, int $limite): array
    {
        $normal = self::normalizar($termo);
        $escapado = Texto::paraLike($normal);
        $sql = "SELECT id, nome, uf FROM cidades WHERE busca LIKE ? ESCAPE '!'";
        $params = ["%{$escapado}%"];
        if ($uf) {
            $sql .= ' AND uf = ?';
            $params[] = strtoupper($uf);
        }
        $sql .= " ORDER BY CASE WHEN busca LIKE ? ESCAPE '!' THEN 0 ELSE 1 END, LENGTH(nome), nome LIMIT ?";
        return $this->db->todos($sql, [...$params, "{$escapado}%", $limite]);
    }

    public function porId(int $id): ?array
    {
        return $this->db->um('SELECT id, nome, uf FROM cidades WHERE id = ?', [$id]);
    }

    public function porNomeUf(string $nome, string $uf): ?array
    {
        return $this->db->um('SELECT id, nome, uf FROM cidades WHERE busca = ? AND uf = ?', [self::normalizar($nome), strtoupper($uf)]);
    }

    /** Carrega a lista do IBGE. Pode rodar de novo: só insere o que falta. */
    public function importar(array $cidades): int
    {
        $existentes = array_flip(array_map('intval', array_column($this->db->todos('SELECT id FROM cidades'), 'id')));
        $novas = 0;
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO cidades (id, nome, uf, busca) VALUES (?, ?, ?, ?)');
        foreach ($cidades as [$id, $nome, $uf]) {
            if (!isset($existentes[$id])) {
                $stmt->execute([$id, $nome, $uf, self::normalizar($nome)]);
                $novas++;
            }
        }
        $pdo->commit();
        return $novas;
    }

    public static function formatar(?string $nome, ?string $uf): ?string
    {
        return $nome ? "{$nome}, {$uf}" : null;
    }
}
