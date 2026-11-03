<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Auth\UsuarioRepository;
use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Social\InteracoesRepository;
use GarageHub\Social\SeguidoresRepository;

/**
 * Páginas públicas: garagehub.com/<username> e garagehub.com/<username>/<slug>.
 * Mostram só carros visíveis e escondem placa e valores. Perfil privado responde 404,
 * exceto para o próprio dono.
 */
final class PerfilPublicoController
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly VeiculoRepository $veiculos,
        private readonly ModificacaoRepository $modificacoes,
        private readonly FotoRepository $fotos,
        private readonly SeguidoresRepository $seguidores,
        private readonly InteracoesRepository $interacoes,
    ) {
    }

    public function perfil(Request $request): Response
    {
        $usuario = $this->usuarioVisivel($request);
        $lista = $this->veiculos->doUsuario((int) $usuario['id'], apenasVisiveis: true);
        $mods = $this->modificacoes->dosVeiculos(array_map(fn ($v) => (int) $v['id'], $lista));

        $veiculos = array_map(fn ($v) => Apresentador::veiculo($v, $mods[(int) $v['id']] ?? [], false), $lista);
        return Response::json(Apresentador::perfilPublico($usuario, $veiculos) + $this->socialDoPerfil($request, (int) $usuario['id']));
    }

    public function veiculo(Request $request): Response
    {
        $usuario = $this->usuarioVisivel($request);
        $veiculo = $this->veiculos->porSlug((int) $usuario['id'], $request->params['slug']);
        if ($veiculo === null || !$veiculo['visivel']) {
            throw HttpException::naoEncontrado('Carro não encontrado.');
        }

        $id = (int) $veiculo['id'];
        $mods = $this->modificacoes->dosVeiculos([$id]);
        $fotos = $this->fotos->dosVeiculos([$id]);
        $eu = $request->usuario ? (int) $request->usuario['id'] : null;

        $dados = Apresentador::veiculo($veiculo, $mods[$id] ?? [], false, $fotos[$id] ?? []) + [
            'seguidores' => $this->seguidores->seguidoresDoVeiculo($id),
            'euSigo' => $eu ? $this->seguidores->segueVeiculo($eu, $id) : false,
            'interacao' => ['tipo' => 'veiculo', 'id' => $id] + $this->interacoes->contagens('veiculo', [$id], $eu)[$id],
        ];
        // Cada modificação da linha do tempo também pode ser curtida e comentada
        $contagensMods = $this->interacoes->contagens('modificacao', array_column($dados['modificacoes'], 'id'), $eu);
        foreach ($dados['modificacoes'] as $n => $m) {
            $dados['modificacoes'][$n]['interacao'] = ['tipo' => 'modificacao', 'id' => $m['id']] + $contagensMods[$m['id']];
        }

        return Response::json([
            'perfil' => Apresentador::perfilPublico($usuario, []) + $this->socialDoPerfil($request, (int) $usuario['id']),
            'veiculo' => $dados,
        ]);
    }

    /** Contagem de seguidores e se quem está vendo já segue (false para visitantes). */
    private function socialDoPerfil(Request $request, int $usuarioId): array
    {
        $eu = $request->usuario ? (int) $request->usuario['id'] : null;
        return $this->seguidores->contagemUsuario($usuarioId) + [
            'euSigo' => $eu ? $this->seguidores->segueUsuario($eu, $usuarioId) : false,
        ];
    }

    private function usuarioVisivel(Request $request): array
    {
        $usuario = $this->usuarios->porUsername($request->params['username']);
        $ehDono = $usuario && $request->usuario && (int) $request->usuario['id'] === (int) $usuario['id'];

        if ($usuario === null || ($usuario['perfil_privado'] && !$ehDono)) {
            throw HttpException::naoEncontrado('Essa garagem não existe ou é privada.');
        }
        return $usuario;
    }
}
