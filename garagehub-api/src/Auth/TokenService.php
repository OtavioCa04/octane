<?php

declare(strict_types=1);

namespace GarageHub\Auth;

use GarageHub\Core\Db;
use GarageHub\Core\HttpException;
use GarageHub\Core\Request;

/**
 * Tokens de acesso (Bearer). O cliente recebe o token uma vez no login;
 * o banco guarda só o hash SHA-256, então um vazamento do banco não expõe tokens válidos.
 */
final class TokenService
{
    private const VALIDADE_DIAS = 30;

    public function __construct(
        private readonly Db $db,
        private readonly UsuarioRepository $usuarios,
    ) {
    }

    public function criar(int $usuarioId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->inserir('tokens', [
            'usuario_id' => $usuarioId,
            'token_hash' => hash('sha256', $token),
            'expira_em' => gmdate('Y-m-d H:i:s', time() + self::VALIDADE_DIAS * 86400),
            'criado_em' => Db::agora(),
        ]);
        return $token;
    }

    public function usuarioDoToken(string $token): ?array
    {
        $linha = $this->db->um(
            'SELECT id, usuario_id FROM tokens WHERE token_hash = ? AND expira_em > ?',
            [hash('sha256', $token), Db::agora()],
        );
        if ($linha === null) {
            return null;
        }
        $this->db->executar('UPDATE tokens SET ultimo_uso_em = ? WHERE id = ?', [Db::agora(), $linha['id']]);
        return $this->usuarios->porId((int) $linha['usuario_id']);
    }

    public function revogar(string $token): void
    {
        $this->db->executar('DELETE FROM tokens WHERE token_hash = ?', [hash('sha256', $token)]);
    }

    /** Middleware: exige token válido. */
    public function exigir(): callable
    {
        return function (Request $request): void {
            $token = $request->bearerToken();
            $usuario = $token ? $this->usuarioDoToken($token) : null;
            if ($usuario === null) {
                throw HttpException::naoAutenticado();
            }
            $request->usuario = $usuario;
            $request->token = $token;
        };
    }

    /** Middleware: identifica o usuário se houver token, sem exigir. */
    public function opcional(): callable
    {
        return function (Request $request): void {
            $token = $request->bearerToken();
            if ($token && ($usuario = $this->usuarioDoToken($token))) {
                $request->usuario = $usuario;
                $request->token = $token;
            }
        };
    }
}
