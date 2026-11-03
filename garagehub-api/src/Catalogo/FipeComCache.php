<?php

declare(strict_types=1);

namespace GarageHub\Catalogo;

/**
 * Guarda as respostas da FIPE em arquivos JSON. Listas de marcas, modelos e anos quase não mudam;
 * o valor muda uma vez por mês. Assim a maioria das consultas nem chega ao provedor.
 *
 * Se o provedor cair, devolve a última resposta guardada mesmo vencida, em vez de falhar.
 */
final class FipeComCache implements FonteFipe
{
    private const TTL_LISTAS = 7 * 86400;
    private const TTL_DETALHE = 86400;

    public function __construct(
        private readonly FonteFipe $fonte,
        private readonly string $pasta,
    ) {
    }

    public function marcas(): array
    {
        return $this->lembrar('marcas', self::TTL_LISTAS, fn () => $this->fonte->marcas());
    }

    public function modelos(string $marcaId): array
    {
        return $this->lembrar("modelos-{$marcaId}", self::TTL_LISTAS, fn () => $this->fonte->modelos($marcaId));
    }

    public function anos(string $marcaId, string $modeloId): array
    {
        return $this->lembrar("anos-{$marcaId}-{$modeloId}", self::TTL_LISTAS, fn () => $this->fonte->anos($marcaId, $modeloId));
    }

    public function detalhe(string $marcaId, string $modeloId, string $anoId): array
    {
        return $this->lembrar(
            "detalhe-{$marcaId}-{$modeloId}-{$anoId}",
            self::TTL_DETALHE,
            fn () => $this->fonte->detalhe($marcaId, $modeloId, $anoId),
        );
    }

    private function lembrar(string $chave, int $ttl, callable $buscar): array
    {
        // As chaves vêm de ids já validados (só dígitos e hífen), mas o nome do arquivo é sempre saneado
        $arquivo = $this->pasta . '/fipe-' . preg_replace('/[^a-z0-9-]/', '', $chave) . '.json';
        $guardado = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;

        if (is_array($guardado) && filemtime($arquivo) > time() - $ttl) {
            return $guardado;
        }

        try {
            $dados = $buscar();
        } catch (FipeIndisponivel $e) {
            if (is_array($guardado)) {
                return $guardado;
            }
            throw $e;
        }

        if (!is_dir($this->pasta)) {
            @mkdir($this->pasta, 0775, true);
        }
        @file_put_contents($arquivo, json_encode($dados, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $dados;
    }
}
