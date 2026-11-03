<?php

declare(strict_types=1);

/**
 * Busca a foto principal de cada modelo de carro na Wikipedia/Wikimedia Commons,
 * baixa uma versão redimensionada e grava um manifesto com os dados de crédito.
 *
 * Uso:
 *   php buscar-fotos-modelos.php [--entrada=modelos.json] [--saida=../../garagehub-web/public/modelos]
 *                                [--largura=1280] [--sql=fotos_modelo.sql] [--forcar]
 *
 * Entrada: JSON com [{"marca": "Volkswagen", "modelo": "Golf GTI", "busca": "Volkswagen Golf Mk7"}, ...]
 *   "busca"    opcional: afina a pesquisa quando o nome sozinho não acha a página certa.
 *   "commons"  opcional (true): busca direto nos arquivos do Commons em vez do artigo da Wikipedia.
 *              Use para gerações específicas, já que o artigo geral mostra o modelo mais novo.
 *   "arquivo"  opcional: nome exato de um arquivo do Commons escolhido à mão.
 *
 * Saída:
 *   <saida>/<slug>.jpg (ou .webp, se a extensão GD com WebP estiver disponível)
 *   <saida>/manifesto.json com autor, licença e página de origem de cada foto
 *   opcionalmente um .sql com INSERTs para a tabela fotos_modelo
 *
 * As fotos do Commons exigem crédito (autor + licença) quando exibidas publicamente.
 * O manifesto guarda esses dados desde já para que isso seja só uma linha na tela depois.
 */

// O Wikimedia pede um User-Agent com um contato. Defina GARAGEHUB_CONTATO com seu e-mail ou site.
define('USER_AGENT', 'GarageHub-dev/0.1 (script de fotos de modelos; contato: ' . (getenv('GARAGEHUB_CONTATO') ?: 'nao-informado') . ')');
const WIKIS = ['pt', 'en'];
const PAUSA_ENTRE_REQUISICOES_US = 1_000_000;
const MAX_TENTATIVAS = 5;

$opcoes = getopt('', ['entrada::', 'saida::', 'largura::', 'sql::', 'forcar']);
$entrada = $opcoes['entrada'] ?? __DIR__ . '/modelos.json';
$saida = rtrim($opcoes['saida'] ?? __DIR__ . '/../../garagehub-web/public/modelos', '/\\');
$largura = (int) ($opcoes['largura'] ?? 1280);
$arquivoSql = $opcoes['sql'] ?? null;
$forcar = isset($opcoes['forcar']);

$modelos = json_decode((string) @file_get_contents($entrada), true);
if (!is_array($modelos)) {
    fwrite(STDERR, "Não consegui ler a lista de modelos em {$entrada}\n");
    exit(1);
}

if (!is_dir($saida) && !mkdir($saida, 0775, true)) {
    fwrite(STDERR, "Não consegui criar a pasta {$saida}\n");
    exit(1);
}

$arquivoManifesto = "{$saida}/manifesto.json";
$manifesto = is_file($arquivoManifesto) ? (json_decode((string) file_get_contents($arquivoManifesto), true) ?: []) : [];
$converteWebp = function_exists('imagewebp') && function_exists('imagecreatefromstring');

$ok = 0;
$falhas = [];

