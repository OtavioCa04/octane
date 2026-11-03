<?php

declare(strict_types=1);

namespace GarageHub\Social;

use GarageHub\Core\Db;

/**
 * O feed é calculado a partir do que já existe no banco, sem tabela de eventos:
 *   - carro adicionado à garagem
 *   - modificação registrada
 *   - fotos enviadas (agrupadas por carro e por dia)
 * Assim, o que é apagado ou escondido some do feed sozinho.
 *
 * Só entram carros visíveis de perfis públicos (os próprios carros entram sempre no "Seguindo").
 */
final class FeedRepository
{
    private const EVENTOS = "
        SELECT 'carro' AS tipo, v.id AS veiculo_id, v.id AS ref_id, v.criado_em AS data, 1 AS quantidade FROM veiculos v
        UNION ALL
        SELECT 'modificacao', m.veiculo_id, m.id, m.criado_em, 1 FROM modificacoes m
        UNION ALL
        SELECT 'fotos', f.veiculo_id, MAX(f.id), MAX(f.criado_em), COUNT(*) FROM veiculo_fotos f
        GROUP BY f.veiculo_id, DATE(f.criado_em)";

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Eventos de quem o usuário segue (pessoas e carros), mais os dele mesmo.
     *
     * @return list<array{tipo: string, veiculo_id: int, ref_id: int, data: string, quantidade: int}>
     */
    public function seguindo(int $usuarioId, int $limite, int $deslocamento): array
    {
        $filtro = '(u.id = ? OR (u.perfil_privado = 0 AND (
                u.id IN (SELECT seguido_id FROM seguidores_usuarios WHERE seguidor_id = ?)
                OR v.id IN (SELECT veiculo_id FROM seguidores_veiculos WHERE usuario_id = ?))))';
        return $this->buscar($filtro, [$usuarioId, $usuarioId, $usuarioId], $limite, $deslocamento);
    }

    /** Toda a atividade pública, para descobrir garagens novas; opcionalmente só de uma cidade ou estado. */
    public function explorar(int $limite, int $deslocamento, ?int $cidadeId = null, ?string $uf = null): array
    {
        $filtro = 'u.perfil_privado = 0';
        $params = [];
        if ($cidadeId !== null) {
            $filtro .= ' AND u.cidade_id = ?';
            $params[] = $cidadeId;
        } elseif ($uf !== null) {
            $filtro .= ' AND c.uf = ?';
            $params[] = $uf;
        }
        return $this->buscar($filtro, $params, $limite, $deslocamento);
    }

    private function buscar(string $filtro, array $params, int $limite, int $deslocamento): array
    {
        return $this->db->todos(
            'SELECT e.tipo, e.veiculo_id, e.ref_id, e.data, e.quantidade FROM (' . self::EVENTOS . ') e
             JOIN veiculos v ON v.id = e.veiculo_id
             JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN cidades c ON c.id = u.cidade_id
             WHERE v.visivel = 1 AND ' . $filtro . '
             ORDER BY e.data DESC, e.ref_id DESC
             LIMIT ? OFFSET ?',
            [...$params, $limite, $deslocamento],
        );
    }

    /** As fotos de um grupo do feed: as enviadas naquele dia para aquele carro (até 4). */
    public function fotosDoDia(int $veiculoId, string $data): array
    {
        return $this->db->todos(
            'SELECT * FROM veiculo_fotos WHERE veiculo_id = ? AND DATE(criado_em) = DATE(?) ORDER BY id DESC LIMIT 4',
            [$veiculoId, $data],
        );
    }

    /** @return array<int, array> donos dos carros, por id do usuário */
    public function donos(array $usuarioIds): array
    {
        if (!$usuarioIds) {
            return [];
        }
        $marcadores = implode(', ', array_fill(0, count($usuarioIds), '?'));
        $porId = [];
        foreach ($this->db->todos("SELECT id, username, nome, avatar FROM usuarios WHERE id IN ({$marcadores})", array_values($usuarioIds)) as $u) {
            $porId[(int) $u['id']] = $u;
        }
        return $porId;
    }

    /** @return array<int, array> modificações por id */
    public function modificacoes(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $marcadores = implode(', ', array_fill(0, count($ids), '?'));
        $porId = [];
        foreach ($this->db->todos("SELECT * FROM modificacoes WHERE id IN ({$marcadores})", array_values($ids)) as $m) {
            $porId[(int) $m['id']] = $m;
        }
        return $porId;
    }

    /**
     * Garagens públicas para seguir: com pelo menos um carro visível, que o usuário ainda não segue.
     * Primeiro as da mesma cidade, depois as do mesmo estado, depois as mais ativas.
     */
    public function sugestoes(int $usuarioId, ?int $cidadeId, ?string $uf, int $limite): array
    {
        return $this->db->todos(
            'SELECT u.id, u.username, u.nome, u.avatar, u.cidade, c.nome AS cidade_nome, c.uf AS cidade_uf,
                    COUNT(v.id) AS carros, MAX(v.atualizado_em) AS ultima_atividade,
                    MAX(CASE WHEN u.cidade_id = ? THEN 1 ELSE 0 END) AS mesma_cidade,
                    MAX(CASE WHEN c.uf = ? THEN 1 ELSE 0 END) AS mesmo_estado
             FROM usuarios u
             JOIN veiculos v ON v.usuario_id = u.id AND v.visivel = 1
             LEFT JOIN cidades c ON c.id = u.cidade_id
             WHERE u.perfil_privado = 0 AND u.id <> ?
               AND u.id NOT IN (SELECT seguido_id FROM seguidores_usuarios WHERE seguidor_id = ?)
             GROUP BY u.id, u.username, u.nome, u.avatar, u.cidade, c.nome, c.uf
             ORDER BY mesma_cidade DESC, mesmo_estado DESC, ultima_atividade DESC
             LIMIT ?',
            [$cidadeId ?? 0, $uf ?? '', $usuarioId, $usuarioId, $limite],
        );
    }

    /** O carro de destaque de cada garagem sugerida (o primeiro na ordem do dono). */
    public function carroDestaque(int $usuarioId): ?int
    {
        $id = $this->db->valor('SELECT id FROM veiculos WHERE usuario_id = ? AND visivel = 1 ORDER BY ordem, id LIMIT 1', [$usuarioId]);
        return $id === null ? null : (int) $id;
    }
}
