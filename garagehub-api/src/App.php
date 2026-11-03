<?php

declare(strict_types=1);

namespace GarageHub;

use GarageHub\Auth\AuthController;
use GarageHub\Auth\LimiteDeLogin;
use GarageHub\Auth\RedefinicaoDeSenha;
use GarageHub\Auth\TokenService;
use GarageHub\Auth\UsuarioRepository;
use GarageHub\Catalogo\CatalogoController;
use GarageHub\Catalogo\FipeComCache;
use GarageHub\Catalogo\FipeIndisponivel;
use GarageHub\Catalogo\FipeParallelum;
use GarageHub\Catalogo\FonteFipe;
use GarageHub\Core\Db;
use GarageHub\Core\Env;
use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Core\Router;
use GarageHub\Correio\Correio;
use GarageHub\Correio\FabricaDeCorreio;
use GarageHub\Garagem\FotoController;
use GarageHub\Garagem\FotoRepository;
use GarageHub\Garagem\FotoStorage;
use GarageHub\Garagem\ModificacaoController;
use GarageHub\Garagem\ModificacaoRepository;
use GarageHub\Garagem\PerfilPublicoController;
use GarageHub\Garagem\VeiculoController;
use GarageHub\Garagem\VeiculoRepository;
use GarageHub\Local\BuscaController;
use GarageHub\Local\CidadeRepository;
use GarageHub\Social\FeedController;
use GarageHub\Social\FeedRepository;
use GarageHub\Social\InteracoesController;
use GarageHub\Social\InteracoesRepository;
use GarageHub\Social\SeguidoresRepository;
use GarageHub\Social\SeguirController;
use Throwable;

/** Monta as rotas e transforma qualquer erro numa resposta JSON. */
final class App
{
    private readonly Router $router;