foreach ($modelos as $item) {
    $marca = trim((string) ($item['marca'] ?? ''));
    $modelo = trim((string) ($item['modelo'] ?? ''));
    if ($marca === '' || $modelo === '') {
        continue;
    }
    $slug = slug("{$marca} {$modelo}");
    $termo = $item['busca'] ?? "{$marca} {$modelo}";

    if (!$forcar && isset($manifesto[$slug]) && is_file("{$saida}/{$manifesto[$slug]['arquivo']}")) {
        echo "· {$marca} {$modelo}: já existe, pulando (use --forcar para buscar de novo)\n";
        continue;
    }

    try {
        $pagina = match (true) {
            isset($item['arquivo']) => ['imagem' => $item['arquivo'], 'url' => ''],
            !empty($item['commons']) => encontrarArquivoNoCommons($termo),
            default => encontrarPaginaComImagem($termo),
        };
        if ($pagina === null) {
            throw new RuntimeException('nenhuma página com foto encontrada');
        }

        $info = dadosDaImagem($pagina['imagem'], $largura);
        $conteudo = baixar($info['url']);

        $arquivo = "{$slug}.jpg";
        if ($converteWebp && ($img = @imagecreatefromstring($conteudo))) {
            $arquivo = "{$slug}.webp";
            imagewebp($img, "{$saida}/{$arquivo}", 82);
        } else {
            file_put_contents("{$saida}/{$arquivo}", $conteudo);
        }

        $manifesto[$slug] = [
            'marca' => $marca,
            'modelo' => $modelo,
            'arquivo' => $arquivo,
            'fonte' => 'wikimedia',
            'arquivo_original' => $pagina['imagem'],
            'autor' => $info['autor'],
            'licenca' => $info['licenca'],
            'licenca_url' => $info['licenca_url'],
            'pagina_origem' => $info['pagina_origem'],
            'artigo' => $pagina['url'],
            'baixado_em' => date('c'),
        ];
        $ok++;
        echo "✓ {$marca} {$modelo}: {$arquivo} ({$info['licenca']}, {$info['autor']})\n";
    } catch (Throwable $e) {
        $falhas[] = "{$marca} {$modelo}";
        echo "✗ {$marca} {$modelo}: {$e->getMessage()}\n";
    }
}

