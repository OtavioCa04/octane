import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, map, tap } from 'rxjs';
import { API_URL, RespostaApi } from '../api';

export interface Usuario {
  id: number;
  nome: string;
  username: string;
  email: string;
  cidade: string | null; // "Campinas, SP"
  cidadeId: number | null; // código do IBGE
  uf: string | null;
  bio: string | null;
  avatarUrl: string | null;
  perfilPrivado: boolean;
  criadoEm: string;
}

interface Sessao {
  usuario: Usuario;
  token: string;
}

export interface DadosCadastro {
  nome: string;
  username: string;
  email: string;
  senha: string;
}

export interface DadosPerfil {
  nome: string;
  bio: string | null;
  cidadeId: number | null;
  perfilPrivado: boolean;
}

const CHAVE = 'gh.sessao';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly sessao = signal<Sessao | null>(lerSessao());

  readonly usuario = computed(() => this.sessao()?.usuario ?? null);
  readonly token = computed(() => this.sessao()?.token ?? null);
  readonly logado = computed(() => this.sessao() !== null);

  entrar(email: string, senha: string, manterConectado: boolean): Observable<Usuario> {
    return this.http.post<RespostaApi<Sessao>>(`${API_URL}/auth/login`, { email, senha }).pipe(
      tap((r) => this.salvar(r.data, manterConectado)),
      map((r) => r.data.usuario),
    );
  }

  cadastrar(dados: DadosCadastro): Observable<Usuario> {
    return this.http.post<RespostaApi<Sessao>>(`${API_URL}/auth/register`, dados).pipe(
      tap((r) => this.salvar(r.data, true)),
      map((r) => r.data.usuario),
    );
  }

  /** Pede o e-mail com o link de redefinição. A API responde igual exista a conta ou não. */
  esqueciSenha(email: string): Observable<void> {
    return this.http.post<void>(`${API_URL}/auth/forgot-password`, { email });
  }

  /** Troca a senha com o código do e-mail. A API encerra todas as sessões da conta. */
  redefinirSenha(token: string, senha: string): Observable<void> {
    return this.http.post<void>(`${API_URL}/auth/reset-password`, { token, senha }).pipe(tap(() => this.limpar()));
  }

  /** Atualiza nome, bio, cidade e privacidade, e guarda o usuário novo na sessão. */
  editarPerfil(dados: DadosPerfil): Observable<Usuario> {
    return this.http.put<RespostaApi<Usuario>>(`${API_URL}/auth/me`, dados).pipe(
      map((r) => r.data),
      tap((usuario) => this.atualizarUsuario(usuario)),
    );
  }

  /** Troca a foto de perfil (JPEG já preparado por prepararFoto). */
  enviarAvatar(foto: Blob): Observable<Usuario> {
    const dados = new FormData();
    dados.append('avatar', foto, 'avatar.jpg');
    return this.http.post<RespostaApi<Usuario>>(`${API_URL}/auth/me/avatar`, dados).pipe(
      map((r) => r.data),
      tap((usuario) => this.atualizarUsuario(usuario)),
    );
  }

  removerAvatar(): Observable<Usuario> {
    return this.http.delete<RespostaApi<Usuario>>(`${API_URL}/auth/me/avatar`).pipe(
      map((r) => r.data),
      tap((usuario) => this.atualizarUsuario(usuario)),
    );
  }

  private atualizarUsuario(usuario: Usuario): void {
    const atual = this.sessao();
    if (atual) {
      const sessao = { ...atual, usuario };
      this.sessao.set(sessao);
      // Regrava onde a sessão já estava (localStorage com "manter conectado", sessionStorage sem)
      const armazenamento = tentar(() => localStorage.getItem(CHAVE)) ? localStorage : sessionStorage;
      tentar(() => armazenamento.setItem(CHAVE, JSON.stringify(sessao)));
    }
  }

  /** Revoga o token na API e limpa a sessão local, mesmo se a API falhar. */
  sair(): Observable<void> {
    return new Observable<void>((assinante) => {
      const fim = () => {
        this.limpar();
        assinante.next();
        assinante.complete();
      };
      this.http.post(`${API_URL}/auth/logout`, {}).subscribe({ next: fim, error: fim });
    });
  }

  limpar(): void {
    this.sessao.set(null);
    tentar(() => localStorage.removeItem(CHAVE));
    tentar(() => sessionStorage.removeItem(CHAVE));
  }

  // "Manter conectado" guarda no localStorage; senão a sessão some ao fechar o navegador
  private salvar(sessao: Sessao, manterConectado: boolean): void {
    this.limpar();
    this.sessao.set(sessao);
    const armazenamento = manterConectado ? localStorage : sessionStorage;
    tentar(() => armazenamento.setItem(CHAVE, JSON.stringify(sessao)));
  }
}

function lerSessao(): Sessao | null {
  for (const armazenamento of [localStorage, sessionStorage]) {
    const bruto = tentar(() => armazenamento.getItem(CHAVE));
    if (bruto) {
      try {
        return JSON.parse(bruto) as Sessao;
      } catch {
        // sessão corrompida: ignora
      }
    }
  }
  return null;
}

// O armazenamento do navegador pode estar bloqueado (modo privado, cookies desativados)
function tentar<T>(fn: () => T): T | null {
  try {
    return fn();
  } catch {
    return null;
  }
}
