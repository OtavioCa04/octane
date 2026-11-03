<?php

declare(strict_types=1);

namespace GarageHub\Auth;

use GarageHub\Core\Db;
use GarageHub\Garagem\Apresentador;
use GarageHub\Local\CidadeRepository;

final class UsuarioRepository
{
    // Traz junto o nome e a UF da cidade estruturada (quando a pessoa escolheu uma)
    private const SELECT = 'SELECT u.*, c.nome AS cidade_nome, c.uf AS cidade_uf
        FROM usuarios u LEFT JOIN cidades c ON c.id = u.cidade_id';

    public function __construct(private readonly Db $db)
    {
    }

    public function porId(int $id): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE u.id = ?', [$id]);
    }

    public function porEmail(string $email): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE u.email = ?', [mb_strtolower($email)]);
    }

    public function porUsername(string $username): ?array
    {
        return $this->db->um(self::SELECT . ' WHERE u.username = ?', [mb_strtolower($username)]);
    }

    public function criar(array $dados): int
    {
        $agora = Db::agora();
        return $this->db->inserir('usuarios', $dados + ['criado_em' => $agora, 'atualizado_em' => $agora]);
    }

    public function atualizar(int $id, array $dados): void
    {
        $this->db->atualizar('usuarios', $id, $dados + ['atualizado_em' => Db::agora()]);
    }

    public function atualizarSenhaHash(int $id, string $hash): void
    {
        $this->atualizar($id, ['senha_hash' => $hash]);
    }

    /** "Campinas, SP" a partir da cidade estruturada; senão, o texto livre antigo. */
    public static function cidade(array $u): ?string
    {
        return CidadeRepository::formatar($u['cidade_nome'] ?? null, $u['cidade_uf'] ?? null) ?? $u['cidade'];
    }

    /** Dados do usuário para ele mesmo (nunca inclui a senha). */
    public static function apresentar(array $u): array
    {
        return [
            'id' => (int) $u['id'],
            'nome' => $u['nome'],
            'username' => $u['username'],
            'email' => $u['email'],
            'cidade' => self::cidade($u),
            'cidadeId' => $u['cidade_id'] !== null ? (int) $u['cidade_id'] : null,
            'uf' => $u['cidade_uf'] ?? null,
            'bio' => $u['bio'],
            'avatarUrl' => Apresentador::avatarUrl($u['avatar'] ?? null),
            'perfilPrivado' => (bool) $u['perfil_privado'],
            'criadoEm' => $u['criado_em'],
        ];
    }
}
