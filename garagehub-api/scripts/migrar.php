<?php

declare(strict_types=1);

// Aplica as migrations pendentes no banco configurado no .env
// Uso: php scripts/migrar.php

use GarageHub\Core\Db;
use GarageHub\Core\Migrador;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $aplicadas = (new Migrador(Db::doAmbiente(), dirname(__DIR__) . '/database/migrations'))->migrar();
} catch (PDOException $e) {
    fwrite(STDERR, "Erro no banco: {$e->getMessage()}\n");
    exit(1);
}

echo $aplicadas ? 'Aplicadas: ' . implode(', ', $aplicadas) . "\n" : "Nada a aplicar, o banco já está atualizado.\n";
