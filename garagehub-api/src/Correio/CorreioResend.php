<?php

declare(strict_types=1);

namespace GarageHub\Correio;

use RuntimeException;

/**
 * Envio pela API HTTP do Resend (resend.com). Serve onde a porta SMTP é bloqueada,
 * como no Railway nos planos Free e Hobby.
 */
final class CorreioResend implements Correio
{
    private const URL = 'https://api.resend.com/emails';

    public function __construct(
        private readonly string $chave,
        private readonly string $remetente,
        private readonly string $nomeRemetente,
    ) {
    }

    public function enviar(Mensagem $mensagem): void
    {
        if (!filter_var($mensagem->para, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Destinatário inválido.');
        }

        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->chave}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'from' => "{$this->nomeRemetente} <{$this->remetente}>",
                'to' => [$mensagem->para],
                'subject' => $mensagem->assunto,
                'html' => $mensagem->html,
                'text' => $mensagem->texto,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        // No Windows o PHP não acha os certificados sozinho (mesmo ajuste do cliente da FIPE)
        if (!ini_get('curl.cainfo') && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }

        $corpo = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $erro = curl_error($ch);

        if ($corpo === false) {
            throw new RuntimeException("Resend: sem resposta ({$erro})");
        }
        if ($status < 200 || $status >= 300) {
            $mensagemErro = json_decode((string) $corpo, true)['message'] ?? substr((string) $corpo, 0, 200);
            throw new RuntimeException("Resend: HTTP {$status}: {$mensagemErro}");
        }
    }
}
