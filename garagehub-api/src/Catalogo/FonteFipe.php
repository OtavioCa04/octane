<?php

declare(strict_types=1);

namespace GarageHub\Catalogo;

/**
 * Fonte dos dados da Tabela FIPE. Devolve os dados crus do provedor;
 * a normalização fica no CatalogoService. Trocar de provedor = nova implementação desta interface.
 */
interface FonteFipe
{
    /** @return list<array{code: string, name: string}> */
    public function marcas(): array;

    /** @return list<array{code: string, name: string}> */
    public function modelos(string $marcaId): array;

    /** @return list<array{code: string, name: string}> */
    public function anos(string $marcaId, string $modeloId): array;

    /** @return array{price: string, brand: string, model: string, modelYear: int, fuel: string, codeFipe: string, referenceMonth: string} */
    public function detalhe(string $marcaId, string $modeloId, string $anoId): array;
}
