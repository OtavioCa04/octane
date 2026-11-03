<?php

declare(strict_types=1);

namespace GarageHub\Correio;

use GarageHub\Core\Env;

final class FabricaDeCorreio
{
    /**
     * MAIL_DRIVER=resend usa a API do Resend (RESEND_API_KEY); smtp usa as variáveis MAIL_*;
     * qualquer outro valor grava em storage/mail (desenvolvimento).
     */
    public static function doAmbiente(): Correio
    {
        $driver = Env::get('MAIL_DRIVER');
        if ($driver === 'resend') {
            return new CorreioResend(
                chave: (string) Env::get('RESEND_API_KEY'),
                remetente: (string) Env::get('MAIL_FROM_ADDRESS'),
                nomeRemetente: (string) Env::get('MAIL_FROM_NAME', 'Octane'),
            );
        }
        if ($driver !== 'smtp') {
            return new CorreioEmArquivo(dirname(__DIR__, 2) . '/storage/mail');
        }
        return new CorreioSmtp(
            host: (string) Env::get('MAIL_HOST'),
            porta: (int) Env::get('MAIL_PORT', '587'),
            usuario: (string) Env::get('MAIL_USERNAME'),
            senha: (string) Env::get('MAIL_PASSWORD'),
            seguranca: (string) Env::get('MAIL_ENCRYPTION', 'tls'),
            remetente: (string) Env::get('MAIL_FROM_ADDRESS'),
            nomeRemetente: (string) Env::get('MAIL_FROM_NAME', 'Octane'),
        );
    }
}
