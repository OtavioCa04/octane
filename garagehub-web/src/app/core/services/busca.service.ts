import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { API_URL, RespostaApi } from '../api';
import { VeiculoResumo } from '../models/social';

export interface Cidade {
  id: number;
  nome: string;
  uf: string;
}

export interface PessoaEncontrada {
  username: string;
  nome: string;
  iniciais: string;
  avatarUrl: string | null;
  cidade: string | null;
  carros: number;
}

export interface CarroEncontrado extends VeiculoResumo {
  dono: { username: string; nome: string };
  // Nome da modificação que fez o carro aparecer na busca (ex.: "Coilover")
  modificacao: string | null;
}

export interface ResultadoBusca {
  pessoas: PessoaEncontrada[];
  carros: CarroEncontrado[];
}

/** Filtro de local: o Brasil todo, um estado ou uma cidade. */
export type FiltroLocal = { uf?: string; cidade?: number };

@Injectable({ providedIn: 'root' })
export class BuscaService {
  private readonly http = inject(HttpClient);

  cidades(termo: string): Observable<Cidade[]> {
    return this.http.get<RespostaApi<Cidade[]>>(`${API_URL}/cities`, { params: { q: termo } }).pipe(map((r) => r.data));
  }

  cidade(id: number): Observable<Cidade> {
    return this.http.get<RespostaApi<Cidade>>(`${API_URL}/cities/${id}`).pipe(map((r) => r.data));
  }

  buscar(termo: string, local: FiltroLocal): Observable<ResultadoBusca> {
    const params: Record<string, string> = { q: termo };
    if (local.cidade) params['cidade'] = String(local.cidade);
    else if (local.uf) params['uf'] = local.uf;
    return this.http.get<RespostaApi<ResultadoBusca>>(`${API_URL}/search`, { params }).pipe(map((r) => r.data));
  }
}
