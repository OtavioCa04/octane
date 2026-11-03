<?php

declare(strict_types=1);

/**
 * Carrega as fotos padrão dos modelos (tabela fotos_modelo) a partir do manifesto gerado por
 * buscar-fotos-modelos.php. Pode rodar em produção: não cria contas nem mexe em dados de usuários.
 *
 * Uso: php scripts/importar-fotos-modelos.php
 *
 * Procura o manifesto em database/seeds/fotos-modelo.json (pacote de deploy) e, se não achar,
 * em garagehub-web/public/modelos/manifesto.json (desenvolvimento).
 */

use GarageHub\Core\Db;

require_once dirname(__DIR__) . '/src/bootstrap.php';

$candidatos = [
    dirname(__DIR__) . '/database/seeds/fotos-modelo.json',
    dirname(__DIR__, 2) . '/garagehub-web/public/modelos/manifesto.json',
];
$manifesto = current(array_filter($candidatos, 'is_file')) ?: null;
$fotos = $manifesto ? (json_decode((string) file_get_contents($manifesto), true) ?: []) : [];

$db = Db::doAmbiente();
$agora = Db::agora();
$db->executar('DELETE FROM fotos_modelo');
foreach ($fotos as $f) {
    $db->inserir('fotos_modelo', [
        'marca' => $f['marca'],
        'modelo' => $f['modelo'],
        'arquivo_url' => '/modelos/' . $f['arquivo'],
        'fonte' => $f['fonte'],
        'autor' => $f['autor'],
        'licenca' => $f['licenca'],
        'licenca_url' => $f['licenca_url'],
        'pagina_origem' => $f['pagina_origem'],
        'created_at' => $agora,
    ]);
}

echo count($fotos) . " fotos padrão de modelos carregadas.\n";
