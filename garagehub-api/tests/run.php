<?php

declare(strict_types=1);

/**
 * Testes das regras principais da API, sem dependências.
 * Cada teste roda num banco SQLite novo, em memória, e chama a API direto (sem servidor).
 *
 * Uso: php -d extension=pdo_sqlite tests/run.php
 *
 * Para rodar os mesmos testes num MySQL/MariaDB (o banco de produção), aponte para um banco
 * VAZIO e descartável; ele é apagado e recriado a cada teste:
 *   TESTE_MYSQL=1 DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=octane_testes DB_USERNAME=... DB_PASSWORD=... php tests/run.php
 */

use GarageHub\App;
use GarageHub\Catalogo\CatalogoController;
use GarageHub\Catalogo\FipeComCache;
use GarageHub\Catalogo\FipeIndisponivel;
use GarageHub\Catalogo\FonteFipe;
use GarageHub\Core\Db;
use GarageHub\Core\Migrador;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Correio\Correio;
use GarageHub\Correio\Mensagem;
use GarageHub\Local\CidadeRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

define('TESTE_MYSQL', (bool) getenv('TESTE_MYSQL'));

if (TESTE_MYSQL && !extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "TESTE_MYSQL precisa da extensão pdo_mysql.\n");
    exit(1);
}
if (!TESTE_MYSQL && !extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Os testes usam SQLite. Rode com: php -d extension=pdo_sqlite tests/run.php\n");
    exit(1);
}

$_ENV['CORS_ORIGINS'] = 'http://localhost:4200';
// Fotos dos testes vão para uma pasta temporária, apagada no fim
$_ENV['UPLOADS_DIR'] = sys_get_temp_dir() . '/garagehub-teste-uploads-' . bin2hex(random_bytes(4));

// JPEG mínimo (1x1) e a mesma imagem com um bloco EXIF contendo "GPS"
const JPEG_TESTE = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

function jpegComGps(): string
{
    $jpg = base64_decode(JPEG_TESTE);
    $exif = "Exif\0\0GPS-LAT-23.5-LON-47.0";
    return "\xFF\xD8\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpg, 2);
}

/** Simula um item de $_FILES com o conteúdo dado. */
function upload(string $conteudo): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'gh');
    file_put_contents($tmp, $conteudo);
    return ['name' => 'foto.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($conteudo)];
}

final class Cliente
{
    public function __construct(private readonly App $app, private readonly ?Db $db = null)
    {
    }

    /** Acesso direto ao banco, para preparar cenários que a API não expõe (ex.: perfil privado). */
    public function db(): Db
    {
        return $this->db ?? throw new LogicException('Cliente criado sem banco.');
    }

    public function chamar(string $method, string $path, array $body = [], ?string $token = null, string $ip = '10.0.0.1', array $arquivos = [], array $query = []): Response
    {
        $headers = $token ? ['authorization' => "Bearer {$token}"] : [];
        return $this->app->processar(new Request($method, $path, $body, $headers, $query, $ip, $arquivos));
    }

    public function enviarFoto(int $veiculoId, string $token, ?string $conteudo = null): Response
    {
        return $this->chamar('POST', "/api/vehicles/{$veiculoId}/photos", [], $token, '10.0.0.1', ['foto' => upload($conteudo ?? base64_decode(JPEG_TESTE))]);
    }

    /** Cadastra um usuário e devolve o token. */
    public function cadastrar(string $username, ?string $email = null): string
    {
        $r = $this->chamar('POST', '/api/auth/register', [
            'nome' => 'Pessoa ' . ucfirst($username),
            'username' => $username,
            'email' => $email ?? "{$username}@exemplo.com",
            'senha' => 'senha-de-teste',
        ]);
        igual(201, $r->status, 'cadastro de ' . $username);
        return $r->corpo['data']['token'];
    }
}

/** FIPE de mentira: respostas fixas no formato do Parallelum, ou falha quando $fora = true. */
final class FipeFalsa implements FonteFipe
{
    public int $chamadas = 0;

    public function __construct(public bool $fora = false, public bool $limite = false)
    {
    }

    public function marcas(): array
    {
        return $this->responder([['code' => '59', 'name' => 'VW - VolksWagen'], ['code' => '7', 'name' => 'BMW'], ['code' => '23', 'name' => 'GM - Chevrolet']]);
    }

    public function modelos(string $marcaId): array
    {
        return $this->responder([['code' => '6600', 'name' => 'Golf GTi 2.0 TSI 220cv Aut.']]);
    }

    public function anos(string $marcaId, string $modeloId): array
    {
        return $this->responder([['code' => '32000-1', 'name' => '32000 Gasolina'], ['code' => '2016-1', 'name' => '2016 Gasolina']]);
    }

    public function detalhe(string $marcaId, string $modeloId, string $anoId): array
    {
        return $this->responder([
            'vehicleType' => 1, 'price' => 'R$ 104.300,00', 'brand' => 'VW - VolksWagen', 'model' => 'Golf GTi 2.0 TSI 220cv Aut.',
            'modelYear' => 2016, 'fuel' => 'Gasolina', 'codeFipe' => '005340-6', 'referenceMonth' => 'outubro de 2026', 'fuelAcronym' => 'G',
        ]);
    }

    private function responder(array $dados): array
    {
        $this->chamadas++;
        if ($this->fora) {
            throw new FipeIndisponivel('fora do ar (teste)', $this->limite);
        }
        return $dados;
    }
}

/** Guarda os e-mails "enviados" para o teste ler. */
final class CorreioFalso implements Correio
{
    /** @var Mensagem[] */
    public array $enviados = [];

    public function enviar(Mensagem $mensagem): void
    {
        $this->enviados[] = $mensagem;
    }

    /** Código do link do último e-mail. */
    public function ultimoToken(): string
    {
        preg_match('/token=([a-f0-9]{64})/', end($this->enviados)->texto, $m);
        return $m[1];
    }
}

function novoCliente(?FonteFipe $fipe = null, ?CorreioFalso $correio = null): Cliente
{
    if (TESTE_MYSQL) {
        $db = Db::doAmbiente();
        $db->executar('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->todos('SHOW TABLES') as $linha) {
            $db->executar('DROP TABLE `' . current($linha) . '`');
        }
        $db->executar('SET FOREIGN_KEY_CHECKS = 1');
    } else {
        $db = new Db('sqlite', ['database' => ':memory:']);
    }
    (new Migrador($db, dirname(__DIR__) . '/database/migrations'))->migrar();
    return new Cliente(new App($db, $fipe ?? new FipeFalsa(), $correio ?? new CorreioFalso()), $db);
}

/** Algumas cidades do IBGE para os testes (a lista completa tem mais de 5 mil). */
function comCidades(Cliente $c): void
{
    (new CidadeRepository($c->db()))->importar([
        [3509502, 'Campinas', 'SP'],
        [3550308, 'São Paulo', 'SP'],
        [3549904, 'São José dos Campos', 'SP'],
        [4106902, 'Curitiba', 'PR'],
    ]);
}