    public function __construct(Db $db, ?FonteFipe $fipe = null, ?Correio $correio = null)
    {
        $fipe ??= new FipeComCache(new FipeParallelum(), dirname(__DIR__) . '/storage/cache');
        $correio ??= FabricaDeCorreio::doAmbiente();

        $usuarios = new UsuarioRepository($db);
        $tokens = new TokenService($db, $usuarios);
        $veiculos = new VeiculoRepository($db);
        $modificacoes = new ModificacaoRepository($db);

        $fotos = new FotoRepository($db);
        $storage = FotoStorage::doAmbiente();
        $seguidores = new SeguidoresRepository($db);
        $interacoes = new InteracoesRepository($db);

        $cidades = new CidadeRepository($db);
        $auth = new AuthController($usuarios, $tokens, new LimiteDeLogin($db), $cidades, new RedefinicaoDeSenha($db, $usuarios, $correio), $storage);
        $busca = new BuscaController($db, $cidades, $veiculos);
        $garagem = new VeiculoController($veiculos, $modificacoes, $fotos, $storage, $interacoes);
        $mods = new ModificacaoController($veiculos, $modificacoes, $interacoes);
        $fotosCtrl = new FotoController($veiculos, $fotos, $storage);
        $publico = new PerfilPublicoController($usuarios, $veiculos, $modificacoes, $fotos, $seguidores, $interacoes);
        $catalogo = new CatalogoController($fipe);
        $seguir = new SeguirController($db, $usuarios, $seguidores);
        $feed = new FeedController(new FeedRepository($db), $veiculos, $interacoes);
        $interacoesCtrl = new InteracoesController($interacoes);

        $logado = [$tokens->exigir()];
        $talvezLogado = [$tokens->opcional()];

        $r = $this->router = new Router();

        // Autenticação
        $r->post('/api/auth/register', $auth->cadastrar(...));
        $r->post('/api/auth/login', $auth->entrar(...));
        $r->post('/api/auth/forgot-password', $auth->esqueciSenha(...));
        $r->post('/api/auth/reset-password', $auth->redefinirSenha(...));
        $r->post('/api/auth/logout', $auth->sair(...), $logado);
        $r->get('/api/auth/me', $auth->eu(...), $logado);
        $r->put('/api/auth/me', $auth->atualizarPerfil(...), $logado);
        $r->post('/api/auth/me/avatar', $auth->enviarAvatar(...), $logado);
        $r->delete('/api/auth/me/avatar', $auth->removerAvatar(...), $logado);

        // Cidades (IBGE) e busca
        $r->get('/api/cities', $busca->cidades(...));
        $r->get('/api/cities/{id}', $busca->cidade(...));
        $r->get('/api/search', $busca->buscar(...), $talvezLogado);

        // Garagem do usuário logado
        $r->get('/api/vehicles', $garagem->listar(...), $logado);
        $r->post('/api/vehicles', $garagem->criar(...), $logado);
        $r->get('/api/vehicles/{id}', $garagem->mostrar(...), $logado);
        $r->put('/api/vehicles/{id}', $garagem->atualizar(...), $logado);
        $r->delete('/api/vehicles/{id}', $garagem->excluir(...), $logado);

        $r->post('/api/vehicles/{id}/modifications', $mods->criar(...), $logado);
        $r->put('/api/modifications/{id}', $mods->atualizar(...), $logado);
        $r->delete('/api/modifications/{id}', $mods->excluir(...), $logado);

        $r->post('/api/vehicles/{id}/photos', $fotosCtrl->enviar(...), $logado);
        $r->put('/api/photos/{id}/cover', $fotosCtrl->definirCapa(...), $logado);
        $r->delete('/api/photos/{id}', $fotosCtrl->excluir(...), $logado);

        // Catálogo FIPE (só logado, para proteger o limite de consultas do provedor)
        $r->get('/api/catalog/brands', $catalogo->marcas(...), $logado);
        $r->get('/api/catalog/brands/{marca}/models', $catalogo->modelos(...), $logado);
        $r->get('/api/catalog/brands/{marca}/models/{modelo}/years', $catalogo->anos(...), $logado);
        $r->get('/api/catalog/brands/{marca}/models/{modelo}/years/{ano}', $catalogo->detalhe(...), $logado);

        // Social: feed, sugestões e seguir (as rotas fixas vêm antes de /api/users/{username})
        $r->get('/api/feed', $feed->feed(...), $logado);
        $r->get('/api/users/suggestions', $feed->sugestoes(...), $logado);
        $r->post('/api/users/{username}/follow', $seguir->seguirUsuario(...), $logado);
        $r->delete('/api/users/{username}/follow', $seguir->deixarDeSeguirUsuario(...), $logado);
        $r->post('/api/vehicles/{id}/follow', $seguir->seguirVeiculo(...), $logado);
        $r->delete('/api/vehicles/{id}/follow', $seguir->deixarDeSeguirVeiculo(...), $logado);

        // Curtidas e comentários ({tipo} = veiculo ou modificacao). Ler comentários não exige login.
        $r->post('/api/likes/{tipo}/{id}', $interacoesCtrl->curtir(...), $logado);
        $r->delete('/api/likes/{tipo}/{id}', $interacoesCtrl->descurtir(...), $logado);
        $r->get('/api/comments/{tipo}/{id}', $interacoesCtrl->listar(...), $talvezLogado);
        $r->post('/api/comments/{tipo}/{id}', $interacoesCtrl->comentar(...), $logado);
        $r->delete('/api/comments/{id}', $interacoesCtrl->excluirComentario(...), $logado);

        // Páginas públicas
        $r->get('/api/users/{username}', $publico->perfil(...), $talvezLogado);
        $r->get('/api/users/{username}/vehicles/{slug}', $publico->veiculo(...), $talvezLogado);
    }

    public function processar(Request $request): Response
    {
        $cors = $this->headersCors($request);

        if ($request->method === 'OPTIONS') {
            $resposta = Response::semConteudo();
        } else {
            try {
                $resposta = $this->router->despachar($request);
            } catch (HttpException $e) {
                $resposta = Response::erro($e->status, $e->getMessage(), $e->campos);
            } catch (FipeIndisponivel $e) {
                error_log("[GarageHub] FIPE indisponível: {$e->getMessage()}");
                $resposta = Response::erro(503, $e->limite
                    ? 'O catálogo FIPE atingiu o limite de consultas por hoje. Preencha os dados manualmente ou tente mais tarde.'
                    : 'O catálogo FIPE está fora do ar. Preencha os dados manualmente ou tente mais tarde.');
            } catch (Throwable $e) {
                // Detalhes só no log; o cliente recebe uma mensagem genérica
                error_log("[GarageHub] {$e}");
                $mensagem = Env::bool('APP_DEBUG') ? $e->getMessage() : 'Erro interno. Tente novamente em instantes.';
                $resposta = Response::erro(500, $mensagem);
            }
        }

        $resposta->headers += $cors;
        return $resposta;
    }

    /** Libera o Angular (e outros endereços da lista CORS_ORIGINS) a chamar a API. */
    private function headersCors(Request $request): array
    {
        $origem = $request->header('origin');
        $permitidas = array_map('trim', explode(',', Env::get('CORS_ORIGINS', 'http://localhost:4200')));
        if ($origem === null || !in_array($origem, $permitidas, true)) {
            return [];
        }
        return [
            'Access-Control-Allow-Origin' => $origem,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];
    }
}
