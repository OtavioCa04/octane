import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, catchError, map, of, throwError } from 'rxjs';
import { API_URL, RespostaApi } from '../api';
import { Foto, Modificacao, ModificacaoEntrada, Perfil, Veiculo, VeiculoDono, VeiculoEntrada } from '../models/veiculo';

@Injectable({ providedIn: 'root' })
export class GaragemService {
  private readonly http = inject(HttpClient);

  /** Perfil público. undefined quando não existe ou é privado. */
  perfil(username: string): Observable<Perfil | undefined> {
    return this.http
      .get<RespostaApi<Perfil>>(`${API_URL}/users/${encodeURIComponent(username)}`)
      .pipe(map((r) => r.data), naoEncontradoVira(undefined));
  }

  /** Página pública de um carro. undefined quando não existe ou está oculto. */
  veiculo(username: string, slug: string): Observable<{ perfil: Perfil; veiculo: Veiculo } | undefined> {
    return this.http
      .get<RespostaApi<{ perfil: Perfil; veiculo: Veiculo }>>(
        `${API_URL}/users/${encodeURIComponent(username)}/vehicles/${encodeURIComponent(slug)}`,
      )
      .pipe(map((r) => r.data), naoEncontradoVira(undefined));
  }

  // Garagem do usuário logado

  meuVeiculo(id: number): Observable<VeiculoDono | undefined> {
    return this.http
      .get<RespostaApi<VeiculoDono>>(`${API_URL}/vehicles/${id}`)
      .pipe(map((r) => r.data), naoEncontradoVira(undefined));
  }

  criar(dados: VeiculoEntrada): Observable<VeiculoDono> {
    return this.http.post<RespostaApi<VeiculoDono>>(`${API_URL}/vehicles`, dados).pipe(map((r) => r.data));
  }

  atualizar(id: number, dados: VeiculoEntrada): Observable<VeiculoDono> {
    return this.http.put<RespostaApi<VeiculoDono>>(`${API_URL}/vehicles/${id}`, dados).pipe(map((r) => r.data));
  }

  excluir(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/vehicles/${id}`);
  }

  // Modificações de um carro do usuário logado

  criarModificacao(veiculoId: number, dados: ModificacaoEntrada): Observable<Modificacao> {
    return this.http
      .post<RespostaApi<Modificacao>>(`${API_URL}/vehicles/${veiculoId}/modifications`, dados)
      .pipe(map((r) => r.data));
  }

  atualizarModificacao(id: number, dados: ModificacaoEntrada): Observable<Modificacao> {
    return this.http.put<RespostaApi<Modificacao>>(`${API_URL}/modifications/${id}`, dados).pipe(map((r) => r.data));
  }

  excluirModificacao(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/modifications/${id}`);
  }

  // Fotos de um carro do usuário logado

  enviarFoto(veiculoId: number, foto: Blob): Observable<Foto> {
    const dados = new FormData();
    dados.append('foto', foto, 'foto.jpg');
    return this.http.post<RespostaApi<Foto>>(`${API_URL}/vehicles/${veiculoId}/photos`, dados).pipe(map((r) => r.data));
  }

  definirCapa(fotoId: number): Observable<Foto> {
    return this.http.put<RespostaApi<Foto>>(`${API_URL}/photos/${fotoId}/cover`, {}).pipe(map((r) => r.data));
  }

  excluirFoto(fotoId: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/photos/${fotoId}`);
  }
}

// 404 é um resultado esperado (garagem inexistente ou privada); outros erros seguem adiante
function naoEncontradoVira<T, R>(valor: R) {
  return catchError<T, Observable<T | R>>((erro: unknown) =>
    erro instanceof HttpErrorResponse && erro.status === 404 ? of(valor) : throwError(() => erro),
  );
}
