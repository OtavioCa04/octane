<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\Db;

final class VeiculoRepository
{
    // A foto padrão do modelo vem da tabela fotos_modelo, quando existir.
    // Compara sem diferenciar maiúsculas: a FIPE escreve "Golf GTi", a tabela de fotos "Golf GTI".
    // A capa é a foto marcada como capa (ou, na falta, a primeira enviada).
    private const SELECT = 'SELECT v.*, f.arquivo_url AS foto_modelo_url,
            (SELECT vf.arquivo FROM veiculo_fotos vf WHERE vf.veiculo_id = v.id ORDER BY vf.capa DESC, vf.ordem, vf.id LIMIT 1) AS foto_capa
        FROM veiculos v
        LEFT JOIN fotos_modelo f ON LOWER(f.marca) = LOWER(v.marca) AND LOWER(f.modelo) = LOWER(v.modelo)';

    public function __construct(private readonly Db $db)
    {
    }

    public function doUsuario(int $usuarioId, bool $apenasVisiveis = false): array
    {
        $sql = self::SELECT . ' WHERE v.usuario_id = ?' . ($apenasVisiveis ? ' AND v.visivel = 1' : '');
        return $this->db->todos($sql . ' ORDER BY v.ordem, v.id', [$usuarioId]);
    }

    /** Busca o carro só se ele pertencer ao usuário. */
    public function doDono(int $id, int $usuarioId): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE v.id = ? AND v.usuario_id = ?', [$id, $usuarioId]);
    }

    /** @param list<int> $ids @return array<int, array> por id */
    public function porIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $marcadores = implode(', ', array_fill(0, count($ids), '?'));
        $porId = [];
        foreach ($this->db->todos(self::SELECT . " WHERE v.id IN ({$marcadores})", array_values($ids)) as $v) {
            $porId[(int) $v['id']] = $v;
        }
        return $porId;
    }

    public function porSlug(int $usuarioId, string $slug): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE v.usuario_id = ? AND v.slug = ?', [$usuarioId, $slug]);
    }

    public function criar(int $usuarioId, array $campos): int
    {
        $agora = Db::agora();
        return $this->db->inserir('veiculos', $campos + [
            'usuario_id' => $usuarioId,
            'slug' => $this->slugLivre($usuarioId, $campos['modelo']),
            'criado_em' => $agora,
            'atualizado_em' => $agora,
        ]);
    }

    // O slug não muda na edição, para não quebrar links já compartilhados
    public function atualizar(int $id, array $campos): void
    {
        $this->db->atualizar('veiculos', $id, $campos + ['atualizado_em' => Db::agora()]);
    }

    public function excluir(int $id): void
    {
        $this->db->executar('DELETE FROM veiculos WHERE id = ?', [$id]);
    }

    /** "Golf GTI" → golf-gti; se já existir na garagem: golf-gti-2, golf-gti-3... */
    private function slugLivre(int $usuarioId, string $modelo): string
    {
        $base = Slug::de($modelo) ?: 'carro';
        $existentes = array_column(
            $this->db->todos('SELECT slug FROM veiculos WHERE usuario_id = ? AND slug LIKE ?', [$usuarioId, "{$base}%"]),
            'slug',
        );
        $slug = $base;
        for ($n = 2; in_array($slug, $existentes, true); $n++) {
            $slug = "{$base}-{$n}";
        }
        return $slug;
    }
}
