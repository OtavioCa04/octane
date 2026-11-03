<?php

declare(strict_types=1);

namespace GarageHub\Auth;

use GarageHub\Core\Db;
use GarageHub\Core\Env;
use GarageHub\Core\HttpException;
use GarageHub\Correio\Correio;
use GarageHub\Correio\Mensagem;

/**
 * "Esqueci minha senha": manda por e-mail um link com código de uso único e troca a senha
 * quando o código volta. A resposta do pedido é sempre a mesma, exista a conta ou não.
 */
final class RedefinicaoDeSenha
{
    private const VALIDADE_MINUTOS = 60;
    private const PEDIDOS_POR_HORA_CONTA = 3;
    private const PEDIDOS_POR_HORA_IP = 10;

    public function __construct(
        private readonly Db $db,
        private readonly UsuarioRepository $usuarios,
        private readonly Correio $correio,
    ) {
    }

    public function pedir(string $email, string $ip): void
    {
        $this->limitarPorIp($ip);

        $usuario = $this->usuarios->porEmail($email);
        if ($usuario === null) {
            return;
        }
        $id = (int) $usuario['id'];
        $recentes = (int) $this->db->valor(
            'SELECT COUNT(*) FROM redefinicoes_senha WHERE usuario_id = ? AND criado_em > ?',
            [$id, gmdate('Y-m-d H:i:s', time() - 3600)],
        );
        if ($recentes >= self::PEDIDOS_POR_HORA_CONTA) {
            return;
        }

        // Um pedido novo invalida os anteriores ainda não usados
        $this->db->executar('UPDATE redefinicoes_senha SET expira_em = ? WHERE usuario_id = ? AND usado_em IS NULL', [Db::agora(), $id]);

        $token = bin2hex(random_bytes(32));
        $this->db->inserir('redefinicoes_senha', [
            'usuario_id' => $id,
            'token_hash' => hash('sha256', $token),
            'expira_em' => gmdate('Y-m-d H:i:s', time() + self::VALIDADE_MINUTOS * 60),
            'criado_em' => Db::agora(),
        ]);

        $link = rtrim((string) Env::get('APP_URL', 'http://localhost:4200'), '/') . '/redefinir-senha?token=' . $token;
        $this->correio->enviar($this->mensagem($usuario, $link));
    }

    /** Troca a senha e derruba todas as sessões abertas da conta. */
    public function redefinir(string $token, string $senha): void
    {
        $pedido = $this->db->um(
            'SELECT id, usuario_id FROM redefinicoes_senha WHERE token_hash = ? AND usado_em IS NULL AND expira_em > ?',
            [hash('sha256', $token), Db::agora()],
        );
        if ($pedido === null) {
            throw new HttpException(400, 'Esse link de redefinição é inválido ou expirou. Peça um novo.');
        }

        $usuarioId = (int) $pedido['usuario_id'];
        $this->db->pdo()->beginTransaction();
        try {
            $this->usuarios->atualizarSenhaHash($usuarioId, password_hash($senha, PASSWORD_DEFAULT));
            $this->db->executar('UPDATE redefinicoes_senha SET usado_em = ? WHERE id = ?', [Db::agora(), $pedido['id']]);
            $this->db->executar('DELETE FROM tokens WHERE usuario_id = ?', [$usuarioId]);
            $this->db->pdo()->commit();
        } catch (\Throwable $e) {
            $this->db->pdo()->rollBack();
            throw $e;
        }
    }

    private function limitarPorIp(string $ip): void
    {
        $chave = hash('sha256', 'redefinicao|' . $ip);
        $pedidos = (int) $this->db->valor(
            'SELECT COUNT(*) FROM login_tentativas WHERE chave = ? AND criado_em > ?',
            [$chave, gmdate('Y-m-d H:i:s', time() - 3600)],
        );
        if ($pedidos >= self::PEDIDOS_POR_HORA_IP) {
            throw new HttpException(429, 'Muitos pedidos. Aguarde um pouco e tente de novo.');
        }
        $this->db->inserir('login_tentativas', ['chave' => $chave, 'criado_em' => Db::agora()]);
    }

    private function mensagem(array $usuario, string $link): Mensagem
    {
        $nome = explode(' ', trim((string) $usuario['nome']))[0];
        $nomeHtml = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $linkHtml = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $validade = self::VALIDADE_MINUTOS;

        $texto = <<<TXT
            Oi, {$nome}!

            Recebemos um pedido para redefinir a senha da sua conta na Octane.
            Para criar uma senha nova, abra este link (vale por {$validade} minutos):

            {$link}

            Se não foi você, ignore este e-mail. Sua senha continua a mesma.
            TXT;

        $html = <<<HTML
            <div style="background:#0b0d11;padding:32px 16px;font-family:Arial,Helvetica,sans-serif">
              <div style="max-width:480px;margin:0 auto;background:#14171f;border:1px solid #23272f;border-radius:14px;padding:28px;color:#f2f3f5">
                <p style="margin:0 0 20px;font-size:14px;letter-spacing:3px;color:#9aa1ac">OCTANE</p>
                <p style="margin:0 0 12px;font-size:20px;font-weight:bold">Oi, {$nomeHtml}!</p>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#c9ced6">Recebemos um pedido para redefinir a senha da sua conta. O link vale por {$validade} minutos.</p>
                <a href="{$linkHtml}" style="display:inline-block;background:#c9773f;color:#0b0d11;font-weight:bold;font-size:15px;text-decoration:none;padding:13px 22px;border-radius:10px">Criar senha nova</a>
                <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#9aa1ac">Se não foi você, ignore este e-mail. Sua senha continua a mesma.</p>
              </div>
            </div>
            HTML;

        return new Mensagem($usuario['email'], 'Redefinir sua senha na Octane', $texto, $html);
    }
}
