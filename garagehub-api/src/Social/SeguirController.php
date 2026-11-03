<?php

declare(strict_types=1);

namespace GarageHub\Social;

use GarageHub\Auth\UsuarioRepository;
use GarageHub\Core\Db;
use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;

/**
 * Seguir pessoas e carros. Só dá para seguir o que é público:
 * perfil privado e carro escondido respondem 404, como se não existissem.
 */
final class SeguirController
{
    public function __construct(
        private readonly Db $db,
        private readonly UsuarioRepository $usuarios,
        private readonly SeguidoresRepository $seguidores,
    ) {
    }

    public function seguirUsuario(Request $request): Response
    {
        $alvo = $this->usuarioPublico($request);
        $eu = (int) $request->usuario['id'];
        if ((int) $alvo['id'] === $eu) {
            throw HttpException::validacao(['username' => 'Você não pode seguir a si mesmo.']);
        }
        $this->seguidores->seguirUsuario($eu, (int) $alvo['id']);
        return $this->estadoUsuario($eu, (int) $alvo['id']);
    }

    public function deixarDeSeguirUsuario(Request $request): Response
    {
        $alvo = $this->usuarioPublico($request);
        $eu = (int) $request->usuario['id'];
        $this->seguidores->deixarDeSeguirUsuario($eu, (int) $alvo['id']);
        return $this->estadoUsuario($eu, (int) $alvo['id']);
    }

    public function seguirVeiculo(Request $request): Response
    {
        $veiculo = $this->veiculoPublico($request);
        $eu = (int) $request->usuario['id'];
        if ((int) $veiculo['usuario_id'] === $eu) {
            throw HttpException::validacao(['veiculo' => 'Esse carro já é seu.']);
        }
        $this->seguidores->seguirVeiculo($eu, (int) $veiculo['id']);
        return $this->estadoVeiculo($eu, (int) $veiculo['id']);
    }

    public function deixarDeSeguirVeiculo(Request $request): Response
    {
        $veiculo = $this->veiculoPublico($request);
        $eu = (int) $request->usuario['id'];
        $this->seguidores->deixarDeSeguirVeiculo($eu, (int) $veiculo['id']);
        return $this->estadoVeiculo($eu, (int) $veiculo['id']);
    }

    private function usuarioPublico(Request $request): array
    {
        $usuario = $this->usuarios->porUsername($request->params['username']);
        if ($usuario === null || $usuario['perfil_privado']) {
            throw HttpException::naoEncontrado('Essa garagem não existe ou é privada.');
        }
        return $usuario;
    }

    private function veiculoPublico(Request $request): array
    {
        $veiculo = $this->db->um(
            'SELECT v.id, v.usuario_id FROM veiculos v JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.id = ? AND v.visivel = 1 AND u.perfil_privado = 0',
            [$request->id()],
        );
        return $veiculo ?? throw HttpException::naoEncontrado('Carro não encontrado.');
    }

    private function estadoUsuario(int $eu, int $alvoId): Response
    {
        return Response::json(['euSigo' => $this->seguidores->segueUsuario($eu, $alvoId)] + $this->seguidores->contagemUsuario($alvoId));
    }

    private function estadoVeiculo(int $eu, int $veiculoId): Response
    {
        return Response::json([
            'euSigo' => $this->seguidores->segueVeiculo($eu, $veiculoId),
            'seguidores' => $this->seguidores->seguidoresDoVeiculo($veiculoId),
        ]);
    }
}
