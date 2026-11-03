<?php

declare(strict_types=1);

namespace GarageHub\Core;

use DateTimeImmutable;

/**
 * Validação dos dados recebidos. Cada campo recebe uma lista de regras:
 *
 *   Validator::validar($body, [
 *       'email' => ['required', 'email', 'max:180'],
 *       'ano'   => ['required', 'int', 'min:1900'],
 *   ]);
 *
 * Devolve só os campos declarados, já limpos (texto aparado, '' vira null,
 * números convertidos). Se algo falhar, lança HttpException 422 com as mensagens.
 *
 * Regras: required, string, int, numeric, bool, email, date, url,
 *         min:N, max:N (tamanho do texto ou valor do número), in:a,b,c, regex:/.../
 */
final class Validator
{
    public static function validar(array $dados, array $regras): array
    {
        $limpos = [];
        $erros = [];

        foreach ($regras as $campo => $lista) {
            $valor = $dados[$campo] ?? null;
            if (is_string($valor)) {
                $valor = trim($valor);
                if ($valor === '') {
                    $valor = null;
                }
            }

            if ($valor === null) {
                if (in_array('required', $lista, true)) {
                    $erros[$campo] = 'Campo obrigatório.';
                } else {
                    $limpos[$campo] = null;
                }
                continue;
            }

            foreach ($lista as $regra) {
                [$nome, $arg] = array_pad(explode(':', $regra, 2), 2, null);
                [$erro, $valor] = self::aplicar($nome, $arg, $valor);
                if ($erro !== null) {
                    $erros[$campo] = $erro;
                    continue 2;
                }
            }
            $limpos[$campo] = $valor;
        }

        if ($erros) {
            throw HttpException::validacao($erros);
        }
        return $limpos;
    }

    /** @return array{0: ?string, 1: mixed} [mensagem de erro, valor convertido] */
    private static function aplicar(string $regra, ?string $arg, mixed $valor): array
    {
        switch ($regra) {
            case 'required':
                return [null, $valor];

            case 'string':
                return is_string($valor) ? [null, $valor] : ['Deve ser um texto.', $valor];

            case 'int':
                if (is_int($valor) || (is_string($valor) && preg_match('/^-?\d+$/', $valor))) {
                    return [null, (int) $valor];
                }
                return ['Deve ser um número inteiro.', $valor];

            case 'numeric':
                if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor))) {
                    return [null, (float) $valor];
                }
                return ['Deve ser um número.', $valor];

            case 'bool':
                if (is_bool($valor) || in_array($valor, [0, 1, '0', '1'], true)) {
                    return [null, (bool) $valor];
                }
                return ['Deve ser verdadeiro ou falso.', $valor];

            case 'email':
                return is_string($valor) && filter_var($valor, FILTER_VALIDATE_EMAIL)
                    ? [null, mb_strtolower($valor)]
                    : ['Digite um e-mail válido.', $valor];

            case 'date':
                $data = is_string($valor) ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
                return $data && $data->format('Y-m-d') === $valor
                    ? [null, $valor]
                    : ['Use uma data no formato AAAA-MM-DD.', $valor];

            case 'url':
                return is_string($valor) && filter_var($valor, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $valor)
                    ? [null, $valor]
                    : ['Digite um endereço válido (http ou https).', $valor];

            case 'min':
            case 'max':
                $limite = (float) $arg;
                $medida = is_string($valor) ? mb_strlen($valor) : $valor;
                $ok = $regra === 'min' ? $medida >= $limite : $medida <= $limite;
                if ($ok) {
                    return [null, $valor];
                }
                if (is_string($valor)) {
                    return [$regra === 'min' ? "Use pelo menos {$arg} caracteres." : "Use no máximo {$arg} caracteres.", $valor];
                }
                return [$regra === 'min' ? "O valor mínimo é {$arg}." : "O valor máximo é {$arg}.", $valor];

            case 'in':
                $opcoes = explode(',', (string) $arg);
                return in_array($valor, $opcoes, true)
                    ? [null, $valor]
                    : ['Escolha uma destas opções: ' . implode(', ', $opcoes) . '.', $valor];

            case 'regex':
                return is_string($valor) && preg_match((string) $arg, $valor)
                    ? [null, $valor]
                    : ['Formato inválido.', $valor];
        }

        throw new \LogicException("Regra de validação desconhecida: {$regra}");
    }
}
