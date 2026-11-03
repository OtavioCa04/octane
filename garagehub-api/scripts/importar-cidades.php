<?php

declare(strict_types=1);

// Carrega os municípios do IBGE (database/seeds/cidades.json) na tabela cidades.
// Pode rodar várias vezes: só insere o que ainda não existe.
// Uso: php scripts/importar-cidades.php

use GarageHub\Core\Db;
use GarageHub\Local\CidadeRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

$arquivo = dirname(__DIR__) . '/database/seeds/cidades.json';
$cidades = json_decode((string) @file_get_contents($arquivo), true);
if (!is_array($cidades)) {
    fwrite(STDERR, "Não encontrei {$arquivo}. Rode antes: php scripts/baixar-cidades-ibge.php\n");
    exit(1);
}

$novas = (new CidadeRepository(Db::doAmbiente()))->importar($cidades);
echo $novas ? "{$novas} cidades importadas.\n" : "As cidades já estavam importadas.\n";
