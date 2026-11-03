<?php

declare(strict_types=1);

namespace GarageHub\Core;

final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable, middleware: list<callable>}> */
    private array $rotas = [];

    public function get(string $padrao, callable $handler, array $middleware = []): void
    {
        $this->adicionar('GET', $padrao, $handler, $middleware);
    }

    public function post(string $padrao, callable $handler, array $middleware = []): void
    {
        $this->adicionar('POST', $padrao, $handler, $middleware);
    }

    public function put(string $padrao, callable $handler, array $middleware = []): void
    {
        $this->adicionar('PUT', $padrao, $handler, $middleware);
    }

    public function delete(string $padrao, callable $handler, array $middleware = []): void
    {
        $this->adicionar('DELETE', $padrao, $handler, $middleware);
    }

    /** "/api/vehicles/{id}" vira uma regex com o grupo nomeado "id". */
    private function adicionar(string $method, string $padrao, callable $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $padrao) . '$#';
        $this->rotas[] = compact('method', 'regex', 'handler', 'middleware');
    }

    public function despachar(Request $request): Response
    {
        $caminhoExiste = false;

        foreach ($this->rotas as $rota) {
            if (!preg_match($rota['regex'], $request->path, $m)) {
                continue;
            }
            $caminhoExiste = true;
            if ($rota['method'] !== $request->method) {
                continue;
            }

            $request->params = array_map('rawurldecode', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
            foreach ($rota['middleware'] as $middleware) {
                $middleware($request);
            }
            return ($rota['handler'])($request);
        }

        throw $caminhoExiste
            ? new HttpException(405, 'Método não permitido.')
            : HttpException::naoEncontrado('Rota não encontrada.');
    }
}
