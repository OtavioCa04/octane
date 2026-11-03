<?php

declare(strict_types=1);

namespace GarageHub\Social;

use GarageHub\Core\HttpException;
use GarageHub\Core\Request;
use GarageHub\Core\Response;
use GarageHub\Core\Validator;
use GarageHub\Garagem\Apresentador;

/**
 * Curtir e comentar carros e modificações.
 *   POST/DELETE /api/likes/{tipo}/{id}
 *   GET/POST    /api/comments/{tipo}/{id}
 *   DELETE      /api/comments/{id}   (quem escreveu, ou o dono do carro)
 */
final class InteracoesController
{
    private const POR_PAGINA = 50;
    private const MAX_COMENTARIOS_POR_MINUTO = 10;

    public function __construct(private readonly InteracoesRepository $interacoes)
    {
    }

    public function curtir(Request $request): Response
    {
        [$tipo, $id] = $this->alvo($request);
        $this->interacoes->curtir((int) $request->usuario['id'], $tipo, $id);
        return $this->estado($request, $tipo, $id);
    }

    public function descurtir(Request $request): Response
    {
        [$tipo, $id] = $this->alvo($request);
        $this->interacoes->descurtir((int) $request->usuario['id'], $tipo, $id);
        return $this->estado($request, $tipo, $id);
    }

    public function listar(Request $request): Response
    {
        [$tipo, $id, $alvo] = $this->alvo($request);
        $pagina = max(1, (int) ($request->query['pagina'] ?? 1));
        $eu = $request->usuario ? (int) $request->usuario['id'] : null;

        $comentarios = $this->interacoes->comentarios($tipo, $id, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA);
        return Response::json([
            'itens' => array_map(fn ($c) => self::comentario($c, $eu, $alvo['dono_id']), $comentarios),
            'total' => $this->interacoes->totalComentarios($tipo, $id),
        ]);
    }

    public function comentar(Request $request): Response
    {
        [$tipo, $id, $alvo] = $this->alvo($request);
        $eu = (int) $request->usuario['id'];
        $dados = Validator::validar($request->body, ['texto' => ['required', 'string', 'max:1000']]);

        if ($this->interacoes->comentariosRecentes($eu, 60) >= self::MAX_COMENTARIOS_POR_MINUTO) {
            throw new HttpException(429, 'Calma aí! Espere um pouco antes de comentar de novo.');
        }

        $novo = $this->interacoes->comentario($this->interacoes->comentar($eu, $tipo, $id, $dados['texto']));
        return Response::criado(self::comentario($novo, $eu, $alvo['dono_id']));
    }

    public function excluirComentario(Request $request): Response
    {
        $eu = (int) $request->usuario['id'];
        $comentario = $this->interacoes->comentario($request->id())
            ?? throw HttpException::naoEncontrado('Comentário não encontrado.');
        $alvo = $this->interacoes->alvoVisivel($comentario['alvo_tipo'], (int) $comentario['alvo_id'], $eu);

        $podeApagar = (int) $comentario['usuario_id'] === $eu || ($alvo !== null && $alvo['dono_id'] === $eu);
        if (!$podeApagar) {
            throw new HttpException(403, 'Você só pode apagar os seus comentários ou os feitos nos seus carros.');
        }
        $this->interacoes->excluirComentario((int) $comentario['id']);
        return Response::semConteudo();
    }

    /** @return array{0: string, 1: int, 2: array{dono_id: int, veiculo_id: int}} */
    private function alvo(Request $request): array
    {
        $tipo = $request->params['tipo'] ?? '';
        if (!in_array($tipo, InteracoesRepository::TIPOS, true)) {
            throw HttpException::naoEncontrado();
        }
        $id = $request->id();
        $alvo = $this->interacoes->alvoVisivel($tipo, $id, $request->usuario ? (int) $request->usuario['id'] : null)
            ?? throw HttpException::naoEncontrado();
        return [$tipo, $id, $alvo];
    }

    private function estado(Request $request, string $tipo, int $id): Response
    {
        return Response::json($this->interacoes->contagens($tipo, [$id], (int) $request->usuario['id'])[$id]);
    }

    private static function comentario(array $c, ?int $eu, int $donoDoAlvo): array
    {
        return [
            'id' => (int) $c['id'],
            'texto' => $c['texto'],
            'data' => $c['criado_em'],
            'autor' => [
                'username' => $c['username'],
                'nome' => $c['nome'],
                'iniciais' => Apresentador::iniciais($c['nome']),
                'avatarUrl' => Apresentador::avatarUrl($c['avatar'] ?? null),
            ],
            // O front mostra "Apagar" para quem escreveu e para o dono do carro
            'podeApagar' => $eu !== null && ((int) $c['usuario_id'] === $eu || $donoDoAlvo === $eu),
        ];
    }
}
