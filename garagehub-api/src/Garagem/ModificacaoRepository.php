<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\Db;

final class ModificacaoRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Modificações de vários carros, agrupadas por veiculo_id, mais recentes primeiro.
     *
     * @param list<int> $veiculoIds
     * @return array<int, list<array>>
     */
    public function dosVeiculos(array $veiculoIds): array
    {
        if (!$veiculoIds) {
            return [];
        }
        $marcadores = implode(', ', array_fill(0, count($veiculoIds), '?'));
        $linhas = $this->db->todos(
            "SELECT * FROM modificacoes WHERE veiculo_id IN ({$marcadores})
             ORDER BY data_instalacao IS NULL, data_instalacao DESC, id DESC",
            array_values($veiculoIds),
        );

        $grupos = [];
        foreach ($linhas as $m) {
            $grupos[(int) $m['veiculo_id']][] = $m;
        }
        return $grupos;
    }

    /** Busca a modificação só se o carro dela pertencer ao usuário. */
    public function doDono(int $id, int $usuarioId): ?array
    {
        return $this->db->um(
            'SELECT m.* FROM modificacoes m JOIN veiculos v ON v.id = m.veiculo_id WHERE m.id = ? AND v.usuario_id = ?',
            [$id, $usuarioId],
        );
    }

    public function criar(int $veiculoId, array $campos): int
    {
        $agora = Db::agora();
        return $this->db->inserir('modificacoes', $campos + [
            'veiculo_id' => $veiculoId,
            'criado_em' => $agora,
            'atualizado_em' => $agora,
        ]);
    }

    public function atualizar(int $id, array $campos): void
    {
        $this->db->atualizar('modificacoes', $id, $campos + ['atualizado_em' => Db::agora()]);
    }

    public function excluir(int $id): void
    {
        $this->db->executar('DELETE FROM modificacoes WHERE id = ?', [$id]);
    }

    public function porId(int $id): ?array
    {
        return $this->db->um('SELECT * FROM modificacoes WHERE id = ?', [$id]);
    }
}
