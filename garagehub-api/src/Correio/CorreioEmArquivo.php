<?php

declare(strict_types=1);

namespace GarageHub\Correio;

/** Desenvolvimento: em vez de enviar, grava cada e-mail como .txt para abrir e clicar no link. */
final class CorreioEmArquivo implements Correio
{
    public function __construct(private readonly string $pasta)
    {
    }

    public function enviar(Mensagem $mensagem): void
    {
        if (!is_dir($this->pasta)) {
            mkdir($this->pasta, 0775, true);
        }
        $arquivo = $this->pasta . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
        file_put_contents($arquivo, "Para: {$mensagem->para}\nAssunto: {$mensagem->assunto}\n\n{$mensagem->texto}\n");
        error_log("[GarageHub] E-mail para {$mensagem->para} gravado em {$arquivo}");
    }
}
