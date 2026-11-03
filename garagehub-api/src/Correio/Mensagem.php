<?php

declare(strict_types=1);

namespace GarageHub\Correio;

final class Mensagem
{
    public function __construct(
        public readonly string $para,
        public readonly string $assunto,
        public readonly string $texto,
        public readonly string $html,
    ) {
    }
}
