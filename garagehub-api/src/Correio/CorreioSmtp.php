<?php

declare(strict_types=1);

namespace GarageHub\Correio;

use RuntimeException;

/**
 * Cliente SMTP mínimo, sem dependências: SSL direto (porta 465) ou STARTTLS (587), com AUTH LOGIN.
 * Serve para o e-mail da hospedagem, Brevo, Amazon SES, Gmail com senha de app etc.
 */
final class CorreioSmtp implements Correio
{
    private const TEMPO_LIMITE = 15;

    /** @var resource|null */
    private $conexao = null;

    public function __construct(
        private readonly string $host,
        private readonly int $porta,
        private readonly string $usuario,
        private readonly string $senha,
        private readonly string $seguranca, // ssl, tls ou nenhuma
        private readonly string $remetente,
        private readonly string $nomeRemetente,
    ) {
    }

    public function enviar(Mensagem $mensagem): void
    {
        // Bloqueia injeção de cabeçalhos pelo endereço
        if (!filter_var($mensagem->para, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Destinatário inválido.');
        }

        try {
            $this->conectar();
            $this->comando("MAIL FROM:<{$this->remetente}>", [250]);
            $this->comando("RCPT TO:<{$mensagem->para}>", [250, 251]);
            $this->comando('DATA', [354]);
            $this->comando($this->montar($mensagem) . "\r\n.", [250]);
            $this->comando('QUIT', [221]);
        } finally {
            if (is_resource($this->conexao)) {
                fclose($this->conexao);
            }
            $this->conexao = null;
        }
    }

    private function conectar(): void
    {
        $endereco = ($this->seguranca === 'ssl' ? 'ssl://' : 'tcp://') . "{$this->host}:{$this->porta}";
        $contexto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->host]]);
        $conexao = @stream_socket_client($endereco, $codigo, $erro, self::TEMPO_LIMITE, STREAM_CLIENT_CONNECT, $contexto);
        if ($conexao === false) {
            throw new RuntimeException("SMTP: não conectou em {$endereco} ({$erro})");
        }
        stream_set_timeout($conexao, self::TEMPO_LIMITE);
        $this->conexao = $conexao;

        $this->esperar([220]);
        $nomeLocal = gethostname() ?: 'localhost';
        $this->comando("EHLO {$nomeLocal}", [250]);

        if ($this->seguranca === 'tls') {
            $this->comando('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('SMTP: falha ao iniciar TLS.');
            }
            $this->comando("EHLO {$nomeLocal}", [250]);
        }

        if ($this->usuario !== '') {
            $this->comando('AUTH LOGIN', [334]);
            $this->comando(base64_encode($this->usuario), [334]);
            $this->comando(base64_encode($this->senha), [235], ocultar: true);
        }
    }

    /** Envia um comando e confere o código de resposta. */
    private function comando(string $linha, array $esperado, bool $ocultar = false): void
    {
        fwrite($this->conexao, $linha . "\r\n");
        $this->esperar($esperado, $ocultar ? '(senha)' : strtok($linha, "\r\n"));
    }

    private function esperar(array $codigos, string $contexto = 'conexão'): void
    {
        $resposta = '';
        // Respostas de várias linhas usam "250-..." e terminam em "250 ..."
        while (($linha = fgets($this->conexao, 1024)) !== false) {
            $resposta .= $linha;
            if (strlen($linha) < 4 || $linha[3] === ' ') {
                break;
            }
        }
        $codigo = (int) substr($resposta, 0, 3);
        if (!in_array($codigo, $codigos, true)) {
            throw new RuntimeException("SMTP: resposta inesperada a \"{$contexto}\": " . trim($resposta));
        }
    }

    /** Monta a mensagem MIME (texto + HTML), com o ponto inicial das linhas escapado. */
    private function montar(Mensagem $m): string
    {
        $fronteira = 'octane-' . bin2hex(random_bytes(8));
        $dominio = substr(strrchr($this->remetente, '@') ?: '@octane.local', 1);
        $cabecalhos = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->codificar($this->nomeRemetente) . " <{$this->remetente}>",
            "To: <{$m->para}>",
            'Subject: ' . $this->codificar($m->assunto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@{$dominio}>",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$fronteira}\"",
        ];
        $partes = '';
        foreach (['text/plain' => $m->texto, 'text/html' => $m->html] as $tipo => $conteudo) {
            $partes .= "--{$fronteira}\r\nContent-Type: {$tipo}; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . rtrim(chunk_split(base64_encode($conteudo), 76, "\r\n")) . "\r\n";
        }
        $corpo = implode("\r\n", $cabecalhos) . "\r\n\r\n" . $partes . "--{$fronteira}--";
        return preg_replace('/^\./m', '..', $corpo);
    }

    private function codificar(string $texto): string
    {
        return preg_match('/[^\x20-\x7E]/', $texto) ? '=?UTF-8?B?' . base64_encode($texto) . '?=' : $texto;
    }
}
