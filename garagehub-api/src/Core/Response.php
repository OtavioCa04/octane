<?php

declare(strict_types=1);

namespace GarageHub\Core;

/**
 * Formato das respostas:
 *   sucesso → {"data": ...}
 *   erro    → {"error": {"message": "...", "fields": {"campo": "mensagem"}}}
 */
final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly ?array $corpo = null,
        public array $headers = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, ['data' => $data]);
    }

    public static function criado(mixed $data): self
    {
        return self::json($data, 201);
    }

    public static function semConteudo(): self
    {
        return new self(204);
    }

    public static function erro(int $status, string $mensagem, array $campos = []): self
    {
        $erro = ['message' => $mensagem];
        if ($campos) {
            $erro['fields'] = $campos;
        }
        return new self($status, ['error' => $erro]);
    }

    public function enviar(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        foreach ($this->headers as $nome => $valor) {
            header("{$nome}: {$valor}");
        }
        if ($this->corpo !== null) {
            echo json_encode($this->corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
