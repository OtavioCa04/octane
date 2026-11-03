<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\Texto;

final class Slug
{
    /** "Opala SS 4.1" → "opala-ss-4-1"; "Brasília" → "brasilia" */
    public static function de(string $texto): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', Texto::semAcento($texto)), '-');
    }
}