ksort($manifesto);
file_put_contents($arquivoManifesto, json_encode($manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

if ($arquivoSql !== null) {
    file_put_contents($arquivoSql, gerarSql($manifesto));
    echo "SQL gravado em {$arquivoSql}\n";
}

echo "\n{$ok} foto(s) baixada(s). Manifesto em {$arquivoManifesto}\n";
if ($falhas) {
    echo 'Sem foto: ' . implode(', ', $falhas) . "\nTente um termo mais específico no campo \"busca\".\n";
}

// ---------------------------------------------------------------------------

/**
 * Procura o artigo nas Wikipedias em português e inglês e devolve o primeiro
 * resultado que tem uma foto (ignorando logos e desenhos em SVG).
 *
 * @return array{imagem: string, url: string}|null
 */
function encontrarPaginaComImagem(string $termo): ?array
{
    foreach (WIKIS as $wiki) {
        $resposta = apiJson("https://{$wiki}.wikipedia.org/w/api.php", [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => '2',
            'generator' => 'search',
            'gsrsearch' => $termo,
            'gsrlimit' => '5',
            'gsrnamespace' => '0',
            'prop' => 'pageimages|info',
            'piprop' => 'name',
            'inprop' => 'url',
        ]);

        $paginas = $resposta['query']['pages'] ?? [];
        usort($paginas, fn ($a, $b) => ($a['index'] ?? 99) <=> ($b['index'] ?? 99));

        foreach ($paginas as $p) {
            $imagem = $p['pageimage'] ?? null;
            if ($imagem && !preg_match('/\.(svg|gif)$/i', $imagem) && !preg_match('/logo|emblem|badge/i', $imagem)) {
                return ['imagem' => $imagem, 'url' => $p['fullurl'] ?? ''];
            }
        }
    }

    return null;
}

/**
 * Busca direto nos arquivos do Commons. Serve para gerações específicas
 * (ex.: "Toyota Supra A80"), já que o artigo geral costuma mostrar o modelo mais novo.
 *
 * @return array{imagem: string, url: string}|null
 */
function encontrarArquivoNoCommons(string $termo): ?array
{
    $resposta = apiJson('https://commons.wikimedia.org/w/api.php', [
        'action' => 'query',
        'format' => 'json',
        'formatversion' => '2',
        'list' => 'search',
        'srsearch' => "{$termo} filetype:bitmap",
        'srnamespace' => '6',
        'srlimit' => '10',
    ]);

    foreach ($resposta['query']['search'] ?? [] as $r) {
        $nome = preg_replace('/^File:/', '', $r['title']);
        if (preg_match('/\.jpe?g$/i', $nome) && !preg_match('/logo|emblem|badge|interior|engine|motor/i', $nome)) {
            return ['imagem' => $nome, 'url' => ''];
        }
    }

    return null;
}

/**
 * Pega no Commons a URL da versão redimensionada e os dados de crédito.
 *
 * @return array{url: string, autor: string, licenca: string, licenca_url: ?string, pagina_origem: string}
 */
function dadosDaImagem(string $nomeArquivo, int $largura): array
{
    $resposta = apiJson('https://commons.wikimedia.org/w/api.php', [
        'action' => 'query',
        'format' => 'json',
        'formatversion' => '2',
        'titles' => "File:{$nomeArquivo}",
        'prop' => 'imageinfo',
        'iiprop' => 'url|extmetadata',
        'iiurlwidth' => (string) $largura,
    ]);

    $info = $resposta['query']['pages'][0]['imageinfo'][0] ?? null;
    if ($info === null) {
        // A imagem pode estar só na Wikipedia local (uso justo), não no Commons: não serve.
        throw new RuntimeException("a imagem {$nomeArquivo} não está no Commons");
    }

    $meta = $info['extmetadata'] ?? [];
    $texto = fn (string $campo) => trim(html_entity_decode(strip_tags((string) ($meta[$campo]['value'] ?? '')), ENT_QUOTES | ENT_HTML5));

    $licenca = $texto('LicenseShortName');
    if ($licenca === '' || preg_match('/fair use|non-free/i', $licenca)) {
        throw new RuntimeException("licença não permite uso ({$licenca})");
    }

    return [
        'url' => $info['thumburl'] ?? $info['url'],
        'autor' => $texto('Artist') ?: 'Autor desconhecido',
        'licenca' => $licenca,
        'licenca_url' => $texto('LicenseUrl') ?: null,
        'pagina_origem' => $info['descriptionurl'] ?? '',
    ];
}

function apiJson(string $url, array $parametros): array
{
    $dados = json_decode(baixar($url . '?' . http_build_query($parametros)), true);
    if (!is_array($dados)) {
        throw new RuntimeException('resposta inválida da API');
    }
    return $dados;
}

function baixar(string $url): string
{
    // O Wikimedia responde 429 quando há requisições demais: espera e tenta de novo.
    for ($tentativa = 1; ; $tentativa++) {
        usleep(PAUSA_ENTRE_REQUISICOES_US);
        [$corpo, $status, $erro, $retryAfter] = requisitar($url);

        if (in_array($status, [429, 503], true) && $tentativa < MAX_TENTATIVAS) {
            $espera = $retryAfter ?? 5 * 2 ** ($tentativa - 1);
            echo "  … limite de requisições, aguardando {$espera}s\n";
            sleep($espera);
            continue;
        }
        if ($corpo === false || $status >= 400) {
            throw new RuntimeException("falha ao baixar ({$status}) {$erro}");
        }
        return $corpo;
    }
}

/** @return array{0: string|false, 1: int, 2: string, 3: ?int} */
function requisitar(string $url): array
{
    $retryAfter = null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADERFUNCTION => function ($ch, string $linha) use (&$retryAfter) {
            if (preg_match('/^retry-after:\s*(\d+)/i', $linha, $m)) {
                $retryAfter = min((int) $m[1], 60);
            }
            return strlen($linha);
        },
    ]);
    // PHP sem curl.cainfo configurado (comum no Windows): usa os certificados do sistema
    // ou um arquivo indicado em GARAGEHUB_CA_BUNDLE. A verificação de SSL continua ligada.
    if ($bundle = getenv('GARAGEHUB_CA_BUNDLE')) {
        curl_setopt($ch, CURLOPT_CAINFO, $bundle);
    } elseif (!ini_get('curl.cainfo') && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    $corpo = curl_exec($ch);

    return [$corpo, (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), curl_error($ch), $retryAfter];
}

function slug(string $texto): string
{
    // Sem iconv: no Windows ele translitera "ã" como "~a"
    $semAcento = strtr(mb_strtolower($texto), ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $semAcento), '-');
}

function gerarSql(array $manifesto): string
{
    $q = fn (?string $v) => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $v) . "'";
    $linhas = ["-- Gerado por buscar-fotos-modelos.php em " . date('c')];
    foreach ($manifesto as $f) {
        $linhas[] = sprintf(
            'INSERT INTO fotos_modelo (marca, modelo, arquivo_url, fonte, autor, licenca, licenca_url, pagina_origem) VALUES (%s, %s, %s, %s, %s, %s, %s, %s);',
            $q($f['marca']),
            $q($f['modelo']),
            $q('/modelos/' . $f['arquivo']),
            $q($f['fonte']),
            $q($f['autor']),
            $q($f['licenca']),
            $q($f['licenca_url']),
            $q($f['pagina_origem']),
        );
    }
    return implode("\n", $linhas) . "\n";
}
