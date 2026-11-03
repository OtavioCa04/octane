<?php

declare(strict_types=1);

namespace GarageHub\Catalogo;

use GarageHub\Core\Env;
use GarageHub\Core\HttpException;

/**
 * FIPE API v2 do Parallelum (https://fipe.parallelum.com.br/api/v2).
 * Sem token, o limite é de 1000 consultas por dia; com FIPE_TOKEN no .env, o limite do plano contratado.
 */
final class FipeParallelum implements FonteFipe
{
    private readonly string $base;

    public function __construct()
    {
        $this->base = rtrim(Env::get('FIPE_URL', 'https://fipe.parallelum.com.br/api/v2'), '/') . '/cars';
    }

    public function marcas(): array
    {
        return $this->get('/brands');
    }

    public function modelos(string $marcaId): array
    {
        return $this->get("/brands/{$marcaId}/models");
    }

    public function anos(string $marcaId, string $modeloId): array
    {
        return $this->get("/brands/{$marcaId}/models/{$modeloId}/years");
    }

    public function detalhe(string $marcaId, string $modeloId, string $anoId): array
    {
        return $this->get("/brands/{$marcaId}/models/{$modeloId}/years/{$anoId}");
    }

    private function get(string $caminho): array
    {
        $headers = ['Accept: application/json'];
        if ($token = Env::get('FIPE_TOKEN')) {
            $headers[] = "X-Subscription-Token: {$token}";
        }

        $ch = curl_init($this->base . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'GarageHub/0.1',
        ]);
        // PHP sem curl.cainfo (comum no Windows): usa os certificados do sistema. A verificação continua ligada.
        if (!ini_get('curl.cainfo') && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }

        $corpo = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($corpo === false || $status === 0) {
            throw new FipeIndisponivel('sem resposta: ' . curl_error($ch));
        }
        if ($status === 404) {
            throw HttpException::naoEncontrado('Não encontramos esse item na Tabela FIPE.');
        }
        if ($status === 429) {
            throw new FipeIndisponivel('limite de consultas', limite: true);
        }
        $dados = json_decode((string) $corpo, true);
        if ($status >= 400 || !is_array($dados)) {
            throw new FipeIndisponivel("resposta inesperada ({$status})");
        }
        return $dados;
    }
}
