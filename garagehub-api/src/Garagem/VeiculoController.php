<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Core\Validator;
use GarageHub\Social\InteracoesRepository;

/** Garagem do usuário logado. Todas as consultas filtram pelo dono. */
final class VeiculoController
{
    // Campo da API → coluna do banco
    private const COLUNAS = [
        'marca' => 'marca',
        'modelo' => 'modelo',
        'versao' => 'versao',
        'anoFabricacao' => 'ano_fabricacao',
        'anoModelo' => 'ano_modelo',
        'motor' => 'motor',
        'cambio' => 'cambio',
        'combustivel' => 'combustivel',
        'cor' => 'cor',
        'corHex' => 'cor_hex',
        'carroceria' => 'carroceria',
        'quilometragem' => 'quilometragem',
        'placa' => 'placa',
        'status' => 'status',
        'descricao' => 'descricao',
        'precoCompra' => 'preco_compra',
        'valorFipe' => 'valor_fipe',
        'dataConsultaFipe' => 'data_consulta_fipe',
        'fipeCodigo' => 'fipe_codigo',
        'fipeMarcaId' => 'fipe_marca_id',
        'fipeModeloId' => 'fipe_modelo_id',
        'fipeAnoId' => 'fipe_ano_id',
        'visivel' => 'visivel',
        'ordem' => 'ordem',
    ];

    public function __construct(
        private readonly VeiculoRepository $veiculos,
        private readonly ModificacaoRepository $modificacoes,
        private readonly FotoRepository $fotos,
        private readonly FotoStorage $storage,
        private readonly InteracoesRepository $interacoes,
    ) {
    }

    public function listar(Request $request): Response
    {
        $lista = $this->veiculos->doUsuario((int) $request->usuario['id']);
        return Response::json($this->apresentarVarios($lista));
    }

    public function mostrar(Request $request): Response
    {
        return Response::json($this->apresentar($this->buscarDoDono($request)));
    }

    public function criar(Request $request): Response
    {
        $usuarioId = (int) $request->usuario['id'];
        $id = $this->veiculos->criar($usuarioId, $this->validar($request->body));
        return Response::criado($this->apresentar($this->veiculos->doDono($id, $usuarioId)));
    }

    public function atualizar(Request $request): Response
    {
        $veiculo = $this->buscarDoDono($request);
        $this->veiculos->atualizar((int) $veiculo['id'], $this->validar($request->body));
        return Response::json($this->apresentar($this->veiculos->doDono((int) $veiculo['id'], (int) $request->usuario['id'])));
    }

    public function excluir(Request $request): Response
    {
        $veiculo = $this->buscarDoDono($request);
        // As linhas das fotos somem em cascata com o carro; os arquivos, apagamos aqui
        $id = (int) $veiculo['id'];
        $arquivos = $this->fotos->arquivosDoVeiculo($id);
        $modIds = array_map(fn ($m) => (int) $m['id'], $this->modificacoes->dosVeiculos([$id])[$id] ?? []);
        $this->veiculos->excluir($id);
        array_map($this->storage->excluir(...), $arquivos);
        // Curtidas e comentários não têm chave estrangeira para o alvo: apagamos aqui
        $this->interacoes->limpar('veiculo', [$id]);
        $this->interacoes->limpar('modificacao', $modIds);
        return Response::semConteudo();
    }

    // Carro de outra pessoa responde 404, e não 403, para não revelar que o id existe
    private function buscarDoDono(Request $request): array
    {
        return $this->veiculos->doDono($request->id(), (int) $request->usuario['id'])
            ?? throw HttpException::naoEncontrado('Carro não encontrado.');
    }

    private function validar(array $body): array
    {
        if (isset($body['placa']) && is_string($body['placa'])) {
            $body['placa'] = strtoupper((string) preg_replace('/[\s-]/', '', $body['placa']));
        }

        $anoLimite = (int) date('Y') + 1;
        $dados = Validator::validar($body, [
            'marca' => ['required', 'string', 'max:80'],
            'modelo' => ['required', 'string', 'max:120'],
            'versao' => ['string', 'max:120'],
            'anoFabricacao' => ['required', 'int', 'min:1886', "max:{$anoLimite}"],
            'anoModelo' => ['required', 'int', 'min:1886', 'max:' . ($anoLimite + 1)],
            'motor' => ['string', 'max:60'],
            'cambio' => ['string', 'max:60'],
            'combustivel' => ['string', 'max:30'],
            'cor' => ['string', 'max:60'],
            'corHex' => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'carroceria' => ['string', 'in:hatch,sedan,suv,picape,cupe,classico,perua,conversivel,van'],
            'quilometragem' => ['required', 'int', 'min:0', 'max:3000000'],
            // Placa antiga (ABC1234) ou Mercosul (ABC1D23)
            'placa' => ['string', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'status' => ['required', 'string', 'in:garagem,projeto,vendido'],
            'descricao' => ['string', 'max:2000'],
            'precoCompra' => ['numeric', 'min:0', 'max:9999999999'],
            'valorFipe' => ['numeric', 'min:0', 'max:9999999999'],
            'dataConsultaFipe' => ['date'],
            'fipeCodigo' => ['string', 'max:20'],
            'fipeMarcaId' => ['string', 'regex:/^\d{1,10}$/'],
            'fipeModeloId' => ['string', 'regex:/^\d{1,10}$/'],
            'fipeAnoId' => ['string', 'regex:/^\d{4,5}-\d$/'],
            'visivel' => ['bool'],
            'ordem' => ['int', 'min:0', 'max:9999'],
        ]);

        $ano = $dados['anoFabricacao'];
        if ($dados['anoModelo'] < $ano || $dados['anoModelo'] > $ano + 1) {
            throw HttpException::validacao(['anoModelo' => 'O ano-modelo deve ser o ano de fabricação ou o seguinte.']);
        }

        $dados['visivel'] ??= true;
        $dados['ordem'] ??= 0;

        $colunas = [];
        foreach (self::COLUNAS as $campo => $coluna) {
            $valor = $dados[$campo];
            $colunas[$coluna] = is_bool($valor) ? (int) $valor : $valor;
        }
        return $colunas;
    }

    private function apresentar(array $veiculo): array
    {
        $id = (int) $veiculo['id'];
        $mods = $this->modificacoes->dosVeiculos([$id]);
        $fotos = $this->fotos->dosVeiculos([$id]);
        return Apresentador::veiculo($veiculo, $mods[$id] ?? [], true, $fotos[$id] ?? []);
    }

    private function apresentarVarios(array $lista): array
    {
        $mods = $this->modificacoes->dosVeiculos(array_map(fn ($v) => (int) $v['id'], $lista));
        return array_map(fn ($v) => Apresentador::veiculo($v, $mods[(int) $v['id']] ?? [], true), $lista);
    }
}