function igual(mixed $esperado, mixed $obtido, string $contexto = ''): void
{
    if ($esperado !== $obtido) {
        throw new RuntimeException(sprintf('%s: esperado %s, obtido %s', $contexto, var_export($esperado, true), var_export($obtido, true)));
    }
}

function verdade(bool $condicao, string $contexto): void
{
    if (!$condicao) {
        throw new RuntimeException($contexto);
    }
}

function carroGolf(array $extra = []): array
{
    return $extra + [
        'marca' => 'Volkswagen',
        'modelo' => 'Golf GTI',
        'versao' => '2.0 TSI',
        'anoFabricacao' => 2015,
        'anoModelo' => 2016,
        'quilometragem' => 98400,
        'status' => 'projeto',
        'placa' => 'abc-1d23',
        'precoCompra' => 95000,
    ];
}

$testes = [
    'cadastro devolve usuário e token, sem a senha' => function () {
        $c = novoCliente();
        $r = $c->chamar('POST', '/api/auth/register', [
            'nome' => 'Otávio Corrêa', 'username' => 'Otavio', 'email' => 'OTAVIO@exemplo.com', 'senha' => 'senha-de-teste',
        ]);
        igual(201, $r->status, 'status');
        igual('otavio', $r->corpo['data']['usuario']['username'], 'username em minúsculas');
        igual('otavio@exemplo.com', $r->corpo['data']['usuario']['email'], 'e-mail em minúsculas');
        verdade(strlen($r->corpo['data']['token']) === 64, 'token de 64 caracteres');
        verdade(!isset($r->corpo['data']['usuario']['senha_hash']), 'não expõe o hash da senha');
    },

    'cadastro valida campos e duplicidade' => function () {
        $c = novoCliente();
        $r = $c->chamar('POST', '/api/auth/register', ['nome' => '', 'username' => 'a', 'email' => 'x', 'senha' => '123']);
        igual(422, $r->status, 'dados inválidos');
        igual(['nome', 'username', 'email', 'senha'], array_keys($r->corpo['error']['fields']), 'campos com erro');

        $c->cadastrar('otavio');
        $r = $c->chamar('POST', '/api/auth/register', ['nome' => 'Outro', 'username' => 'OTAVIO', 'email' => 'otavio@exemplo.com', 'senha' => 'senha-de-teste']);
        igual(422, $r->status, 'duplicado');
        verdade(isset($r->corpo['error']['fields']['username'], $r->corpo['error']['fields']['email']), 'aponta username e e-mail');

        $r = $c->chamar('POST', '/api/auth/register', ['nome' => 'Rota', 'username' => 'entrar', 'email' => 'rota@exemplo.com', 'senha' => 'senha-de-teste']);
        igual(422, $r->status, 'username reservado');
    },

    'login certo, errado e /me' => function () {
        $c = novoCliente();
        $c->cadastrar('otavio');

        $r = $c->chamar('POST', '/api/auth/login', ['email' => 'otavio@exemplo.com', 'senha' => 'errada-123']);
        igual(401, $r->status, 'senha errada');
        $r = $c->chamar('POST', '/api/auth/login', ['email' => 'ninguem@exemplo.com', 'senha' => 'qualquer-1']);
        igual(401, $r->status, 'e-mail inexistente com a mesma resposta');
        igual('E-mail ou senha incorretos.', $r->corpo['error']['message'], 'mensagem genérica');

        $r = $c->chamar('POST', '/api/auth/login', ['email' => 'Otavio@Exemplo.com', 'senha' => 'senha-de-teste']);
        igual(200, $r->status, 'login certo');
        $token = $r->corpo['data']['token'];

        igual('otavio', $c->chamar('GET', '/api/auth/me', [], $token)->corpo['data']['username'], '/me');
        igual(401, $c->chamar('GET', '/api/auth/me')->status, '/me sem token');
        igual(401, $c->chamar('GET', '/api/auth/me', [], str_repeat('a', 64))->status, '/me com token inventado');
    },

    'logout invalida o token' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');
        igual(204, $c->chamar('POST', '/api/auth/logout', [], $token)->status, 'logout');
        igual(401, $c->chamar('GET', '/api/auth/me', [], $token)->status, 'token revogado');
    },

    'bloqueia depois de 5 senhas erradas' => function () {
        $c = novoCliente();
        $c->cadastrar('otavio');
        for ($i = 0; $i < 5; $i++) {
            $c->chamar('POST', '/api/auth/login', ['email' => 'otavio@exemplo.com', 'senha' => 'errada-123']);
        }
        $r = $c->chamar('POST', '/api/auth/login', ['email' => 'otavio@exemplo.com', 'senha' => 'senha-de-teste']);
        igual(429, $r->status, 'bloqueado mesmo com a senha certa');
        $r = $c->chamar('POST', '/api/auth/login', ['email' => 'otavio@exemplo.com', 'senha' => 'senha-de-teste'], null, '10.0.0.2');
        igual(200, $r->status, 'outro IP não é afetado');
    },

    'token chega também como REDIRECT_HTTP_AUTHORIZATION (PHP em CGI/FastCGI)' => function () {
        $antes = $_SERVER;
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/auth/me', 'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc'];
        try {
            igual('abc', Request::doServidor()->bearerToken(), 'lê a cópia feita pelo .htaccess');
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer direto';
            igual('direto', Request::doServidor()->bearerToken(), 'o header original tem prioridade');
        } finally {
            $_SERVER = $antes;
        }
    },

    'esqueci a senha: e-mail, troca de senha e sessões derrubadas' => function () {
        $correio = new CorreioFalso();
        $c = novoCliente(correio: $correio);
        $tokenAntigo = $c->cadastrar('otavio');

        igual(204, $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'ninguem@exemplo.com'])->status, 'e-mail sem conta responde igual');
        igual(0, count($correio->enviados), 'sem conta, nada é enviado');

        igual(204, $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'Otavio@Exemplo.com'])->status, 'pedido');
        igual(1, count($correio->enviados), 'um e-mail');
        igual('otavio@exemplo.com', $correio->enviados[0]->para, 'para o dono da conta');
        $token = $correio->ultimoToken();
        verdade(!str_contains((string) json_encode($c->db()->todos('SELECT * FROM redefinicoes_senha')), $token), 'banco guarda só o hash');

        $r = $c->chamar('POST', '/api/auth/reset-password', ['token' => $token, 'senha' => 'curta']);
        igual(422, $r->status, 'senha curta');
        igual(204, $c->chamar('POST', '/api/auth/reset-password', ['token' => $token, 'senha' => 'senha-nova-123'])->status, 'troca');
        igual(400, $c->chamar('POST', '/api/auth/reset-password', ['token' => $token, 'senha' => 'outra-senha-1'])->status, 'link só vale uma vez');

        igual(401, $c->chamar('GET', '/api/auth/me', [], $tokenAntigo)->status, 'sessões antigas encerradas');
        igual(401, $c->chamar('POST', '/api/auth/login', ['email' => 'otavio@exemplo.com', 'senha' => 'senha-de-teste'])->status, 'senha antiga não vale');
        igual(200, $c->chamar('POST', '/api/auth/login', ['email' => 'otavio@exemplo.com', 'senha' => 'senha-nova-123'])->status, 'senha nova vale');
    },

    'esqueci a senha: pedido novo invalida o anterior, expiração e limites' => function () {
        $correio = new CorreioFalso();
        $c = novoCliente(correio: $correio);
        $c->cadastrar('otavio');

        $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'otavio@exemplo.com']);
        $primeiro = $correio->ultimoToken();
        $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'otavio@exemplo.com']);
        $segundo = $correio->ultimoToken();
        igual(400, $c->chamar('POST', '/api/auth/reset-password', ['token' => $primeiro, 'senha' => 'senha-nova-123'])->status, 'o primeiro deixou de valer');

        $c->db()->executar('UPDATE redefinicoes_senha SET expira_em = ?', [gmdate('Y-m-d H:i:s', time() - 60)]);
        igual(400, $c->chamar('POST', '/api/auth/reset-password', ['token' => $segundo, 'senha' => 'senha-nova-123'])->status, 'expirado');

        $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'otavio@exemplo.com']);
        $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'otavio@exemplo.com']);
        igual(3, count($correio->enviados), 'no máximo 3 e-mails por hora para a mesma conta');

        for ($i = 0; $i < 6; $i++) {
            $c->chamar('POST', '/api/auth/forgot-password', ['email' => "x{$i}@exemplo.com"]);
        }
        igual(429, $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'y@exemplo.com'])->status, 'limite por IP');
        igual(204, $c->chamar('POST', '/api/auth/forgot-password', ['email' => 'y@exemplo.com'], null, '10.0.0.9')->status, 'outro IP segue');
        igual(422, $c->chamar('POST', '/api/auth/reset-password', ['token' => 'abc', 'senha' => 'senha-nova-123'])->status, 'token mal formado');
    },

    'CRUD de carros da própria garagem' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');

        $r = $c->chamar('POST', '/api/vehicles', carroGolf(), $token);
        igual(201, $r->status, 'criar');
        $carro = $r->corpo['data'];
        igual('golf-gti', $carro['slug'], 'slug');
        igual('ABC1D23', $carro['placa'], 'placa normalizada');
        igual(95000.0, $carro['precoCompra'], 'preço de compra');

        $r = $c->chamar('POST', '/api/vehicles', carroGolf(), $token);
        igual('golf-gti-2', $r->corpo['data']['slug'], 'slug repetido ganha sufixo');

        igual(2, count($c->chamar('GET', '/api/vehicles', [], $token)->corpo['data']), 'listar');

        $r = $c->chamar('PUT', "/api/vehicles/{$carro['id']}", carroGolf(['quilometragem' => 99000, 'status' => 'garagem']), $token);
        igual(200, $r->status, 'editar');
        igual(99000, $r->corpo['data']['quilometragem'], 'km atualizado');
        igual('golf-gti', $r->corpo['data']['slug'], 'slug não muda na edição');

        igual(204, $c->chamar('DELETE', "/api/vehicles/{$carro['id']}", [], $token)->status, 'excluir');
        igual(404, $c->chamar('GET', "/api/vehicles/{$carro['id']}", [], $token)->status, 'excluído');
    },

    'validação de carro' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');

        $r = $c->chamar('POST', '/api/vehicles', carroGolf(['anoModelo' => 2018]), $token);
        igual(422, $r->status, 'ano-modelo fora do intervalo');
        $r = $c->chamar('POST', '/api/vehicles', carroGolf(['status' => 'roubado', 'placa' => '123', 'quilometragem' => -5]), $token);
        igual(422, $r->status, 'campos inválidos');
        igual(['quilometragem', 'placa', 'status'], array_keys($r->corpo['error']['fields']), 'campos com erro');
        igual(401, $c->chamar('POST', '/api/vehicles', carroGolf())->status, 'sem login');
    },

    'ninguém mexe no carro de outra pessoa' => function () {
        $c = novoCliente();
        $dono = $c->cadastrar('otavio');
        $outro = $c->cadastrar('intruso');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $dono)->corpo['data']['id'];
        $modId = $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'Coilover', 'categoria' => 'Suspensão'], $dono)->corpo['data']['id'];

        igual(404, $c->chamar('GET', "/api/vehicles/{$id}", [], $outro)->status, 'ver');
        igual(404, $c->chamar('PUT', "/api/vehicles/{$id}", carroGolf(), $outro)->status, 'editar');
        igual(404, $c->chamar('DELETE', "/api/vehicles/{$id}", [], $outro)->status, 'excluir');
        igual(404, $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'X', 'categoria' => 'Y'], $outro)->status, 'adicionar modificação');
        igual(404, $c->chamar('DELETE', "/api/modifications/{$modId}", [], $outro)->status, 'excluir modificação');
        igual(0, count($c->chamar('GET', '/api/vehicles', [], $outro)->corpo['data']), 'lista do outro vazia');
        igual(200, $c->chamar('GET', "/api/vehicles/{$id}", [], $dono)->status, 'o dono continua vendo');
    },

    'modificações formam a linha do tempo' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $token)->corpo['data']['id'];

        $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'Coilover', 'categoria' => 'Suspensão', 'data' => '2025-11-05', 'valor' => 6500], $token);
        $r = $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'Remap', 'categoria' => 'Motor', 'data' => '2026-03-20'], $token);
        igual(201, $r->status, 'criar modificação');

        $r = $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'Futura', 'categoria' => 'Motor', 'data' => '2999-01-01'], $token);
        igual(422, $r->status, 'data no futuro');

        $mods = $c->chamar('GET', "/api/vehicles/{$id}", [], $token)->corpo['data']['modificacoes'];
        igual(['Remap', 'Coilover'], array_column($mods, 'nome'), 'mais recentes primeiro');
        igual(6500.0, $mods[1]['valor'], 'dono vê o valor');
    },

    'perfil público esconde dados sensíveis' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $token)->corpo['data']['id'];
        $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'Coilover', 'categoria' => 'Suspensão', 'valor' => 6500], $token);
        $c->chamar('POST', '/api/vehicles', carroGolf(['modelo' => 'Fusca', 'visivel' => false]), $token);

        $r = $c->chamar('GET', '/api/users/otavio');
        igual(200, $r->status, 'perfil público');
        igual('PO', $r->corpo['data']['iniciais'], 'iniciais');
        igual(1, count($r->corpo['data']['veiculos']), 'só carros visíveis');
        $carro = $r->corpo['data']['veiculos'][0];
        foreach (['placa', 'precoCompra', 'fipeCodigo'] as $campo) {
            verdade(!array_key_exists($campo, $carro), "não expõe {$campo}");
        }
        verdade(!array_key_exists('valor', $carro['modificacoes'][0]), 'não expõe valor da modificação');

        igual(200, $c->chamar('GET', '/api/users/otavio/vehicles/golf-gti')->status, 'página pública do carro');
        igual(404, $c->chamar('GET', '/api/users/otavio/vehicles/fusca')->status, 'carro oculto');
        igual(404, $c->chamar('GET', '/api/users/ninguem')->status, 'usuário inexistente');
    },

    'catálogo FIPE normalizado' => function () {
        $c = novoCliente();
        igual(401, $c->chamar('GET', '/api/catalog/brands')->status, 'exige login');
        $token = $c->cadastrar('otavio');

        $marcas = $c->chamar('GET', '/api/catalog/brands', [], $token)->corpo['data'];
        igual(['BMW', 'Chevrolet', 'Volkswagen'], array_column($marcas, 'nome'), 'marcas renomeadas e em ordem');

        $anos = $c->chamar('GET', '/api/catalog/brands/59/models/6600/years', [], $token)->corpo['data'];
        igual('Zero km Gasolina', $anos[0]['nome'], 'ano 32000 vira zero km');
        igual(['ano' => 2016, 'combustivel' => 'Gasolina'], array_intersect_key($anos[1], ['ano' => 1, 'combustivel' => 1]), 'ano e combustível');

        $d = $c->chamar('GET', '/api/catalog/brands/59/models/6600/years/2016-1', [], $token)->corpo['data'];
        igual(['Volkswagen', 'Golf GTi', '2.0 TSI 220cv Aut.'], [$d['marca'], $d['modelo'], $d['versao']], 'marca, modelo e versão');
        igual([104300.0, '2026-10-01', '005340-6'], [$d['valor'], $d['mesReferencia'], $d['codigoFipe']], 'valor, mês e código');

        igual(404, $c->chamar('GET', '/api/catalog/brands/abc/models', [], $token)->status, 'id inválido');
        igual(404, $c->chamar('GET', '/api/catalog/brands/59/models/6600/years/2016', [], $token)->status, 'ano inválido');
    },

    'FIPE fora do ar responde 503 e o cache segura' => function () {
        $fipe = new FipeFalsa(fora: true, limite: true);
        $c = novoCliente($fipe);
        $r = $c->chamar('GET', '/api/catalog/brands', [], $c->cadastrar('otavio'));
        igual(503, $r->status, 'indisponível');
        verdade(str_contains($r->corpo['error']['message'], 'limite'), 'explica o limite');

        $pasta = sys_get_temp_dir() . '/garagehub-teste-cache-' . bin2hex(random_bytes(4));
        $fonte = new FipeFalsa();
        $cache = new FipeComCache($fonte, $pasta);
        $cache->marcas();
        $cache->marcas();
        igual(1, $fonte->chamadas, 'segunda consulta vem do cache');

        touch("{$pasta}/fipe-marcas.json", time() - 30 * 86400);
        $fonte->fora = true;
        igual(3, count($cache->marcas()), 'cache vencido ainda serve quando a FIPE cai');
        array_map('unlink', glob("{$pasta}/*"));
        rmdir($pasta);
    },

    'separa modelo e versão do nome da FIPE' => function () {
        igual(['Golf GTi', '350 TSI 2.0 230cv 16V Aut.'], CatalogoController::separarModelo('Golf GTi 350 TSI 2.0 230cv 16V Aut.'), 'golf');
        igual(['Opala Comodoro', '4.1'], CatalogoController::separarModelo('Opala Comodoro 4.1'), 'opala');
        igual(['Fusca', null], CatalogoController::separarModelo('Fusca'), 'sem versão');
        igual(null, CatalogoController::mesReferencia('mês estranho'), 'mês desconhecido');
    },

    'carro guarda os ids da FIPE só para o dono' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');
        $r = $c->chamar('POST', '/api/vehicles', carroGolf(['fipeMarcaId' => '59', 'fipeModeloId' => '6600', 'fipeAnoId' => '2016-1', 'fipeCodigo' => '005340-6']), $token);
        igual(201, $r->status, 'criar');
        igual(['59', '6600', '2016-1'], [$r->corpo['data']['fipeMarcaId'], $r->corpo['data']['fipeModeloId'], $r->corpo['data']['fipeAnoId']], 'ids salvos');

        $publico = $c->chamar('GET', '/api/users/otavio/vehicles/golf-gti')->corpo['data']['veiculo'];
        verdade(!array_key_exists('fipeMarcaId', $publico), 'não expõe ids na página pública');

        igual(422, $c->chamar('POST', '/api/vehicles', carroGolf(['fipeAnoId' => '../etc']), $token)->status, 'id malformado');
    },

    'foto padrão do modelo ignora maiúsculas' => function () {
        $c = novoCliente();
        $c->db()->inserir('fotos_modelo', ['marca' => 'Volkswagen', 'modelo' => 'Golf GTI', 'arquivo_url' => '/modelos/golf.jpg']);
        $r = $c->chamar('POST', '/api/vehicles', carroGolf(['modelo' => 'Golf GTi']), $c->cadastrar('otavio'));
        igual('/modelos/golf.jpg', $r->corpo['data']['fotoModeloUrl'], 'foto encontrada');
    },

    'fotos: primeira vira capa, troca de capa e galeria pública' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $token)->corpo['data']['id'];

        $r1 = $c->enviarFoto($id, $token);
        igual(201, $r1->status, 'enviar');
        igual(true, $r1->corpo['data']['capa'], 'primeira é capa');
        verdade((bool) preg_match('#^/uploads/veiculos/\d+/[a-f0-9]{32}\.jpg$#', $r1->corpo['data']['url']), 'url com nome aleatório');
        $r2 = $c->enviarFoto($id, $token);
        igual(false, $r2->corpo['data']['capa'], 'segunda não é capa');

        igual(200, $c->chamar('PUT', "/api/photos/{$r2->corpo['data']['id']}/cover", [], $token)->status, 'trocar capa');
        $publico = $c->chamar('GET', '/api/users/otavio/vehicles/golf-gti')->corpo['data']['veiculo'];
        igual($r2->corpo['data']['url'], $publico['fotoUrl'], 'capa vira a foto do carro');
        igual([$r2->corpo['data']['id'], $r1->corpo['data']['id']], array_column($publico['fotos'], 'id'), 'galeria com a capa primeiro');

        $lista = $c->chamar('GET', '/api/users/otavio')->corpo['data']['veiculos'][0];
        igual($r2->corpo['data']['url'], $lista['fotoUrl'], 'card do perfil usa a capa');
        verdade(!array_key_exists('fotos', $lista), 'lista não traz a galeria inteira');
    },

    'fotos: metadados removidos e arquivo inválido recusado' => function () {
        $c = novoCliente();
        $token = $c->cadastrar('otavio');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $token)->corpo['data']['id'];

        $r = $c->enviarFoto($id, $token, jpegComGps());
        igual(201, $r->status, 'jpeg com exif aceito');
        $gravado = file_get_contents($_ENV['UPLOADS_DIR'] . '/' . substr($r->corpo['data']['url'], strlen('/uploads/')));
        verdade(!str_contains($gravado, 'GPS-LAT'), 'GPS removido do arquivo gravado');

        igual(422, $c->enviarFoto($id, $token, '<?php echo "oi";')->status, 'php disfarçado');
        igual(422, $c->enviarFoto($id, $token, "\x89PNG\r\n\x1a\n" . str_repeat("\0", 30))->status, 'png');
        $semArquivo = $c->chamar('POST', "/api/vehicles/{$id}/photos", [], $token);
        igual('Escolha uma foto para enviar.', $semArquivo->corpo['error']['fields']['foto'] ?? null, 'sem arquivo');
    },

    'fotos: só o dono mexe, e excluir a capa promove a próxima' => function () {
        $c = novoCliente();
        $dono = $c->cadastrar('otavio');
        $outro = $c->cadastrar('intruso');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $dono)->corpo['data']['id'];
        $capa = $c->enviarFoto($id, $dono)->corpo['data'];
        $segunda = $c->enviarFoto($id, $dono)->corpo['data'];

        igual(404, $c->enviarFoto($id, $outro)->status, 'outro não envia');
        igual(404, $c->chamar('PUT', "/api/photos/{$segunda['id']}/cover", [], $outro)->status, 'outro não troca capa');
        igual(404, $c->chamar('DELETE', "/api/photos/{$capa['id']}", [], $outro)->status, 'outro não exclui');

        $arquivoCapa = $_ENV['UPLOADS_DIR'] . '/' . substr($capa['url'], strlen('/uploads/'));
        igual(204, $c->chamar('DELETE', "/api/photos/{$capa['id']}", [], $dono)->status, 'dono exclui');
        verdade(!is_file($arquivoCapa), 'arquivo apagado do disco');
        $fotos = $c->chamar('GET', "/api/vehicles/{$id}", [], $dono)->corpo['data']['fotos'];
        igual([[$segunda['id'], true]], array_map(fn ($f) => [$f['id'], $f['capa']], $fotos), 'a próxima virou capa');

        $arquivoSegunda = $_ENV['UPLOADS_DIR'] . '/' . substr($segunda['url'], strlen('/uploads/'));
        $c->chamar('DELETE', "/api/vehicles/{$id}", [], $dono);
        verdade(!is_file($arquivoSegunda), 'excluir o carro apaga as fotos do disco');
    },

    'seguir e deixar de seguir pessoas' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $c->cadastrar('beto');

        $r = $c->chamar('POST', '/api/users/beto/follow', [], $ana);
        igual(200, $r->status, 'seguir');
        igual(['euSigo' => true, 'seguidores' => 1, 'seguindo' => 0], $r->corpo['data'], 'estado depois de seguir');
        igual(1, $c->chamar('POST', '/api/users/beto/follow', [], $ana)->corpo['data']['seguidores'], 'seguir de novo não duplica');

        $perfil = $c->chamar('GET', '/api/users/beto', [], $ana)->corpo['data'];
        igual([1, true], [$perfil['seguidores'], $perfil['euSigo']], 'perfil mostra seguidores para quem segue');
        igual(false, $c->chamar('GET', '/api/users/beto')->corpo['data']['euSigo'], 'visitante não segue');
        igual(1, $c->chamar('GET', '/api/users/ana')->corpo['data']['seguindo'], 'contagem de quem a pessoa segue');

        igual(false, $c->chamar('DELETE', '/api/users/beto/follow', [], $ana)->corpo['data']['euSigo'], 'deixar de seguir');
        igual(422, $c->chamar('POST', '/api/users/ana/follow', [], $ana)->status, 'não segue a si mesmo');
        igual(401, $c->chamar('POST', '/api/users/beto/follow')->status, 'exige login');
        igual(404, $c->chamar('POST', '/api/users/ninguem/follow', [], $ana)->status, 'usuário inexistente');
    },

    'feed mostra quem eu sigo, e explorar mostra todos os públicos' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $caio = $c->cadastrar('caio');

        $golfBeto = $c->chamar('POST', '/api/vehicles', carroGolf(), $beto)->corpo['data']['id'];
        $c->chamar('POST', "/api/vehicles/{$golfBeto}/modifications", ['nome' => 'Coilover', 'categoria' => 'Suspensão'], $beto);
        $c->enviarFoto($golfBeto, $beto);
        $c->enviarFoto($golfBeto, $beto);
        $fuscaCaio = $c->chamar('POST', '/api/vehicles', carroGolf(['modelo' => 'Fusca']), $caio)->corpo['data']['id'];
        $c->chamar('POST', '/api/vehicles', carroGolf(['modelo' => 'Escondido', 'visivel' => false]), $beto);

        $itens = fn (string $token) => $c->chamar('GET', '/api/feed', [], $token)->corpo['data']['itens'];
        igual([], $itens($ana), 'sem seguir ninguém, feed vazio');

        $c->chamar('POST', '/api/users/beto/follow', [], $ana);
        $feed = $itens($ana);
        $tipos = array_count_values(array_column($feed, 'tipo'));
        igual([1, 1, 1], [$tipos['carro'] ?? 0, $tipos['modificacao'] ?? 0, $tipos['fotos'] ?? 0], 'eventos do beto: carro, modificação e fotos agrupadas');
        $fotos = array_values(array_filter($feed, fn ($i) => $i['tipo'] === 'fotos'))[0];
        igual(2, $fotos['quantidade'], 'duas fotos no mesmo evento');
        verdade(!in_array('Escondido', array_column(array_column($feed, 'veiculo'), 'modelo'), true), 'carro escondido fora do feed');
        verdade(!array_key_exists('placa', $feed[0]['veiculo']), 'feed não expõe placa');
        igual('beto', $feed[0]['autor']['username'], 'autor do evento');

        // Seguir só o carro do Caio traz os eventos dele, sem seguir a pessoa
        $c->chamar('POST', "/api/vehicles/{$fuscaCaio}/follow", [], $ana);
        verdade(in_array('Fusca', array_column(array_column($itens($ana), 'veiculo'), 'modelo'), true), 'carro seguido entra no feed');

        $explorar = $c->chamar('GET', '/api/feed', [], $ana, query: ['aba' => 'explorar'])->corpo['data'];
        verdade(count($explorar['itens']) >= 4, 'explorar traz todos os perfis públicos');
        igual(false, $explorar['temMais'], 'sem próxima página');
    },

    'perfil privado some do feed, das sugestões e não pode ser seguido' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $c->chamar('POST', '/api/vehicles', carroGolf(), $beto);
        $c->chamar('POST', '/api/users/beto/follow', [], $ana);

        $sugestoes = $c->chamar('GET', '/api/users/suggestions', [], $c->cadastrar('caio'))->corpo['data'];
        igual(['beto'], array_column($sugestoes, 'username'), 'sugestão: só quem tem carro público');
        igual('Golf GTI', $sugestoes[0]['destaque']['modelo'], 'sugestão com carro de destaque');

        $c->db()->executar("UPDATE usuarios SET perfil_privado = 1 WHERE username = 'beto'");
        igual([], $c->chamar('GET', '/api/feed', [], $ana)->corpo['data']['itens'], 'privado sai do feed de quem seguia');
        igual(404, $c->chamar('POST', '/api/users/beto/follow', [], $ana)->status, 'não dá para seguir privado');
        igual([], $c->chamar('GET', '/api/users/suggestions', [], $ana)->corpo['data'], 'privado fora das sugestões');
        verdade(count($c->chamar('GET', '/api/feed', [], $beto)->corpo['data']['itens']) > 0, 'o próprio dono continua vendo os seus');
    },

    'seguir carro' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $beto)->corpo['data']['id'];

        igual(['euSigo' => true, 'seguidores' => 1], $c->chamar('POST', "/api/vehicles/{$id}/follow", [], $ana)->corpo['data'], 'seguir carro');
        $publico = $c->chamar('GET', '/api/users/beto/vehicles/golf-gti', [], $ana)->corpo['data']['veiculo'];
        igual([1, true], [$publico['seguidores'], $publico['euSigo']], 'página do carro mostra seguidores');
        igual(422, $c->chamar('POST', "/api/vehicles/{$id}/follow", [], $beto)->status, 'dono não segue o próprio carro');
        igual(0, $c->chamar('DELETE', "/api/vehicles/{$id}/follow", [], $ana)->corpo['data']['seguidores'], 'deixar de seguir carro');
    },

    'curtir carro e modificação' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $beto)->corpo['data']['id'];
        $modId = $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'Coilover', 'categoria' => 'Suspensão'], $beto)->corpo['data']['id'];

        igual(['curtidas' => 1, 'curtido' => true, 'comentarios' => 0], $c->chamar('POST', "/api/likes/veiculo/{$id}", [], $ana)->corpo['data'], 'curtir carro');
        igual(1, $c->chamar('POST', "/api/likes/veiculo/{$id}", [], $ana)->corpo['data']['curtidas'], 'curtir de novo não duplica');
        igual(2, $c->chamar('POST', "/api/likes/veiculo/{$id}", [], $beto)->corpo['data']['curtidas'], 'o dono também pode curtir');
        $c->chamar('POST', "/api/likes/modificacao/{$modId}", [], $ana);

        $publico = $c->chamar('GET', '/api/users/beto/vehicles/golf-gti', [], $ana)->corpo['data']['veiculo'];
        igual([2, true], [$publico['interacao']['curtidas'], $publico['interacao']['curtido']], 'carro mostra curtidas');
        igual(1, $publico['modificacoes'][0]['interacao']['curtidas'], 'modificação mostra curtidas');

        igual(false, $c->chamar('DELETE', "/api/likes/veiculo/{$id}", [], $ana)->corpo['data']['curtido'], 'descurtir');
        igual(404, $c->chamar('POST', '/api/likes/foto/1', [], $ana)->status, 'tipo inválido');
        igual(404, $c->chamar('POST', '/api/likes/veiculo/9999', [], $ana)->status, 'carro inexistente');
        igual(401, $c->chamar('POST', "/api/likes/veiculo/{$id}")->status, 'exige login');

        $c->db()->executar('UPDATE veiculos SET visivel = 0 WHERE id = ?', [$id]);
        igual(404, $c->chamar('POST', "/api/likes/veiculo/{$id}", [], $ana)->status, 'carro escondido não recebe curtida');
    },

    'comentários: escrever, ler, apagar e limites' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $caio = $c->cadastrar('caio');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $beto)->corpo['data']['id'];

        $r = $c->chamar('POST', "/api/comments/veiculo/{$id}", ['texto' => '  Que projeto! <b>top</b>  '], $ana);
        igual(201, $r->status, 'comentar');
        igual('Que projeto! <b>top</b>', $r->corpo['data']['texto'], 'texto guardado como veio (o front escapa ao mostrar)');
        $comentarioAna = $r->corpo['data']['id'];
        $comentarioCaio = $c->chamar('POST', "/api/comments/veiculo/{$id}", ['texto' => 'Qual a altura do coilover?'], $caio)->corpo['data']['id'];

        $lista = $c->chamar('GET', "/api/comments/veiculo/{$id}")->corpo['data'];
        igual([2, ['ana', 'caio']], [$lista['total'], array_column(array_column($lista['itens'], 'autor'), 'username')], 'visitante lê, em ordem de conversa');
        igual([false, false], array_column($lista['itens'], 'podeApagar'), 'visitante não apaga');
        igual([false, true], array_column($c->chamar('GET', "/api/comments/veiculo/{$id}", [], $caio)->corpo['data']['itens'], 'podeApagar'), 'cada um apaga o seu');
        igual([true, true], array_column($c->chamar('GET', "/api/comments/veiculo/{$id}", [], $beto)->corpo['data']['itens'], 'podeApagar'), 'o dono do carro apaga qualquer um');

        igual(403, $c->chamar('DELETE', "/api/comments/{$comentarioAna}", [], $caio)->status, 'não apaga o dos outros');
        igual(204, $c->chamar('DELETE', "/api/comments/{$comentarioAna}", [], $beto)->status, 'dono do carro apaga');
        igual(204, $c->chamar('DELETE', "/api/comments/{$comentarioCaio}", [], $caio)->status, 'autor apaga');

        igual(422, $c->chamar('POST', "/api/comments/veiculo/{$id}", ['texto' => '   '], $ana)->status, 'comentário vazio');
        igual(422, $c->chamar('POST', "/api/comments/veiculo/{$id}", ['texto' => str_repeat('a', 1001)], $ana)->status, 'comentário longo demais');
        for ($i = 0; $i < 10; $i++) {
            $c->chamar('POST', "/api/comments/veiculo/{$id}", ['texto' => "oi {$i}"], $ana);
        }
        igual(429, $c->chamar('POST', "/api/comments/veiculo/{$id}", ['texto' => 'mais um'], $ana)->status, 'limite de comentários por minuto');
    },

    'feed traz curtidas e comentários, e excluir limpa tudo' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $id = $c->chamar('POST', '/api/vehicles', carroGolf(), $beto)->corpo['data']['id'];
        $modId = $c->chamar('POST', "/api/vehicles/{$id}/modifications", ['nome' => 'Coilover', 'categoria' => 'Suspensão'], $beto)->corpo['data']['id'];
        $c->chamar('POST', '/api/users/beto/follow', [], $ana);
        $c->chamar('POST', "/api/likes/modificacao/{$modId}", [], $ana);
        $c->chamar('POST', "/api/comments/modificacao/{$modId}", ['texto' => 'Boa!'], $ana);
        $c->chamar('POST', "/api/likes/veiculo/{$id}", [], $ana);

        $itens = $c->chamar('GET', '/api/feed', [], $ana)->corpo['data']['itens'];
        $porTipo = array_column($itens, 'interacao', 'tipo');
        igual(['tipo' => 'modificacao', 'id' => $modId, 'curtidas' => 1, 'curtido' => true, 'comentarios' => 1], $porTipo['modificacao'], 'item de modificação');
        igual(['veiculo', 1], [$porTipo['carro']['tipo'], $porTipo['carro']['curtidas']], 'item de carro curte o carro');

        $c->chamar('DELETE', "/api/vehicles/{$id}", [], $beto);
        igual(0, (int) $c->db()->valor('SELECT COUNT(*) FROM curtidas') + (int) $c->db()->valor('SELECT COUNT(*) FROM comentarios'), 'excluir o carro apaga curtidas e comentários dele e das modificações');
    },

    'cidades: busca sem acento e perfil com cidade' => function () {
        $c = novoCliente();
        comCidades($c);
        $nomes = fn (string $q) => array_map(fn ($x) => "{$x['nome']}/{$x['uf']}", $c->chamar('GET', '/api/cities', query: ['q' => $q])->corpo['data']);
        igual(['São Paulo/SP', 'São José dos Campos/SP'], $nomes('sao'), 'sem acento, começando pelo termo e pelos nomes curtos');
        igual(['Campinas/SP', 'São José dos Campos/SP'], $nomes('camp'), 'prefixo primeiro, depois o meio do nome');
        igual([], $nomes('c'), 'pede pelo menos 2 letras');

        $ana = $c->cadastrar('ana');
        $r = $c->chamar('PUT', '/api/auth/me', ['nome' => 'Ana Lima', 'bio' => 'Rebaixados', 'cidadeId' => 3509502, 'perfilPrivado' => false], $ana);
        igual(200, $r->status, 'atualizar perfil');
        igual(['Campinas, SP', 3509502, 'SP'], [$r->corpo['data']['cidade'], $r->corpo['data']['cidadeId'], $r->corpo['data']['uf']], 'cidade estruturada');
        igual('Campinas, SP', $c->chamar('GET', '/api/users/ana')->corpo['data']['cidade'], 'perfil público mostra a cidade');
        igual(422, $c->chamar('PUT', '/api/auth/me', ['nome' => 'Ana', 'cidadeId' => 999], $ana)->status, 'cidade inexistente');
        igual(422, $c->chamar('PUT', '/api/auth/me', ['nome' => ''], $ana)->status, 'nome obrigatório');

        $c->chamar('PUT', '/api/auth/me', ['nome' => 'Ana Lima', 'perfilPrivado' => true], $ana);
        igual(404, $c->chamar('GET', '/api/users/ana')->status, 'perfil privado pelo próprio perfil');
    },

    'explorar por cidade e estado, e sugestões da mesma cidade primeiro' => function () {
        $c = novoCliente();
        comCidades($c);
        $eu = $c->cadastrar('euzinho');
        $c->chamar('PUT', '/api/auth/me', ['nome' => 'Eu', 'cidadeId' => 3509502], $eu);
        foreach ([['campineiro', 3509502], ['paulistano', 3550308], ['curitibano', 4106902]] as [$nome, $cidade]) {
            $t = $c->cadastrar($nome);
            $c->chamar('PUT', '/api/auth/me', ['nome' => ucfirst($nome), 'cidadeId' => $cidade], $t);
            $c->chamar('POST', '/api/vehicles', carroGolf(), $t);
        }
        $autores = fn (?string $local) => array_values(array_unique(array_column(array_column(
            $c->chamar('GET', '/api/feed', [], $eu, query: ['aba' => 'explorar'] + ($local ? ['local' => $local] : []))->corpo['data']['itens'],
            'autor',
        ), 'username')));

        igual(['campineiro'], $autores('cidade'), 'minha cidade');
        igual(['campineiro', 'paulistano'], array_values(array_intersect(['campineiro', 'paulistano', 'curitibano'], $autores('estado'))), 'meu estado');
        igual(3, count($autores(null)), 'Brasil todo');

        $sugestoes = $c->chamar('GET', '/api/users/suggestions', [], $eu)->corpo['data'];
        igual(['campineiro', 'paulistano', 'curitibano'], array_column($sugestoes, 'username'), 'cidade, depois estado, depois o resto');
        igual([true, 'Campinas, SP'], [$sugestoes[0]['mesmaCidade'], $sugestoes[0]['cidade']], 'marca quem é da mesma cidade');
    },

    'busca de pessoas, carros e peças, com filtro de local' => function () {
        $c = novoCliente();
        comCidades($c);
        $ana = $c->cadastrar('ana');
        $c->chamar('PUT', '/api/auth/me', ['nome' => 'Ana Rebaixada', 'cidadeId' => 3509502], $ana);
        $golf = $c->chamar('POST', '/api/vehicles', carroGolf(), $ana)->corpo['data']['id'];
        $c->chamar('POST', "/api/vehicles/{$golf}/modifications", ['nome' => 'Coilover', 'categoria' => 'Suspensão', 'marcaPeca' => 'KW'], $ana);
        $beto = $c->cadastrar('beto');
        $c->chamar('PUT', '/api/auth/me', ['nome' => 'Beto', 'cidadeId' => 4106902], $beto);
        $c->chamar('POST', '/api/vehicles', carroGolf(['versao' => 'GTI Clubsport']), $beto);
        $c->chamar('POST', '/api/vehicles', carroGolf(['modelo' => 'Oculto', 'visivel' => false]), $beto);

        $busca = fn (array $q) => $c->chamar('GET', '/api/search', query: $q)->corpo['data'];

        igual(['ana'], array_column($busca(['q' => 'rebaixada'])['pessoas'], 'username'), 'pessoa pelo nome');
        igual(2, count($busca(['q' => 'golf'])['carros']), 'carros pelo modelo, sem os ocultos');
        $porPeca = $busca(['q' => 'coilover kw'])['carros'];
        igual([1, 'ana', 'Coilover'], [count($porPeca), $porPeca[0]['dono']['username'], $porPeca[0]['modificacao']], 'carro pela peça, com o motivo');
        igual(['beto'], array_column(array_column($busca(['q' => 'golf', 'uf' => 'PR'])['carros'], 'dono'), 'username'), 'filtro por estado');
        igual(['ana'], array_column(array_column($busca(['q' => 'golf', 'cidade' => '3509502'])['carros'], 'dono'), 'username'), 'filtro por cidade');
        igual([], $busca(['q' => 'golf_%'])['carros'], 'caracteres especiais do LIKE não viram curinga');
        verdade(!array_key_exists('placa', $busca(['q' => 'golf'])['carros'][0]), 'busca não expõe placa');
        igual(422, $c->chamar('GET', '/api/search', query: ['q' => 'g'])->status, 'termo curto demais');
    },

    'foto de perfil: enviar, aparecer em todo lugar, trocar e remover' => function () {
        $c = novoCliente();
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $avatar = fn (string $token, ?string $conteudo = null) => $c->chamar('POST', '/api/auth/me/avatar', [], $token, '10.0.0.1', ['avatar' => upload($conteudo ?? base64_decode(JPEG_TESTE))]);

        igual(422, $avatar($ana, 'não é imagem')->status, 'recusa arquivo que não é JPEG');
        igual(422, $c->chamar('POST', '/api/auth/me/avatar', [], $ana)->status, 'sem arquivo');
        igual(401, $c->chamar('POST', '/api/auth/me/avatar')->status, 'exige login');

        $r = $avatar($ana, jpegComGps());
        igual(200, $r->status, 'envio');
        $url = $r->corpo['data']['avatarUrl'];
        verdade(str_starts_with($url, '/uploads/avatares/'), 'endereço da foto');
        $arquivo = $_ENV['UPLOADS_DIR'] . substr($url, strlen('/uploads'));
        verdade(is_file($arquivo) && !str_contains((string) file_get_contents($arquivo), 'GPS'), 'gravada sem metadados');

        igual($url, $c->chamar('GET', '/api/auth/me', [], $ana)->corpo['data']['avatarUrl'], 'no /me');
        igual($url, $c->chamar('GET', '/api/users/ana')->corpo['data']['avatarUrl'], 'no perfil público');
        $carro = $c->chamar('POST', '/api/vehicles', carroGolf(), $ana)->corpo['data']['id'];
        $c->chamar('POST', "/api/comments/veiculo/{$carro}", ['texto' => 'Meu carro'], $ana);
        igual($url, $c->chamar('GET', "/api/comments/veiculo/{$carro}")->corpo['data']['itens'][0]['autor']['avatarUrl'], 'nos comentários');
        igual($url, $c->chamar('GET', '/api/feed', [], $beto, query: ['aba' => 'explorar'])->corpo['data']['itens'][0]['autor']['avatarUrl'], 'no feed');
        igual($url, $c->chamar('GET', '/api/users/suggestions', [], $beto)->corpo['data'][0]['avatarUrl'], 'nas sugestões');
        igual($url, $c->chamar('GET', '/api/search', [], $beto, query: ['q' => 'ana'])->corpo['data']['pessoas'][0]['avatarUrl'], 'na busca');
        igual(null, $c->chamar('GET', '/api/users/beto')->corpo['data']['avatarUrl'], 'quem não enviou fica com as iniciais');

        $nova = $avatar($ana)->corpo['data']['avatarUrl'];
        verdade($nova !== $url && !is_file($arquivo), 'trocar apaga a foto anterior');

        $r = $c->chamar('DELETE', '/api/auth/me/avatar', [], $ana);
        igual([200, null], [$r->status, $r->corpo['data']['avatarUrl']], 'remover');
        verdade(!is_file($_ENV['UPLOADS_DIR'] . substr($nova, strlen('/uploads'))), 'arquivo removido');
    },

    'feed explorar filtra por qualquer cidade ou estado' => function () {
        $c = novoCliente();
        comCidades($c);
        $ana = $c->cadastrar('ana');
        $beto = $c->cadastrar('beto');
        $c->chamar('PUT', '/api/auth/me', ['nome' => 'Ana', 'cidadeId' => 3509502], $ana);
        $c->chamar('PUT', '/api/auth/me', ['nome' => 'Beto', 'cidadeId' => 4106902], $beto);
        $c->chamar('POST', '/api/vehicles', carroGolf(), $ana);
        $c->chamar('POST', '/api/vehicles', carroGolf(), $beto);

        $donos = fn (array $query) => array_values(array_unique(array_column(array_column(
            $c->chamar('GET', '/api/feed', [], $ana, query: ['aba' => 'explorar'] + $query)->corpo['data']['itens'], 'autor'), 'username')));
        igual(['beto'], $donos(['cidade' => '4106902']), 'outra cidade, diferente da do perfil');
        igual(['beto'], $donos(['uf' => 'pr']), 'outro estado');
        igual(['ana'], $donos(['local' => 'cidade']), 'local=cidade continua usando a cidade do perfil');
        igual(2, count($donos([])), 'sem filtro, o Brasil todo');

        $r = $c->chamar('GET', '/api/cities/4106902');
        igual(['id' => 4106902, 'nome' => 'Curitiba', 'uf' => 'PR'], $r->corpo['data'], 'cidade pelo id');
        igual(404, $c->chamar('GET', '/api/cities/1')->status, 'cidade inexistente');
    },

    'rotas inexistentes, método errado e CORS' => function () {
        $c = novoCliente();
        igual(404, $c->chamar('GET', '/api/nada')->status, 'rota inexistente');
        igual(405, $c->chamar('DELETE', '/api/auth/login')->status, 'método errado');
        igual(404, $c->chamar('GET', '/api/vehicles/abc', [], $c->cadastrar('otavio'))->status, 'id não numérico');

        $app = (new ReflectionProperty(Cliente::class, 'app'))->getValue($c);
        $r = $app->processar(new Request('OPTIONS', '/api/vehicles', [], ['origin' => 'http://localhost:4200']));
        igual(204, $r->status, 'preflight');
        igual('http://localhost:4200', $r->headers['Access-Control-Allow-Origin'] ?? null, 'origem liberada');
        $r = $app->processar(new Request('OPTIONS', '/api/vehicles', [], ['origin' => 'https://site-estranho.com']));
        verdade(!isset($r->headers['Access-Control-Allow-Origin']), 'origem estranha bloqueada');
    },
];

$falhas = 0;
foreach ($testes as $nome => $teste) {
    try {
        $teste();
        echo "✓ {$nome}\n";
    } catch (Throwable $e) {
        $falhas++;
        echo "✗ {$nome}\n    {$e->getMessage()}\n";
    }
}

// Limpa a pasta temporária das fotos
if (is_dir($_ENV['UPLOADS_DIR'])) {
    $itens = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_ENV['UPLOADS_DIR'], FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($itens as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($_ENV['UPLOADS_DIR']);
}

$total = count($testes);
echo "\n" . ($total - $falhas) . " de {$total} testes passaram.\n";
exit($falhas ? 1 : 0);
