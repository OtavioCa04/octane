<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\Db;

final class FotoRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Fotos de vários carros, agrupadas por veiculo_id: a capa primeiro, depois na ordem de envio.
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
        $grupos = [];
        foreach ($this->db->todos(
            "SELECT * FROM veiculo_fotos WHERE veiculo_id IN ({$marcadores}) ORDER BY capa DESC, ordem, id",
            array_values($veiculoIds),
        ) as $f) {
            $grupos[(int) $f['veiculo_id']][] = $f;
        }
        return $grupos;
    }

    /** Busca a foto só se o carro dela pertencer ao usuário. */
    public function doDono(int $id, int $usuarioId): ?array
    {
        return $this->db->um(
            'SELECT f.* FROM veiculo_fotos f JOIN veiculos v ON v.id = f.veiculo_id WHERE f.id = ? AND v.usuario_id = ?',
            [$id, $usuarioId],
        );
    }

    public function porId(int $id): ?array
    {
        return $this->db->um('SELECT * FROM veiculo_fotos WHERE id = ?', [$id]);
    }

    public function contar(int $veiculoId): int
    {
        return (int) $this->db->valor('SELECT COUNT(*) FROM veiculo_fotos WHERE veiculo_id = ?', [$veiculoId]);
    }

    public function criar(int $veiculoId, array $dados): int
    {
        // A primeira foto do carro vira a capa
        $primeira = $this->contar($veiculoId) === 0;
        $ordem = (int) $this->db->valor('SELECT COALESCE(MAX(ordem), 0) + 1 FROM veiculo_fotos WHERE veiculo_id = ?', [$veiculoId]);
        return $this->db->inserir('veiculo_fotos', $dados + [
            'veiculo_id' => $veiculoId,
            'capa' => $primeira ? 1 : 0,
            'ordem' => $ordem,
            'criado_em' => Db::agora(),
        ]);
    }

    public function definirCapa(int $veiculoId, int $fotoId): void
    {
        $this->db->executar('UPDATE veiculo_fotos SET capa = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE veiculo_id = ?', [$fotoId, $veiculoId]);
    }

    /** Exclui a foto; se era a capa, a mais antiga que sobrou vira a capa. */
    public function excluir(array $foto): void
    {
        $this->db->executar('DELETE FROM veiculo_fotos WHERE id = ?', [$foto['id']]);
        if ($foto['capa']) {
            $proxima = $this->db->valor('SELECT id FROM veiculo_fotos WHERE veiculo_id = ? ORDER BY ordem, id LIMIT 1', [$foto['veiculo_id']]);
            if ($proxima !== null) {
                $this->definirCapa((int) $foto['veiculo_id'], (int) $proxima);
            }
        }
    }

    /** @return list<string> arquivos das fotos do carro, para apagar do disco junto com ele */
    public function arquivosDoVeiculo(int $veiculoId): array
    {
        return array_column($this->db->todos('SELECT arquivo FROM veiculo_fotos WHERE veiculo_id = ?', [$veiculoId]), 'arquivo');
    }
}
