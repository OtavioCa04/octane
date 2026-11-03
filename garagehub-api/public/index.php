<?php

declare(strict_types=1);

// Ponto de entrada da API: toda requisição passa por aqui.

use GarageHub\App;
use GarageHub\Core\Db;
use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;

// No servidor embutido do PHP (php -S), arquivos que existem em public/ (as fotos em /uploads)
// são servidos direto. Em Apache isso fica a cargo do .htaccess.
if (PHP_SAPI === 'cli-server') {
    $caminho = realpath(__DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));
    if ($caminho && str_starts_with($caminho, __DIR__ . DIRECTORY_SEPARATOR) && is_file($caminho) && !str_ends_with($caminho, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $request = Request::doServidor();
} catch (HttpException $e) {
    Response::erro($e->status, $e->getMessage())->enviar();
    return;
}

(new App(Db::doAmbiente()))->processar($request)->enviar();
