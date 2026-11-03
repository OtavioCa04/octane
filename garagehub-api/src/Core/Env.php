<?php

declare(strict_types=1);

namespace GarageHub\Core;

/** Lê variáveis do arquivo .env sem sobrescrever as que já existem no ambiente. */
final class Env
{
    public static function carregar(string $arquivo): void
    {
        if (!is_file($arquivo)) {
            return;
        }
        foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
                continue;
            }
            [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
            $valor = trim($valor, "\"'");
            if (getenv($chave) === false && !isset($_ENV[$chave])) {
                putenv("{$chave}={$valor}");
                $_ENV[$chave] = $valor;
            }
        }
    }

    public static function get(string $chave, ?string $padrao = null): ?string
    {
        $valor = $_ENV[$chave] ?? getenv($chave);
        return ($valor === false || $valor === null || $valor === '') ? $padrao : (string) $valor;
    }

    public static function bool(string $chave, bool $padrao = false): bool
    {
        $valor = self::get($chave);
        return $valor === null ? $padrao : in_array(strtolower($valor), ['1', 'true', 'on', 'yes'], true);
    }
}
