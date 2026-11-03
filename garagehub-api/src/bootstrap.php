<?php

declare(strict_types=1);

use GarageHub\Core\Env;

// Autoload simples (PSR-4): GarageHub\Core\Router → src/Core/Router.php
spl_autoload_register(function (string $classe): void {
    $prefixo = 'GarageHub\\';
    if (!str_starts_with($classe, $prefixo)) {
        return;
    }
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', substr($classe, strlen($prefixo))) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

Env::carregar(dirname(__DIR__) . '/.env');

date_default_timezone_set('UTC');
