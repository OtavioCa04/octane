import { Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { filter } from 'rxjs';
import { AuthService } from '../../../core/auth/auth.service';
import { Logo } from '../logo/logo';

@Component({
  selector: 'gh-site-header',
  imports: [RouterLink, RouterLinkActive, Logo],
  template: `
    <div class="conteudo">
      <a routerLink="/" class="logo"><gh-logo /></a>
      <form class="busca" role="search" (submit)="buscar($event, campo.value)">
        <label class="sr-only" for="busca-topo">Buscar</label>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" />
        </svg>
        <input #campo id="busca-topo" type="search" name="q" placeholder="Buscar carros, pessoas, peças" [value]="termoAtual()" />
      </form>
      <nav>
        @if (auth.usuario(); as usuario) {
          <a routerLink="/feed" routerLinkActive="ativo">Feed</a>
          <a [routerLink]="['/', usuario.username]" routerLinkActive="ativo" [routerLinkActiveOptions]="{ exact: true }">Minha garagem</a>
          <a routerLink="/garagem/novo" class="gh-btn gh-btn--primary">Adicionar carro</a>
          <button type="button" class="gh-btn" (click)="sair()">Sair</button>
        } @else {
          <a routerLink="/entrar">Entrar</a>
          <a routerLink="/cadastro" class="gh-btn gh-btn--primary">Criar garagem</a>
        }
      </nav>
    </div>
  `,
  styles: `
    :host {
      display: block;
      position: sticky;
      top: 0;
      z-index: 20;
      background: rgba(11, 13, 17, 0.72);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border-bottom: 1px solid var(--gh-border);
    }
    .conteudo {
      max-width: var(--gh-max-width);
      margin: 0 auto;
      padding: 14px 24px;
      display: flex;
      align-items: center;
      gap: 24px;
      flex-wrap: wrap;
    }
    .logo {
      font-size: 19px;
    }
    .busca {
      flex: 1 1 240px;
      max-width: 420px;
      display: flex;
      align-items: center;
      gap: 8px;
      height: 40px;
      padding: 0 12px;
      background: var(--gh-input);
      border: 1px solid var(--gh-border);
      border-radius: var(--gh-radius);
      color: var(--gh-text-faint);
    }
    .busca input {
      flex: 1;
      min-width: 0;
      background: none;
      border: 0;
      color: var(--gh-text);
      font: 400 14px var(--gh-font-body);
      outline: none;
    }
    nav {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 20px;
      font-size: 14px;
    }
    nav a:not(.gh-btn) {
      font-weight: 500;
    }
    nav a:not(.gh-btn):hover {
      color: var(--gh-accent-text);
    }
    nav a.ativo {
      color: var(--gh-text);
      box-shadow: inset 0 -2px 0 var(--gh-accent);
      padding-bottom: 4px;
    }
    nav a {
      white-space: nowrap;
    }
    @media (max-width: 640px) {
      :host {
        position: static;
      }
      .conteudo {
        padding: 12px 16px;
        gap: 12px;
      }
      .logo {
        font-size: 16px;
      }
      .busca {
        order: 3;
        flex-basis: 100%;
        max-width: none;
      }
      nav {
        gap: 14px;
        font-size: 13px;
      }
      nav .gh-btn {
        height: 34px;
        padding: 0 12px;
        font-size: 13px;
      }
    }
    @media (max-width: 420px) {
      /* Sem espaço para tudo numa linha: os links vão para baixo da busca */
      nav {
        order: 4;
        flex-basis: 100%;
        margin-left: 0;
      }
      nav .gh-btn:last-child {
        margin-left: auto;
      }
    }
  `,
})
export class SiteHeader {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  // Na tela de busca, o campo mostra o termo buscado
  private readonly url = toSignal(this.router.events.pipe(filter((e) => e instanceof NavigationEnd)), { initialValue: null });
  protected readonly termoAtual = computed(() => {
    this.url();
    const arvore = this.router.parseUrl(this.router.url);
    return arvore.root.children['primary']?.segments[0]?.path === 'buscar' ? (arvore.queryParams['q'] ?? '') : '';
  });

  protected buscar(evento: Event, termo: string): void {
    evento.preventDefault();
    if (termo.trim()) {
      this.router.navigate(['/buscar'], { queryParams: { q: termo.trim() } });
    }
  }

  protected sair(): void {
    this.auth.sair().subscribe(() => this.router.navigate(['/entrar']));
  }
}
