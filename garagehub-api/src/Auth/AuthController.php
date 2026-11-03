<?php

declare(strict_types=1);

namespace GarageHub\Auth;

use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Core\Validator;
use GarageHub\Garagem\FotoStorage;
use GarageHub\Local\CidadeRepository;

final class AuthController
{
    // Nomes que conflitam com rotas do site (garagehub.com/<username>)
    private const USERNAMES_RESERVADOS = [
        'api', 'admin', 'entrar', 'sair', 'cadastro', 'cadastrar', 'explorar', 'buscar', 'configuracoes',
        'conta', 'ajuda', 'termos', 'privacidade', 'sobre', 'garagehub', 'octane', 'minha-garagem', 'novo', 'garagem', 'feed', 'suggestions',
        'esqueci-senha', 'redefinir-senha', 'feedback', 'suporte', 'contato',
    ];

    // Hash bcrypt de uma senha qualquer, com o mesmo custo de PASSWORD_DEFAULT
    private const HASH_DE_REFERENCIA = '$2y$12$n63GJ2eHF2rQiYRi16uZb.mzYeHP5xuPtnc0Yeoz6A5idGUa0mxXW';

    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly TokenService $tokens,
        private readonly LimiteDeLogin $limite,
        private readonly CidadeRepository $cidades,
        private readonly RedefinicaoDeSenha $redefinicao,
        private readonly FotoStorage $storage,
    ) {
    }

    /** PUT /api/auth/me: nome, bio, cidade (id do IBGE) e perfil privado. */
    public function atualizarPerfil(Request $request): Response
    {
        $dados = Validator::validar($request->body, [
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'bio' => ['string', 'max:500'],
            'cidadeId' => ['int', 'min:1'],
            'perfilPrivado' => ['bool'],
        ]);
        if ($dados['cidadeId'] !== null && $this->cidades->porId($dados['cidadeId']) === null) {
            throw HttpException::validacao(['cidadeId' => 'Escolha uma cidade da lista.']);
        }

        $id = (int) $request->usuario['id'];
        $this->usuarios->atualizar($id, [
            'nome' => $dados['nome'],
            'bio' => $dados['bio'],
            'cidade_id' => $dados['cidadeId'],
            'perfil_privado' => (int) ($dados['perfilPrivado'] ?? false),
        ]);
        return Response::json(UsuarioRepository::apresentar($this->usuarios->porId($id)));
    }

    public function cadastrar(Request $request): Response
    {
        $dados = Validator::validar($request->body, [
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_.]{3,30}$/'],
            'email' => ['required', 'email', 'max:180'],
            // bcrypt usa só os primeiros 72 bytes
            'senha' => ['required', 'string', 'min:8', 'max:72'],
            'cidade' => ['string', 'max:120'],
        ]);
        $dados['username'] = mb_strtolower($dados['username']);

        $erros = [];
        if (in_array($dados['username'], self::USERNAMES_RESERVADOS, true)) {
            $erros['username'] = 'Esse nome de usuário não está disponível.';
        } elseif ($this->usuarios->porUsername($dados['username'])) {
            $erros['username'] = 'Esse nome de usuário já está em uso.';
        }
        if ($this->usuarios->porEmail($dados['email'])) {
            $erros['email'] = 'Já existe uma conta com esse e-mail.';
        }
        if ($erros) {
            throw HttpException::validacao($erros);
        }

        $id = $this->usuarios->criar([
            'nome' => $dados['nome'],
            'username' => $dados['username'],
            'email' => $dados['email'],
            'senha_hash' => password_hash($dados['senha'], PASSWORD_DEFAULT),
            'cidade' => $dados['cidade'],
        ]);

        return Response::criado([
            'usuario' => UsuarioRepository::apresentar($this->usuarios->porId($id)),
            'token' => $this->tokens->criar($id),
        ]);
    }

    public function entrar(Request $request): Response
    {
        $dados = Validator::validar($request->body, [
            'email' => ['required', 'email'],
            'senha' => ['required', 'string', 'max:72'],
        ]);

        $this->limite->verificar($dados['email'], $request->ip);

        $usuario = $this->usuarios->porEmail($dados['email']);
        // Verifica um hash qualquer quando o e-mail não existe, para o tempo de resposta
        // não revelar quais e-mails têm conta.
        $hash = $usuario['senha_hash'] ?? self::HASH_DE_REFERENCIA;
        if (!password_verify($dados['senha'], $hash) || $usuario === null) {
            $this->limite->registrarFalha($dados['email'], $request->ip);
            throw new HttpException(401, 'E-mail ou senha incorretos.');
        }

        $this->limite->limpar($dados['email'], $request->ip);
        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
            $this->usuarios->atualizarSenhaHash((int) $usuario['id'], password_hash($dados['senha'], PASSWORD_DEFAULT));
        }

        return Response::json([
            'usuario' => UsuarioRepository::apresentar($usuario),
            'token' => $this->tokens->criar((int) $usuario['id']),
        ]);
    }

    /** POST /api/auth/me/avatar com multipart/form-data, campo "avatar". Troca a foto anterior. */
    public function enviarAvatar(Request $request): Response
    {
        $upload = $request->arquivos['avatar'] ?? throw HttpException::validacao(['avatar' => 'Escolha uma foto.']);
        $id = (int) $request->usuario['id'];
        $arquivo = $this->storage->salvarAvatar($id, $upload);

        $anterior = $request->usuario['avatar'] ?? null;
        $this->usuarios->atualizar($id, ['avatar' => $arquivo]);
        if ($anterior) {
            $this->storage->excluir($anterior);
        }
        return Response::json(UsuarioRepository::apresentar($this->usuarios->porId($id)));
    }

    /** DELETE /api/auth/me/avatar: volta para as iniciais. */
    public function removerAvatar(Request $request): Response
    {
        $id = (int) $request->usuario['id'];
        if ($anterior = $request->usuario['avatar'] ?? null) {
            $this->usuarios->atualizar($id, ['avatar' => null]);
            $this->storage->excluir($anterior);
        }
        return Response::json(UsuarioRepository::apresentar($this->usuarios->porId($id)));
    }

    /** POST /api/auth/forgot-password: responde igual exista a conta ou não. */
    public function esqueciSenha(Request $request): Response
    {
        $dados = Validator::validar($request->body, ['email' => ['required', 'email', 'max:180']]);
        $this->redefinicao->pedir($dados['email'], $request->ip);
        return Response::semConteudo();
    }

    /** POST /api/auth/reset-password: troca a senha com o código do e-mail e encerra as sessões abertas. */
    public function redefinirSenha(Request $request): Response
    {
        $dados = Validator::validar($request->body, [
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'senha' => ['required', 'string', 'min:8', 'max:72'],
        ]);
        $this->redefinicao->redefinir($dados['token'], $dados['senha']);
        return Response::semConteudo();
    }

    public function sair(Request $request): Response
    {
        $this->tokens->revogar((string) $request->token);
        return Response::semConteudo();
    }

    public function eu(Request $request): Response
    {
        return Response::json(UsuarioRepository::apresentar($request->usuario));
    }
}
