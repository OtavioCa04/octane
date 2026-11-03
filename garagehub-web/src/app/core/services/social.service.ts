import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { API_URL, RespostaApi } from '../api';
import { AbaFeed, Comentario, EstadoSeguir, Interacao, PaginaFeed, Sugestao } from '../models/social';
import { FiltroLocal } from './busca.service';

@Injectable({ providedIn: 'root' })
export class SocialService {
  private readonly http = inject(HttpClient);

  /** local: no Explorar, "cidade" ou "estado" filtram pela região de quem está vendo. */
  feed(aba: AbaFeed, pagina: number, local: FiltroLocal = {}): Observable<PaginaFeed> {
    const params: Record<string, string | number> = { aba, pagina };
    if (aba === 'explorar' && local.cidade) params['cidade'] = local.cidade;
    else if (aba === 'explorar' && local.uf) params['uf'] = local.uf;
    return this.http.get<RespostaApi<PaginaFeed>>(`${API_URL}/feed`, { params }).pipe(map((r) => r.data));
  }

  sugestoes(): Observable<Sugestao[]> {
    return this.http.get<RespostaApi<Sugestao[]>>(`${API_URL}/users/suggestions`).pipe(map((r) => r.data));
  }

  seguirUsuario(username: string, seguir: boolean): Observable<EstadoSeguir> {
    const url = `${API_URL}/users/${encodeURIComponent(username)}/follow`;
    const pedido = seguir ? this.http.post<RespostaApi<EstadoSeguir>>(url, {}) : this.http.delete<RespostaApi<EstadoSeguir>>(url);
    return pedido.pipe(map((r) => r.data));
  }

  curtir(tipo: Interacao['tipo'], id: number, curtir: boolean): Observable<Omit<Interacao, 'tipo' | 'id'>> {
    const url = `${API_URL}/likes/${tipo}/${id}`;
    const pedido = curtir ? this.http.post<RespostaApi<Interacao>>(url, {}) : this.http.delete<RespostaApi<Interacao>>(url);
    return pedido.pipe(map((r) => r.data));
  }

  comentarios(tipo: Interacao['tipo'], id: number): Observable<{ itens: Comentario[]; total: number }> {
    return this.http
      .get<RespostaApi<{ itens: Comentario[]; total: number }>>(`${API_URL}/comments/${tipo}/${id}`)
      .pipe(map((r) => r.data));
  }

  comentar(tipo: Interacao['tipo'], id: number, texto: string): Observable<Comentario> {
    return this.http
      .post<RespostaApi<Comentario>>(`${API_URL}/comments/${tipo}/${id}`, { texto })
      .pipe(map((r) => r.data));
  }

  apagarComentario(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/comments/${id}`);
  }

  seguirVeiculo(veiculoId: number, seguir: boolean): Observable<EstadoSeguir> {
    const url = `${API_URL}/vehicles/${veiculoId}/follow`;
    const pedido = seguir ? this.http.post<RespostaApi<EstadoSeguir>>(url, {}) : this.http.delete<RespostaApi<EstadoSeguir>>(url);
    return pedido.pipe(map((r) => r.data));
  }
}
