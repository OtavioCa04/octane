<?php

declare(strict_types=1);

namespace GarageHub\Correio;

/** Envio de e-mail. Em produção vai por SMTP; em desenvolvimento, só grava num arquivo (ver FabricaDeCorreio). */
interface Correio
{
    public function enviar(Mensagem $mensagem): void;
}
