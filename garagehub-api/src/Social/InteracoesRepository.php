<?php

declare(strict_types=1);

namespace GarageHub\Social;

use GarageHub\Core\Db;

/** Curtidas e comentários em carros ('veiculo') e modificações ('modificacao'). */
final class InteracoesRepository
{
    public const TIPOS = ['veiculo', 'modificacao'];

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * O alvo só aceita interação se estiver visível para quem pede:
     * carro visível de perfil público, ou qualquer carro do próprio usuário.
     *
     * @return array{dono_id: int, veiculo_id: int}|null
     */
    public function alvoVisivel(string $tipo, int $id, ?int $eu): ?array
    {
        $sql = $tipo === 'veiculo'
            ? 'SELECT u.id AS dono_id, v.id AS veiculo_id, v.visivel, u.perfil_privado
               FROM veiculos v JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ?'
            : 'SELECT u.id AS dono_id, v.id AS veiculo_id, v.visivel, u.perfil_privado
               FROM modificacoes m JOIN veiculos v ON v.id = m.veiculo_id JOIN usuarios u ON u.id = v.usuario_id WHERE m.id = ?';
        $alvo = $this->db->um($sql, [$id]);
        if ($alvo === null) {
            return null;
        }
        $publico = $alvo['visivel'] && !$alvo['perfil_privado'];
        if (!$publico && (int) $alvo['dono_id'] !== $eu) {
            return null;
        }
        return ['dono_id' => (int) $alvo['dono_id'], 'veiculo_id' => (int) $alvo['veiculo_id']];
    }

    // --- Curtidas ---

    public function curtir(int $usuarioId, string $tipo, int $id): void
    {
        if (!$this->curtiu($usuarioId, $tipo, $id)) {
            $this->db->executar(
                'INSERT INTO curtidas (usuario_id, alvo_tipo, alvo_id, criado_em) VALUES (?, ?, ?, ?)',
                [$usuarioId, $tipo, $id, Db::agora()],
            );
        }
    }

    public function descurtir(int $usuarioId, string $tipo, int $id): void
    {
        $this->db->executar('DELETE FROM curtidas WHERE usuario_id = ? AND alvo_tipo = ? AND alvo_id = ?', [$usuarioId, $tipo, $id]);
    }

    private function curtiu(int $usuarioId, string $tipo, int $id): bool
    {
        return (bool) $this->db->valor(
            'SELECT 1 FROM curtidas WHERE usuario_id = ? AND alvo_tipo = ? AND alvo_id = ?',
            [$usuarioId, $tipo, $id],
        );
    }

    /**
     * Curtidas, comentários e "eu curti" de vários alvos do mesmo tipo, numa ida ao banco por informação.
     *
     * @param list<int> $ids
     * @return array<int, array{curtidas: int, curtido: bool, comentarios: int}>
     */
    public function contagens(string $tipo, array $ids, ?int $eu): array
    {
        $resultado = [];
        foreach ($ids as $id) {
            $resultado[$id] = ['curtidas' => 0, 'curtido' => false, 'comentarios' => 0];
        }
        if (!$ids) {
            return $resultado;
        }
        $marcadores = implode(', ', array_fill(0, count($ids), '?'));
        $params = [$tipo, ...array_values($ids)];

        foreach ($this->db->todos(
            "SELECT alvo_id, COUNT(*) AS n FROM curtidas WHERE alvo_tipo = ? AND alvo_id IN ({$marcadores}) GROUP BY alvo_id",
            $params,
        ) as $l) {
            $resultado[(int) $l['alvo_id']]['curtidas'] = (int) $l['n'];
        }
        foreach ($this->db->todos(
            "SELECT alvo_id, COUNT(*) AS n FROM comentarios WHERE alvo_tipo = ? AND alvo_id IN ({$marcadores}) GROUP BY alvo_id",
            $params,
        ) as $l) {
            $resultado[(int) $l['alvo_id']]['comentarios'] = (int) $l['n'];
        }
        if ($eu !== null) {
            foreach ($this->db->todos(
                "SELECT alvo_id FROM curtidas WHERE alvo_tipo = ? AND alvo_id IN ({$marcadores}) AND usuario_id = ?",
                [...$params, $eu],
            ) as $l) {
                $resultado[(int) $l['alvo_id']]['curtido'] = true;
            }
        }
        return $resultado;
    }

    // --- Comentários ---

    /** Comentários de um alvo, dos mais antigos para os mais novos (como uma conversa). */
    public function comentarios(string $tipo, int $id, int $limite, int $deslocamento): array
    {
        return $this->db->todos(
            'SELECT c.id, c.texto, c.criado_em, c.usuario_id, u.username, u.nome, u.avatar
             FROM comentarios c JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.alvo_tipo = ? AND c.alvo_id = ?
             ORDER BY c.criado_em, c.id
             LIMIT ? OFFSET ?',
            [$tipo, $id, $limite, $deslocamento],
        );
    }

    public function totalComentarios(string $tipo, int $id): int
    {
        return (int) $this->db->valor('SELECT COUNT(*) FROM comentarios WHERE alvo_tipo = ? AND alvo_id = ?', [$tipo, $id]);
    }

    public function comentar(int $usuarioId, string $tipo, int $id, string $texto): int
    {
        return $this->db->inserir('comentarios', [
            'usuario_id' => $usuarioId,
            'alvo_tipo' => $tipo,
            'alvo_id' => $id,
            'texto' => $texto,
            'criado_em' => Db::agora(),
        ]);
    }

    public function comentario(int $id): ?array
    {
        return $this->db->um(
            'SELECT c.id, c.texto, c.criado_em, c.usuario_id, c.alvo_tipo, c.alvo_id, u.username, u.nome, u.avatar
             FROM comentarios c JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = ?',
            [$id],
        );
    }

    public function excluirComentario(int $id): void
    {
        $this->db->executar('DELETE FROM comentarios WHERE id = ?', [$id]);
    }

    /** Quantos comentários o usuário fez nos últimos N segundos (limite contra spam). */
    public function comentariosRecentes(int $usuarioId, int $segundos): int
    {
        return (int) $this->db->valor(
            'SELECT COUNT(*) FROM comentarios WHERE usuario_id = ? AND criado_em > ?',
            [$usuarioId, gmdate('Y-m-d H:i:s', time() - $segundos)],
        );
    }

    /** Apaga curtidas e comentários de alvos que deixaram de existir. */
    public function limpar(string $tipo, array $ids): void
    {
        if (!$ids) {
            return;
        }
        $marcadores = implode(', ', array_fill(0, count($ids), '?'));
        foreach (['curtidas', 'comentarios'] as $tabela) {
            $this->db->executar("DELETE FROM {$tabela} WHERE alvo_tipo = ? AND alvo_id IN ({$marcadores})", [$tipo, ...array_values($ids)]);
        }
    }
}
