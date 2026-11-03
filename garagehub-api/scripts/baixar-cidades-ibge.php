<?php

declare(strict_types=1);

/**
 * Baixa a lista de municípios do IBGE e grava em database/seeds/cidades.json,
 * no formato compacto [[código IBGE, nome, UF], ...]. Só precisa rodar de novo
 * se o IBGE criar ou renomear municípios.
 *
 * Uso: php scripts/baixar-cidades-ibge.php
 */

$url = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios?view=nivelado';

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'GarageHub/0.1']);
// PHP sem curl.cainfo (comum no Windows): usa os certificados do sistema. A verificação continua ligada.
if (!ini_get('curl.cainfo') && defined('CURLSSLOPT_NATIVE_CA')) {
    curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
}
$corpo = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

$municipios = $status === 200 ? json_decode((string) $corpo, true) : null;
if (!is_array($municipios) || count($municipios) < 5000) {
    fwrite(STDERR, "Resposta inesperada do IBGE ({$status}).\n");
    exit(1);
}

$cidades = array_map(fn ($m) => [(int) $m['municipio-id'], $m['municipio-nome'], $m['UF-sigla']], $municipios);
usort($cidades, fn ($a, $b) => [$a[2], $a[1]] <=> [$b[2], $b[1]]);

$destino = dirname(__DIR__) . '/database/seeds/cidades.json';
file_put_contents($destino, json_encode($cidades, JSON_UNESCAPED_UNICODE));
echo count($cidades) . " municípios gravados em {$destino}\n";
