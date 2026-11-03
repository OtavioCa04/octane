<?php

declare(strict_types=1);

namespace GarageHub\Local;

use GarageHub\Core\Db;
use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Core\Texto;
use GarageHub\Garagem\Apresentador;
use GarageHub\Garagem\VeiculoRepository;

/**
 * Busca e cidades.
 *   GET /api/cities?q=camp[&uf=SP]          → cidades para o seletor
 *   GET /api/search?q=golf[&cidade=|&uf=]   → pessoas e carros públicos
 *
 * Na busca de carros, o termo vale para marca, modelo e versão, e também para as modificações
 * (nome e marca da peça): "coilover kw" acha os carros que têm essa peça.
 */
final class BuscaController
{
    public function __construct(
        private readonly Db $db,
        private readonly CidadeRepository $cidades,
        private readonly VeiculoRepository $veiculos,
    ) {
    }

    public function cidades(Request $request): Response
    {
        $q = trim((string) ($request->query['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            return Response::json([]);
        }
        $uf = isset($request->query['uf']) && preg_match('/^[A-Za-z]{2}$/', $request->query['uf']) ? $request->query['uf'] : null;
        return Response::json(array_map(
            fn ($c) => ['id' => (int) $c['id'], 'nome' => $c['nome'], 'uf' => $c['uf']],
            $this->cidades->buscar($q, $uf, 10),
        ));
    }

    /** GET /api/cities/{id} */
    public function cidade(Request $request): Response
    {
        $c = $this->cidades->porId($request->id()) ?? throw HttpException::naoEncontrado('Cidade não encontrada.');
        return Response::json(['id' => (int) $c['id'], 'nome' => $c['nome'], 'uf' => $c['uf']]);
    }

    public function buscar(Request $request): Response
    {
        $q = trim((string) ($request->query['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            throw HttpException::validacao(['q' => 'Digite pelo menos 2 letras.']);
        }
        // Cada palavra precisa aparecer em algum campo (até 5 palavras)
        $palavras = array_slice(array_values(array_filter(preg_split('/\s+/', mb_strtolower($q)) ?: [])), 0, 5);
        $padroes = array_map(fn ($p) => '%' . Texto::paraLike($p) . '%', $palavras);
        [$filtroLocal, $paramsLocal] = $this->filtroLocal($request);

        $condPessoa = "(LOWER(u.nome) LIKE ? ESCAPE '!' OR LOWER(u.username) LIKE ? ESCAPE '!')";
        $pessoas = $this->db->todos(
            "SELECT u.username, u.nome, u.avatar, c.nome AS cidade_nome, c.uf AS cidade_uf, u.cidade,
                    (SELECT COUNT(*) FROM veiculos v WHERE v.usuario_id = u.id AND v.visivel = 1) AS carros
             FROM usuarios u LEFT JOIN cidades c ON c.id = u.cidade_id
             WHERE u.perfil_privado = 0 AND " . implode(' AND ', array_fill(0, count($padroes), $condPessoa)) . " {$filtroLocal}
             ORDER BY carros DESC, u.nome
             LIMIT 10",
            [...array_merge(...array_map(fn ($p) => [$p, $p], $padroes)), ...$paramsLocal],
        );

        $condMod = "(LOWER(m.nome) LIKE ? ESCAPE '!' OR LOWER(COALESCE(m.marca_peca, '')) LIKE ? ESCAPE '!')";
        $condCarro = "(LOWER(v.marca) LIKE ? ESCAPE '!' OR LOWER(v.modelo) LIKE ? ESCAPE '!' OR LOWER(COALESCE(v.versao, '')) LIKE ? ESCAPE '!'
                       OR EXISTS (SELECT 1 FROM modificacoes m WHERE m.veiculo_id = v.id AND {$condMod}))";
        $carros = $this->db->todos(
            "SELECT v.id, u.username, u.nome AS dono_nome,
                    (SELECT m.nome FROM modificacoes m WHERE m.veiculo_id = v.id AND ("
                        . implode(' OR ', array_fill(0, count($padroes), $condMod)) . ")
                     ORDER BY m.id DESC LIMIT 1) AS modificacao
             FROM veiculos v
             JOIN usuarios u ON u.id = v.usuario_id
             LEFT JOIN cidades c ON c.id = u.cidade_id
             WHERE v.visivel = 1 AND u.perfil_privado = 0 {$filtroLocal}
               AND " . implode(' AND ', array_fill(0, count($padroes), $condCarro)) . '
             ORDER BY v.atualizado_em DESC
             LIMIT 30',
            [
                ...array_merge(...array_map(fn ($p) => [$p, $p], $padroes)),
                ...$paramsLocal,
                ...array_merge(...array_map(fn ($p) => [$p, $p, $p, $p, $p], $padroes)),
            ],
        );

        $veiculos = $this->veiculos->porIds(array_map(fn ($c) => (int) $c['id'], $carros));
        return Response::json([
            'pessoas' => array_map(fn ($p) => [
                'username' => $p['username'],
                'nome' => $p['nome'],
                'iniciais' => Apresentador::iniciais($p['nome']),
                'avatarUrl' => Apresentador::avatarUrl($p['avatar']),
                'cidade' => CidadeRepository::formatar($p['cidade_nome'], $p['cidade_uf']) ?? $p['cidade'],
                'carros' => (int) $p['carros'],
            ], $pessoas),
            'carros' => array_values(array_filter(array_map(function ($c) use ($veiculos) {
                $v = $veiculos[(int) $c['id']] ?? null;
                if (!$v) {
                    return null;
                }
                $completo = Apresentador::veiculo($v, [], false);
                return array_intersect_key($completo, array_flip([
                    'id', 'slug', 'marca', 'modelo', 'versao', 'anoModelo', 'status', 'carroceria', 'corHex', 'fotoUrl', 'fotoModeloUrl',
                ])) + [
                    'dono' => ['username' => $c['username'], 'nome' => $c['dono_nome']],
                    // Por que esse carro apareceu, quando foi por causa de uma modificação
                    'modificacao' => $c['modificacao'],
                ];
            }, $carros))),
        ]);
    }

    /** ?cidade={id} ou ?uf=SP → trecho de SQL (sobre o alias "c" de cidades) e parâmetros. */
    private function filtroLocal(Request $request): array
    {
        if (isset($request->query['cidade']) && ctype_digit((string) $request->query['cidade'])) {
            return ['AND u.cidade_id = ?', [(int) $request->query['cidade']]];
        }
        if (isset($request->query['uf']) && preg_match('/^[A-Za-z]{2}$/', (string) $request->query['uf'])) {
            return ['AND c.uf = ?', [strtoupper($request->query['uf'])]];
        }
        return ['', []];
    }
}
