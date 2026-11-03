<?php

declare(strict_types=1);

namespace GarageHub\Core;

use RuntimeException;

/** Erro esperado da API, convertido em resposta JSON com o status indicado. */
final class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $mensagem,
        public readonly array $campos = [],
    ) {
        parent::__construct($mensagem);
    }

    public static function naoEncontrado(string $mensagem = 'Não encontrado.'): self
    {
        return new self(404, $mensagem);
    }

    public static function naoAutenticado(): self
    {
        return new self(401, 'Faça login para continuar.');
    }

    /** @param array<string, string> $campos */
    public static function validacao(array $campos): self
    {
        return new self(422, 'Confira os campos destacados.', $campos);
    }
}
