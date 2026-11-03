<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Core\Validator;
use GarageHub\Social\InteracoesRepository;

final class ModificacaoController
{
    public function __construct(
        private readonly VeiculoRepository $veiculos,
        private readonly ModificacaoRepository $modificacoes,
        private readonly InteracoesRepository $interacoes,
    ) {
    }

    public function criar(Request $request): Response
    {
        $veiculo = $this->veiculos->doDono($request->id(), (int) $request->usuario['id'])
            ?? throw HttpException::naoEncontrado('Carro não encontrado.');

        $id = $this->modificacoes->criar((int) $veiculo['id'], $this->validar($request->body));
        return Response::criado(Apresentador::modificacao($this->modificacoes->porId($id), true));
    }

    public function atualizar(Request $request): Response
    {
        $mod = $this->buscarDoDono($request);
        $this->modificacoes->atualizar((int) $mod['id'], $this->validar($request->body));
        return Response::json(Apresentador::modificacao($this->modificacoes->porId((int) $mod['id']), true));
    }

    public function excluir(Request $request): Response
    {
        $mod = $this->buscarDoDono($request);
        $this->modificacoes->excluir((int) $mod['id']);
        $this->interacoes->limpar('modificacao', [(int) $mod['id']]);
        return Response::semConteudo();
    }

    private function buscarDoDono(Request $request): array
    {
        return $this->modificacoes->doDono($request->id(), (int) $request->usuario['id'])
            ?? throw HttpException::naoEncontrado('Modificação não encontrada.');
    }

    private function validar(array $body): array
    {
        $dados = Validator::validar($body, [
            'nome' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:60'],
            'descricao' => ['string', 'max:2000'],
            'data' => ['date'],
            'quilometragem' => ['int', 'min:0', 'max:3000000'],
            'valor' => ['numeric', 'min:0', 'max:9999999999'],
            'marcaPeca' => ['string', 'max:80'],
            'oficina' => ['string', 'max:120'],
        ]);

        if ($dados['data'] !== null && $dados['data'] > gmdate('Y-m-d')) {
            throw HttpException::validacao(['data' => 'A data não pode estar no futuro.']);
        }

        return [
            'nome' => $dados['nome'],
            'categoria' => $dados['categoria'],
            'descricao' => $dados['descricao'],
            'data_instalacao' => $dados['data'],
            'quilometragem' => $dados['quilometragem'],
            'valor' => $dados['valor'],
            'marca_peca' => $dados['marcaPeca'],
            'oficina' => $dados['oficina'],
        ];
    }
}
