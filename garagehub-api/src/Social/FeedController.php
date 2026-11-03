<?php

declare(strict_types=1);

namespace GarageHub\Social;

use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Garagem\Apresentador;
use GarageHub\Garagem\VeiculoRepository;
use GarageHub\Local\CidadeRepository;

final class FeedController
{
    private const POR_PAGINA = 20;

    public function __construct(
        private readonly FeedRepository $feed,
        private readonly VeiculoRepository $veiculos,
        private readonly InteracoesRepository $interacoes,
    ) {
    }

    /** GET /api/feed?aba=seguindo|explorar&pagina=1 */
    public function feed(Request $request): Response
    {
        $pagina = max(1, (int) ($request->query['pagina'] ?? 1));
        $deslocamento = ($pagina - 1) * self::POR_PAGINA;
        // Busca um a mais para saber se existe próxima página
        $limite = self::POR_PAGINA + 1;

        // No Explorar, ?cidade=<id do IBGE> ou ?uf=SP filtram por qualquer lugar.
        // ?local=cidade|estado (versão antiga) usa a cidade do perfil de quem está vendo.
        $eu = $request->usuario;
        $q = $request->query;
        $local = $q['local'] ?? null;
        $cidadeId = isset($q['cidade']) && ctype_digit((string) $q['cidade']) ? (int) $q['cidade']
            : ($local === 'cidade' && $eu['cidade_id'] ? (int) $eu['cidade_id'] : null);
        $uf = isset($q['uf']) && preg_match('/^[A-Za-z]{2}$/', (string) $q['uf']) ? strtoupper($q['uf'])
            : ($local === 'estado' && ($eu['cidade_uf'] ?? null) ? $eu['cidade_uf'] : null);

        $eventos = ($request->query['aba'] ?? 'seguindo') === 'explorar'
            ? $this->feed->explorar($limite, $deslocamento, $cidadeId, $uf)
            : $this->feed->seguindo((int) $eu['id'], $limite, $deslocamento);

        $temMais = count($eventos) > self::POR_PAGINA;
        $eventos = array_slice($eventos, 0, self::POR_PAGINA);

        $itens = $this->comInteracoes($this->montar($eventos), (int) $request->usuario['id']);
        return Response::json(['itens' => $itens, 'temMais' => $temMais, 'pagina' => $pagina]);
    }

    /** GET /api/users/suggestions */
    public function sugestoes(Request $request): Response
    {
        $eu = $request->usuario;
        $sugestoes = $this->feed->sugestoes((int) $eu['id'], $eu['cidade_id'] ? (int) $eu['cidade_id'] : null, $eu['cidade_uf'] ?? null, 6);
        $destaques = array_filter(array_map(fn ($s) => $this->feed->carroDestaque((int) $s['id']), $sugestoes));
        $veiculos = $this->veiculos->porIds(array_values($destaques));

        return Response::json(array_map(function ($s) use ($veiculos) {
            $destaque = $veiculos[$this->feed->carroDestaque((int) $s['id']) ?? 0] ?? null;
            return [
                'username' => $s['username'],
                'nome' => $s['nome'],
                'cidade' => CidadeRepository::formatar($s['cidade_nome'], $s['cidade_uf']) ?? $s['cidade'],
                'mesmaCidade' => (bool) $s['mesma_cidade'],
                'iniciais' => Apresentador::iniciais($s['nome']),
                'avatarUrl' => Apresentador::avatarUrl($s['avatar']),
                'carros' => (int) $s['carros'],
                'destaque' => $destaque ? self::resumoVeiculo($destaque) : null,
            ];
        }, $sugestoes));
    }

    /** Completa os eventos com os dados do carro, do dono, da modificação ou das fotos. */
    private function montar(array $eventos): array
    {
        $veiculos = $this->veiculos->porIds(array_unique(array_map(fn ($e) => (int) $e['veiculo_id'], $eventos)));
        $donos = $this->feed->donos(array_unique(array_map(fn ($v) => (int) $v['usuario_id'], $veiculos)));
        $modIds = array_map(fn ($e) => (int) $e['ref_id'], array_filter($eventos, fn ($e) => $e['tipo'] === 'modificacao'));
        $mods = $this->feed->modificacoes(array_values($modIds));

        $itens = [];
        foreach ($eventos as $e) {
            $v = $veiculos[(int) $e['veiculo_id']] ?? null;
            $dono = $v ? ($donos[(int) $v['usuario_id']] ?? null) : null;
            if (!$v || !$dono) {
                continue;
            }

            $item = [
                'id' => "{$e['tipo']}-{$e['ref_id']}",
                'tipo' => $e['tipo'],
                'data' => $e['data'],
                'autor' => [
                    'username' => $dono['username'],
                    'nome' => $dono['nome'],
                    'iniciais' => Apresentador::iniciais($dono['nome']),
                    'avatarUrl' => Apresentador::avatarUrl($dono['avatar']),
                ],
                'veiculo' => self::resumoVeiculo($v),
            ];

            if ($e['tipo'] === 'modificacao') {
                $m = $mods[(int) $e['ref_id']] ?? null;
                if (!$m) {
                    continue;
                }
                $item['modificacao'] = Apresentador::modificacao($m, false);
            } elseif ($e['tipo'] === 'fotos') {
                $item['quantidade'] = (int) $e['quantidade'];
                $item['fotos'] = array_map(fn ($f) => Apresentador::foto($f), $this->feed->fotosDoDia((int) $v['id'], $e['data']));
            }
            $itens[] = $item;
        }
        return $itens;
    }

    /**
     * Cada item recebe "interacao": o que se curte e comenta nele (a modificação, ou o carro
     * nos itens de carro novo e de fotos) e as contagens.
     */
    private function comInteracoes(array $itens, int $eu): array
    {
        $alvos = array_map(
            fn ($i) => $i['tipo'] === 'modificacao' ? ['modificacao', $i['modificacao']['id']] : ['veiculo', $i['veiculo']['id']],
            $itens,
        );
        $porTipo = [];
        foreach (InteracoesRepository::TIPOS as $tipo) {
            $ids = array_values(array_unique(array_map(fn ($a) => $a[1], array_filter($alvos, fn ($a) => $a[0] === $tipo))));
            $porTipo[$tipo] = $this->interacoes->contagens($tipo, $ids, $eu);
        }
        foreach ($itens as $n => $item) {
            [$tipo, $id] = $alvos[$n];
            $itens[$n]['interacao'] = ['tipo' => $tipo, 'id' => $id] + $porTipo[$tipo][$id];
        }
        return $itens;
    }

    /** O mínimo para desenhar o carro num card do feed (sem placa, valores nem dados internos). */
    private static function resumoVeiculo(array $v): array
    {
        $completo = Apresentador::veiculo($v, [], false);
        return array_intersect_key($completo, array_flip([
            'id', 'slug', 'marca', 'modelo', 'versao', 'anoModelo', 'status', 'carroceria', 'corHex', 'fotoUrl', 'fotoModeloUrl',
        ]));
    }
}
