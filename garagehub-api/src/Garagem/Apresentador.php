<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Auth\UsuarioRepository;

/**
 * Converte as linhas do banco no JSON da API (camelCase, igual aos modelos do Angular).
 * A versão pública esconde placa, valores pagos e dados internos.
 */
final class Apresentador
{
    /** @param list<array>|null $fotos todas as fotos (páginas de detalhe); null nas listas, que só usam a capa */
    public static function veiculo(array $v, array $modificacoes, bool $dono, ?array $fotos = null): array
    {
        $dados = [
            'id' => (int) $v['id'],
            'slug' => $v['slug'],
            'marca' => $v['marca'],
            'modelo' => $v['modelo'],
            'versao' => $v['versao'],
            'anoFabricacao' => (int) $v['ano_fabricacao'],
            'anoModelo' => (int) $v['ano_modelo'],
            'motor' => $v['motor'],
            'cambio' => $v['cambio'],
            'combustivel' => $v['combustivel'],
            'cor' => $v['cor'],
            'corHex' => $v['cor_hex'],
            'carroceria' => $v['carroceria'],
            'quilometragem' => (int) $v['quilometragem'],
            'status' => $v['status'],
            'descricao' => $v['descricao'],
            'valorFipe' => self::decimal($v['valor_fipe']),
            'dataConsultaFipe' => $v['data_consulta_fipe'],
            'fotoUrl' => !empty($v['foto_capa']) ? FotoStorage::url($v['foto_capa']) : $v['foto_url'],
            'fotoModeloUrl' => $v['foto_modelo_url'] ?? null,
            'modificacoes' => array_map(fn ($m) => self::modificacao($m, $dono), $modificacoes),
        ];
        if ($fotos !== null) {
            $dados['fotos'] = array_map(fn ($f) => self::foto($f), $fotos);
        }

        if ($dono) {
            $dados += [
                'placa' => $v['placa'],
                'precoCompra' => self::decimal($v['preco_compra']),
                'fipeCodigo' => $v['fipe_codigo'],
                'fipeMarcaId' => $v['fipe_marca_id'] ?? null,
                'fipeModeloId' => $v['fipe_modelo_id'] ?? null,
                'fipeAnoId' => $v['fipe_ano_id'] ?? null,
                'visivel' => (bool) $v['visivel'],
                'ordem' => (int) $v['ordem'],
                'criadoEm' => $v['criado_em'],
                'atualizadoEm' => $v['atualizado_em'],
            ];
        }
        return $dados;
    }

    public static function modificacao(array $m, bool $dono): array
    {
        $dados = [
            'id' => (int) $m['id'],
            'nome' => $m['nome'],
            'categoria' => $m['categoria'],
            'descricao' => $m['descricao'],
            'data' => $m['data_instalacao'],
            'quilometragem' => $m['quilometragem'] === null ? null : (int) $m['quilometragem'],
            'marcaPeca' => $m['marca_peca'],
            'oficina' => $m['oficina'],
        ];
        if ($dono) {
            $dados['valor'] = self::decimal($m['valor']);
        }
        return $dados;
    }

    public static function foto(array $f): array
    {
        return [
            'id' => (int) $f['id'],
            'url' => FotoStorage::url($f['arquivo']),
            'capa' => (bool) $f['capa'],
            'largura' => (int) $f['largura'],
            'altura' => (int) $f['altura'],
        ];
    }

    public static function perfilPublico(array $u, array $veiculos): array
    {
        return [
            'username' => $u['username'],
            'nome' => $u['nome'],
            'cidade' => UsuarioRepository::cidade($u),
            'bio' => $u['bio'],
            'iniciais' => self::iniciais($u['nome']),
            'avatarUrl' => self::avatarUrl($u['avatar'] ?? null),
            'veiculos' => $veiculos,
        ];
    }

    public static function avatarUrl(?string $arquivo): ?string
    {
        return $arquivo ? FotoStorage::url($arquivo) : null;
    }

    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $letras = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), [reset($partes), count($partes) > 1 ? end($partes) : '']);
        return implode('', $letras);
    }

    private static function decimal(mixed $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
    }
}
