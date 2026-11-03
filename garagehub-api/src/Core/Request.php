<?php

declare(strict_types=1);

namespace GarageHub\Core;

final class Request
{
    /** Parâmetros da rota, ex.: {id} */
    public array $params = [];

    /** Usuário autenticado (preenchido pelo middleware de token) */
    public ?array $usuario = null;

    /** Token usado na requisição, para o logout */
    public ?string $token = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly string $ip = '0.0.0.0',
        /** Arquivos enviados (multipart), no formato de $_FILES, só os que o PHP confirmou como upload */
        public readonly array $arquivos = [],
    ) {
    }

    /**
     * IP de quem fez o pedido. Atrás de um proxy (Railway, Cloudflare), REMOTE_ADDR é o do proxy para todo mundo,
     * e os limites por IP (login, esqueci a senha) virariam um limite do site inteiro. Com TRUST_PROXY=1, usa o
     * último endereço do X-Forwarded-For, que é o que o proxy viu (os anteriores o próprio cliente pode inventar).
     */
    private static function ipDoCliente(array $headers): string
    {
        $remoto = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!Env::bool('TRUST_PROXY') || empty($headers['x-forwarded-for'])) {
            return $remoto;
        }
        $ips = array_map('trim', explode(',', $headers['x-forwarded-for']));
        $ultimo = end($ips);
        return filter_var($ultimo, FILTER_VALIDATE_IP) ? $ultimo : $remoto;
    }

    public static function doServidor(): self
    {
        $headers = [];
        foreach ($_SERVER as $chave => $valor) {
            if (str_starts_with($chave, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($chave, 5)))] = $valor;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        // Com PHP em CGI/FastCGI (a maioria das hospedagens), o Apache não repassa o Authorization;
        // o .htaccess o copia para uma variável que chega como REDIRECT_HTTP_AUTHORIZATION a cada reescrita.
        if (!isset($headers['authorization'])) {
            foreach (['REDIRECT_HTTP_AUTHORIZATION', 'REDIRECT_REDIRECT_HTTP_AUTHORIZATION'] as $chave) {
                if (!empty($_SERVER[$chave])) {
                    $headers['authorization'] = $_SERVER[$chave];
                    break;
                }
            }
        }

        // Envio de arquivos: o PHP já leu o corpo para $_FILES. Só passa adiante upload legítimo.
        $arquivos = [];
        foreach ($_FILES as $campo => $arquivo) {
            if (is_array($arquivo) && is_string($arquivo['tmp_name'] ?? null)
                && ($arquivo['error'] !== UPLOAD_ERR_OK || is_uploaded_file($arquivo['tmp_name']))) {
                $arquivos[$campo] = $arquivo;
            }
        }
        $multipart = str_starts_with($headers['content-type'] ?? '', 'multipart/form-data');

        $bruto = $multipart ? '' : (string) file_get_contents('php://input');
        $body = $multipart ? $_POST : [];
        if ($bruto !== '') {
            $body = json_decode($bruto, true);
            if (!is_array($body)) {
                throw new HttpException(400, 'O corpo da requisição precisa ser um JSON válido.');
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rtrim($path, '/') ?: '/',
            $body,
            $headers,
            $_GET,
            self::ipDoCliente($headers),
            $arquivos,
        );
    }

    public function header(string $nome): ?string
    {
        return $this->headers[strtolower($nome)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization') ?? '';
        return preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : null;
    }

    /** Parâmetro numérico da rota; qualquer outra coisa vira 404. */
    public function id(string $nome = 'id'): int
    {
        $valor = $this->params[$nome] ?? '';
        if (!ctype_digit($valor)) {
            throw HttpException::naoEncontrado();
        }
        return (int) $valor;
    }
}
