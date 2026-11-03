<?php

declare(strict_types=1);

/**
 * Roda antes de cada deploy (no Railway, pelo preDeployCommand do railway.json), na ordem:
 * migrations, cidades do IBGE e fotos padrão dos modelos. Os três podem repetir sem problema.
 * Se um falhar, o deploy para e a versão anterior continua no ar.
 *
 * Uso: php scripts/preparar-deploy.php
 */

foreach (['migrar.php', 'importar-cidades.php', 'importar-fotos-modelos.php'] as $script) {
    echo "> {$script}\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script), $codigo);
    if ($codigo !== 0) {
        fwrite(STDERR, "{$script} falhou (código {$codigo}).\n");
        exit($codigo);
    }
}
