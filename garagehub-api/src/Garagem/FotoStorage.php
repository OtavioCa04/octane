<?php

declare(strict_types=1);

namespace GarageHub\Garagem;

use GarageHub\Core\Env;
use GarageHub\Core\HttpException;

/**
 * Guarda as fotos em disco: UPLOADS_DIR/veiculos/<id do carro>/<nome aleatório>.jpg e, as de perfil,
 * UPLOADS_DIR/avatares/<id do usuário>/<nome aleatório>.jpg.
 *
 * Só aceita JPEG: o app converte toda foto para JPEG no navegador antes de enviar.
 * Antes de gravar, remove os metadados (EXIF com GPS, XMP, IPTC, comentários), para a foto
 * pública não revelar onde o carro mora. Faz isso lendo o arquivo, sem depender da extensão GD.
 */
final class FotoStorage
{
    public const TAMANHO_MAXIMO = 10 * 1024 * 1024;
    private const LADO_MAXIMO = 6000;

    public function __construct(private readonly string $pasta)
    {
    }

    public static function doAmbiente(): self
    {
        return new self(rtrim(Env::get('UPLOADS_DIR', dirname(__DIR__, 2) . '/public/uploads'), '/\\'));
    }

    /** Endereço público de um arquivo guardado. */
    public static function url(string $arquivo): string
    {
        return rtrim(Env::get('UPLOADS_URL', '/uploads'), '/') . '/' . $arquivo;
    }

    /**
     * Valida e grava a foto. Devolve o caminho relativo e as medidas.
     *
     * @param array{tmp_name: string, error: int, size: int} $upload um item de $_FILES já conferido com is_uploaded_file
     * @return array{arquivo: string, largura: int, altura: int, tamanho: int}
     */
    public function salvar(int $veiculoId, array $upload): array
    {
        return $this->gravar("veiculos/{$veiculoId}", $upload, 'foto');
    }

    /** Foto de perfil. Devolve o caminho relativo, para guardar em usuarios.avatar. */
    public function salvarAvatar(int $usuarioId, array $upload): string
    {
        return $this->gravar("avatares/{$usuarioId}", $upload, 'avatar')['arquivo'];
    }

    /** @return array{arquivo: string, largura: int, altura: int, tamanho: int} */
    private function gravar(string $subpasta, array $upload, string $campo): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw HttpException::validacao([$campo => self::mensagemDoErroDeUpload((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE))]);
        }
        if ($upload['size'] > self::TAMANHO_MAXIMO) {
            throw HttpException::validacao([$campo => 'A foto passa de 10 MB.']);
        }

        $info = @getimagesize($upload['tmp_name']);
        if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
            throw HttpException::validacao([$campo => 'Envie uma foto em JPEG.']);
        }
        [$largura, $altura] = $info;
        if ($largura > self::LADO_MAXIMO || $altura > self::LADO_MAXIMO) {
            throw HttpException::validacao([$campo => 'A foto é grande demais (máximo de 6000 pixels por lado).']);
        }

        $limpa = self::semMetadados((string) file_get_contents($upload['tmp_name']));

        $relativo = "{$subpasta}/" . bin2hex(random_bytes(16)) . '.jpg';
        $destino = "{$this->pasta}/{$relativo}";
        if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0775, true) && !is_dir(dirname($destino))) {
            throw new \RuntimeException('Não foi possível criar a pasta de fotos.');
        }
        if (file_put_contents($destino, $limpa, LOCK_EX) === false) {
            throw new \RuntimeException('Não foi possível gravar a foto.');
        }
        @unlink($upload['tmp_name']);

        return ['arquivo' => $relativo, 'largura' => $largura, 'altura' => $altura, 'tamanho' => strlen($limpa)];
    }

    public function excluir(string $arquivo): void
    {
        // Só apaga dentro da pasta de fotos
        if (preg_match('#^(veiculos|avatares)/\d+/[a-f0-9]{32}\.jpg$#', $arquivo)) {
            @unlink("{$this->pasta}/{$arquivo}");
        }
    }

    /**
     * Copia o JPEG sem os segmentos de metadados: APP1 (EXIF e XMP), APP13 (IPTC) e COM (comentário).
     * Mantém o resto (tabelas, perfil de cor, dados da imagem) intacto.
     */
    public static function semMetadados(string $jpeg): string
    {
        $tamanho = strlen($jpeg);
        if ($tamanho < 4 || substr($jpeg, 0, 2) !== "\xFF\xD8") {
            throw HttpException::validacao(['foto' => 'Envie uma foto em JPEG.']);
        }

        $saida = "\xFF\xD8";
        $i = 2;
        while ($i + 4 <= $tamanho) {
            if ($jpeg[$i] !== "\xFF") {
                break;
            }
            // Bytes 0xFF extras entre segmentos são preenchimento
            while ($i + 1 < $tamanho && $jpeg[$i + 1] === "\xFF") {
                $i++;
            }
            $marcador = ord($jpeg[$i + 1]);

            if ($marcador === 0xDA || $marcador === 0xD9) {
                // Início da imagem comprimida (ou fim do arquivo): o resto vai como está
                return $saida . substr($jpeg, $i);
            }
            if ($marcador === 0x01 || ($marcador >= 0xD0 && $marcador <= 0xD7)) {
                $saida .= substr($jpeg, $i, 2);
                $i += 2;
                continue;
            }

            $comprimento = unpack('n', substr($jpeg, $i + 2, 2))[1];
            if ($comprimento < 2 || $i + 2 + $comprimento > $tamanho) {
                break;
            }
            if (!in_array($marcador, [0xE1, 0xED, 0xFE], true)) {
                $saida .= substr($jpeg, $i, 2 + $comprimento);
            }
            $i += 2 + $comprimento;
        }

        throw HttpException::validacao(['foto' => 'Não consegui ler essa foto. Tente outra.']);
    }

    private static function mensagemDoErroDeUpload(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A foto é maior do que o servidor aceita.',
            UPLOAD_ERR_PARTIAL => 'O envio foi interrompido. Tente de novo.',
            UPLOAD_ERR_NO_FILE => 'Escolha uma foto para enviar.',
            default => 'Não foi possível receber a foto. Tente de novo.',
        };
    }
}
