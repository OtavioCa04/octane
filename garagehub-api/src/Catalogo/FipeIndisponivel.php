<?php

declare(strict_types=1);

namespace GarageHub\Catalogo;

use RuntimeException;

/** O provedor da FIPE falhou, demorou demais ou atingiu o limite de consultas. */
final class FipeIndisponivel extends RuntimeException
{
    public function __construct(string $motivo, public readonly bool $limite = false)
    {
        parent::__construct($motivo);
    }
}
