import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { API_URL, RespostaApi } from '../api';

export interface ItemCatalogo {
  id: string;
  nome: string;
}

export interface AnoCatalogo extends ItemCatalogo {
  ano: number;
  combustivel: string | null;
}

export interface DetalheFipe {
  fipeMarcaId: string;
  fipeModeloId: string;
  fipeAnoId: string;
  marca: string;
  modelo: string;
  versao: string | null;
  nomeFipe: string;
  anoModelo: number;
  zeroKm: boolean;
  combustivel: string;
  codigoFipe: string;
  valor: number | null;
  mesReferencia: string | null;
}

/** Catálogo da Tabela FIPE, sempre via API do GarageHub (que faz cache e trata limites). */
@Injectable({ providedIn: 'root' })
export class CatalogoService {
  private readonly http = inject(HttpClient);
  private readonly base = `${API_URL}/catalog/brands`;

  marcas(): Observable<ItemCatalogo[]> {
    return this.http.get<RespostaApi<ItemCatalogo[]>>(this.base).pipe(map((r) => r.data));
  }

  modelos(marcaId: string): Observable<ItemCatalogo[]> {
    return this.http.get<RespostaApi<ItemCatalogo[]>>(`${this.base}/${marcaId}/models`).pipe(map((r) => r.data));
  }

  anos(marcaId: string, modeloId: string): Observable<AnoCatalogo[]> {
    return this.http
      .get<RespostaApi<AnoCatalogo[]>>(`${this.base}/${marcaId}/models/${modeloId}/years`)
      .pipe(map((r) => r.data));
  }

  detalhe(marcaId: string, modeloId: string, anoId: string): Observable<DetalheFipe> {
    return this.http
      .get<RespostaApi<DetalheFipe>>(`${this.base}/${marcaId}/models/${modeloId}/years/${anoId}`)
      .pipe(map((r) => r.data));
  }
}
