<?php

declare(strict_types=1);

namespace GarageHub\Catalogo;

use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;

/**
 * Catálogo de carros da Tabela FIPE, normalizado para o GarageHub:
 * marcas → modelos → anos → detalhe (valor de referência, código FIPE, combustível).
 */
final class CatalogoController
{
    // Nomes da FIPE que ficam melhores reescritos
    private const MARCAS = [
        'VW - VolksWagen' => 'Volkswagen',
        'GM - Chevrolet' => 'Chevrolet',
        'ASTON MARTIN' => 'Aston Martin',
        'ARROW' => 'Arrow',
    ];

    private const MESES = [
        'janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
    ];

    // A FIPE usa o ano 32000 para carros zero quilômetro
    private const ANO_ZERO_KM = 32000;

    public function __construct(private readonly FonteFipe $fipe)
    {
    }

    public function marcas(Request $request): Response
    {
        $marcas = array_map(fn ($m) => ['id' => (string) $m['code'], 'nome' => self::nomeMarca($m['name'])], $this->fipe->marcas());
        usort($marcas, fn ($a, $b) => strcasecmp($a['nome'], $b['nome']));
        return Response::json($marcas);
    }

    public function modelos(Request $request): Response
    {
        $modelos = $this->fipe->modelos($this->id($request, 'marca'));
        return Response::json(array_map(fn ($m) => ['id' => (string) $m['code'], 'nome' => $m['name']], $modelos));
    }

    public function anos(Request $request): Response
    {
        $anos = $this->fipe->anos($this->id($request, 'marca'), $this->id($request, 'modelo'));
        return Response::json(array_map(fn ($a) => self::ano($a), $anos));
    }

    public function detalhe(Request $request): Response
    {
        $marcaId = $this->id($request, 'marca');
        $modeloId = $this->id($request, 'modelo');
        $anoId = $request->params['ano'] ?? '';
        if (!preg_match('/^\d{4,5}-\d$/', $anoId)) {
            throw HttpException::naoEncontrado();
        }

        $d = $this->fipe->detalhe($marcaId, $modeloId, $anoId);
        [$modelo, $versao] = self::separarModelo($d['model']);
        $anoModelo = (int) $d['modelYear'] === self::ANO_ZERO_KM ? (int) date('Y') : (int) $d['modelYear'];

        return Response::json([
            'fipeMarcaId' => $marcaId,
            'fipeModeloId' => $modeloId,
            'fipeAnoId' => $anoId,
            'marca' => self::nomeMarca($d['brand']),
            'modelo' => $modelo,
            'versao' => $versao,
            'nomeFipe' => $d['model'],
            'anoModelo' => $anoModelo,
            'zeroKm' => (int) $d['modelYear'] === self::ANO_ZERO_KM,
            'combustivel' => $d['fuel'],
            'codigoFipe' => $d['codeFipe'],
            'valor' => self::valor($d['price']),
            'mesReferencia' => self::mesReferencia($d['referenceMonth']),
        ]);
    }

    private function id(Request $request, string $nome): string
    {
        $valor = $request->params[$nome] ?? '';
        if (!ctype_digit($valor)) {
            throw HttpException::naoEncontrado();
        }
        return $valor;
    }

    public static function nomeMarca(string $nome): string
    {
        if (isset(self::MARCAS[$nome])) {
            return self::MARCAS[$nome];
        }
        // "XX - Nome" → "Nome"
        return str_contains($nome, ' - ') ? trim(explode(' - ', $nome, 2)[1]) : $nome;
    }

    /**
     * Separa o nome da FIPE em modelo e versão, cortando no primeiro trecho que começa com número:
     * "Golf GTi 2.0 TSI 220cv Aut." → ["Golf GTi", "2.0 TSI 220cv Aut."]. O usuário pode ajustar no formulário.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function separarModelo(string $nome): array
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        foreach ($partes as $i => $parte) {
            if ($i > 0 && preg_match('/^\d/', $parte)) {
                return [implode(' ', array_slice($partes, 0, $i)), implode(' ', array_slice($partes, $i))];
            }
        }
        return [trim($nome), null];
    }

    /** "R$ 104.300,00" → 104300.0 */
    public static function valor(string $preco): ?float
    {
        $numero = str_replace(['.', ','], ['', '.'], preg_replace('/[^\d.,]/', '', $preco));
        return is_numeric($numero) ? (float) $numero : null;
    }

    /** "outubro de 2026" → "2026-10-01" */
    public static function mesReferencia(string $texto): ?string
    {
        if (preg_match('/^(\p{L}+)\s+de\s+(\d{4})$/u', mb_strtolower(trim($texto)), $m) && isset(self::MESES[$m[1]])) {
            return sprintf('%s-%02d-01', $m[2], self::MESES[$m[1]]);
        }
        return null;
    }

    /** {"code": "2016-1", "name": "2016 Gasolina"} → {"id", "nome", "ano", "combustivel"} */
    private static function ano(array $a): array
    {
        $ano = (int) explode('-', (string) $a['code'])[0];
        $combustivel = trim((string) preg_replace('/^\S+\s*/', '', (string) $a['name'])) ?: null;
        $zeroKm = $ano === self::ANO_ZERO_KM;
        return [
            'id' => (string) $a['code'],
            'nome' => $zeroKm ? trim('Zero km ' . $combustivel) : $a['name'],
            'ano' => $zeroKm ? (int) date('Y') : $ano,
            'combustivel' => $combustivel,
        ];
    }
}
