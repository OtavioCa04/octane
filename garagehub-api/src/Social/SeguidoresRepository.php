<?php

declare(strict_types=1);

namespace GarageHub\Social;

use GarageHub\Core\Db;

final class SeguidoresRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    // --- Pessoas ---

    public function seguirUsuario(int $seguidorId, int $seguidoId): void
    {
        if (!$this->segueUsuario($seguidorId, $seguidoId)) {
            $this->db->executar(
                'INSERT INTO seguidores_usuarios (seguidor_id, seguido_id, criado_em) VALUES (?, ?, ?)',
                [$seguidorId, $seguidoId, Db::agora()],
            );
        }
    }

    public function deixarDeSeguirUsuario(int $seguidorId, int $seguidoId): void
    {
        $this->db->executar('DELETE FROM seguidores_usuarios WHERE seguidor_id = ? AND seguido_id = ?', [$seguidorId, $seguidoId]);
    }

    public function segueUsuario(int $seguidorId, int $seguidoId): bool
    {
        return (bool) $this->db->valor(
            'SELECT 1 FROM seguidores_usuarios WHERE seguidor_id = ? AND seguido_id = ?',
            [$seguidorId, $seguidoId],
        );
    }

    /** @return array{seguidores: int, seguindo: int} */
    public function contagemUsuario(int $usuarioId): array
    {
        return [
            'seguidores' => (int) $this->db->valor('SELECT COUNT(*) FROM seguidores_usuarios WHERE seguido_id = ?', [$usuarioId]),
            'seguindo' => (int) $this->db->valor('SELECT COUNT(*) FROM seguidores_usuarios WHERE seguidor_id = ?', [$usuarioId]),
        ];
    }

    // --- Carros ---

    public function seguirVeiculo(int $usuarioId, int $veiculoId): void
    {
        if (!$this->segueVeiculo($usuarioId, $veiculoId)) {
            $this->db->executar(
                'INSERT INTO seguidores_veiculos (usuario_id, veiculo_id, criado_em) VALUES (?, ?, ?)',
                [$usuarioId, $veiculoId, Db::agora()],
            );
        }
    }

    public function deixarDeSeguirVeiculo(int $usuarioId, int $veiculoId): void
    {
        $this->db->executar('DELETE FROM seguidores_veiculos WHERE usuario_id = ? AND veiculo_id = ?', [$usuarioId, $veiculoId]);
    }

    public function segueVeiculo(int $usuarioId, int $veiculoId): bool
    {
        return (bool) $this->db->valor(
            'SELECT 1 FROM seguidores_veiculos WHERE usuario_id = ? AND veiculo_id = ?',
            [$usuarioId, $veiculoId],
        );
    }

    public function seguidoresDoVeiculo(int $veiculoId): int
    {
        return (int) $this->db->valor('SELECT COUNT(*) FROM seguidores_veiculos WHERE veiculo_id = ?', [$veiculoId]);
    }
}
