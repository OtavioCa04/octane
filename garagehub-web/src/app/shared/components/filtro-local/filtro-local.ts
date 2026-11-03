import { Component, computed, inject, input, output, signal } from '@angular/core';
import { AuthService } from '../../../core/auth/auth.service';
import { Cidade } from '../../../core/services/busca.service';
import { SeletorCidade } from '../seletor-cidade/seletor-cidade';

/** Onde o Explorar está olhando: o Brasil todo, um estado ou uma cidade. */
export type Lugar = { tipo: 'brasil' } | { tipo: 'estado'; uf: string } | { tipo: 'cidade'; cidade: Cidade };

/**
 * Botões "Brasil · SP · Campinas · Outra cidade" para o Explorar.
 * A cidade dos botões é a que está sendo vista ou, se nenhuma, a do perfil;
 * "Outra cidade" abre a busca de municípios para olhar qualquer lugar.
 */
@Component({
  selector: 'gh-filtro-local',
  imports: [SeletorCidade],
  template: `
    <div class="filtro" role="group" aria-label="Filtrar por local">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" />
      </svg>
      <button type="button" [class.ativo]="lugar().tipo === 'brasil'" [attr.aria-pressed]="lugar().tipo === 'brasil'" (click)="mudou.emit({ tipo: 'brasil' })">
        Brasil
      </button>
      @if (base(); as c) {
        <button type="button" [class.ativo]="lugar().tipo === 'estado'" [attr.aria-pressed]="lugar().tipo === 'estado'" (click)="mudou.emit({ tipo: 'estado', uf: c.uf })">
          {{ c.uf }}
        </button>
        <button type="button" [class.ativo]="lugar().tipo === 'cidade'" [attr.aria-pressed]="lugar().tipo === 'cidade'" (click)="mudou.emit({ tipo: 'cidade', cidade: c })">
          {{ c.nome }}
        </button>
      } @else if (lugar(); as l) {
        @if (l.tipo === 'estado') {
          <button type="button" class="ativo" aria-pressed="true">{{ l.uf }}</button>
        }
      }
      <button type="button" class="outra" [attr.aria-expanded]="escolhendo()" (click)="escolhendo.set(!escolhendo())">
        {{ base() ? 'Outra cidade' : 'Escolher cidade' }}
      </button>
    </div>
    @if (escolhendo()) {
      <div class="seletor">
        <label class="sr-only" for="filtro-cidade">Ver o Explorar de qual cidade?</label>
        <gh-seletor-cidade idCampo="filtro-cidade" [cidade]="null" (cidadeChange)="escolher($event)" />
        <p>Veja o que a galera de qualquer cidade está postando. Sua cidade no perfil continua a mesma.</p>
      </div>
    }
  `,
  styles: `
    .filtro {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--gh-text-muted);
    }
    button {
      height: 32px;
      padding: 0 12px;
      border-radius: 999px;
      border: 1px solid var(--gh-border-strong);
      background: none;
      color: var(--gh-text-2);
      font: 500 13px var(--gh-font-body);
      cursor: pointer;
    }
    button.ativo {
      border-color: var(--gh-accent);
      background: var(--gh-accent-soft);
      color: var(--gh-accent-text);
    }
    button.outra {
      border-style: dashed;
      color: var(--gh-text-muted);
    }
    button.outra:hover,
    button.outra[aria-expanded='true'] {
      color: var(--gh-text);
      border-color: var(--gh-accent-line);
    }
    .seletor {
      margin-top: 12px;
      max-width: 420px;
    }
    .seletor p {
      margin: 8px 0 0;
      font-size: 12px;
      color: var(--gh-text-faint);
    }
  `,
})
export class FiltroLocal {
  private readonly auth = inject(AuthService);

  readonly lugar = input<Lugar>({ tipo: 'brasil' });
  // Última cidade escolhida em "Outra cidade", para os botões continuarem nela
  readonly ultimaCidade = input<Cidade | null>(null);
  readonly mudou = output<Lugar>();

  protected readonly escolhendo = signal(false);

  // A cidade dos botões: a que está sendo vista, a última escolhida ou a do perfil
  protected readonly base = computed<Cidade | null>(() => {
    const l = this.lugar();
    if (l.tipo === 'cidade') return l.cidade;
    const ultima = this.ultimaCidade();
    if (ultima && (l.tipo === 'brasil' || ultima.uf === l.uf)) return ultima;
    const u = this.auth.usuario();
    const doPerfil = u?.cidadeId && u.uf ? { id: u.cidadeId, nome: u.cidade?.replace(/, [A-Z]{2}$/, '') ?? '', uf: u.uf } : null;
    return l.tipo === 'estado' && doPerfil && doPerfil.uf !== l.uf ? null : doPerfil;
  });

  protected escolher(cidade: Cidade | null): void {
    if (cidade) {
      this.escolhendo.set(false);
      this.mudou.emit({ tipo: 'cidade', cidade });
    }
  }
}
