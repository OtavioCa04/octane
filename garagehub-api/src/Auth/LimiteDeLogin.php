<?php

declare(strict_types=1);

namespace GarageHub\Auth;

use GarageHub\Core\Db;
use GarageHub\Core\HttpException;

/** Bloqueia novas tentativas depois de várias senhas erradas para o mesmo e-mail e IP. */
final class LimiteDeLogin
{
    private const MAX_TENTATIVAS = 5;
    private const JANELA_MINUTOS = 15;

    public function __construct(private readonly Db $db)
    {
    }

    public function verificar(string $email, string $ip): void
    {
        $falhas = (int) $this->db->valor(
            'SELECT COUNT(*) FROM login_tentativas WHERE chave = ? AND criado_em > ?',
            [$this->chave($email, $ip), gmdate('Y-m-d H:i:s', time() - self::JANELA_MINUTOS * 60)],
        );
        if ($falhas >= self::MAX_TENTATIVAS) {
            throw new HttpException(429, 'Muitas tentativas. Aguarde alguns minutos e tente de novo.');
        }
    }

    public function registrarFalha(string $email, string $ip): void
    {
        $this->db->inserir('login_tentativas', ['chave' => $this->chave($email, $ip), 'criado_em' => Db::agora()]);
    }

    public function limpar(string $email, string $ip): void
    {
        $this->db->executar('DELETE FROM login_tentativas WHERE chave = ?', [$this->chave($email, $ip)]);
    }

    private function chave(string $email, string $ip): string
    {
        return hash('sha256', mb_strtolower($email) . '|' . $ip);
    }
}
