<?php

declare(strict_types=1);

namespace GarageHub\Core;

final class Texto
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
    ];

    /**
     * "São José" → "sao jose": minúsculas e sem acento.
     * Não usa iconv: no Windows ele translitera "ã" como "~a".
     */
    /**
     * Escapa % e _ para um LIKE literal. Usa "!" como escape (com ESCAPE '!' no SQL) porque a barra
     * invertida tem significados diferentes no MySQL e no SQLite.
     */
    public static function paraLike(string $texto): string
    {
        return strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }

    public static function semAcento(string $texto): string
    {
        return strtr(mb_strtolower($texto, 'UTF-8'), self::ACENTOS);
    }
}
