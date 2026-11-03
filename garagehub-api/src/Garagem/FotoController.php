<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;

/** Fotos dos carros do usuário logado: enviar, escolher a capa e excluir. */
final class FotoController
{
    public const MAX_POR_CARRO = 30;

    public function __construct(
        private readonly VeiculoRepository $veiculos,
        private readonly FotoRepository $fotos,
        private readonly FotoStorage $storage,
    ) {
    }

    /** POST /api/vehicles/{id}/photos com multipart/form-data, campo "foto". */
    public function enviar(Request $request): Response
    {
        $veiculo = $this->veiculos->doDono($request->id(), (int) $request->usuario['id'])
            ?? throw HttpException::naoEncontrado('Carro não encontrado.');

        if ($this->fotos->contar((int) $veiculo['id']) >= self::MAX_POR_CARRO) {
            throw HttpException::validacao(['foto' => 'Cada carro pode ter até ' . self::MAX_POR_CARRO . ' fotos.']);
        }
        $upload = $request->arquivos['foto'] ?? null;
        if ($upload === null) {
            throw HttpException::validacao(['foto' => 'Escolha uma foto para enviar.']);
        }

        $salvo = $this->storage->salvar((int) $veiculo['id'], $upload);
        $id = $this->fotos->criar((int) $veiculo['id'], $salvo);

        return Response::criado(Apresentador::foto($this->fotos->porId($id)));
    }

    /** PUT /api/photos/{id}/cover */
    public function definirCapa(Request $request): Response
    {
        $foto = $this->buscarDoDono($request);
        $this->fotos->definirCapa((int) $foto['veiculo_id'], (int) $foto['id']);
        return Response::json(Apresentador::foto($this->fotos->porId((int) $foto['id'])));
    }

    /** DELETE /api/photos/{id} */
    public function excluir(Request $request): Response
    {
        $foto = $this->buscarDoDono($request);
        $this->fotos->excluir($foto);
        $this->storage->excluir($foto['arquivo']);
        return Response::semConteudo();
    }

    private function buscarDoDono(Request $request): array
    {
        return $this->fotos->doDono($request->id(), (int) $request->usuario['id'])
            ?? throw HttpException::naoEncontrado('Foto não encontrada.');
    }
}
