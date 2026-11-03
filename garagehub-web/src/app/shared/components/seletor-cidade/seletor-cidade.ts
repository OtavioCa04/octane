import { Component, DestroyRef, effect, inject, input, model, signal, untracked } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subject, debounceTime, distinctUntilChanged, of, switchMap } from 'rxjs';
import { BuscaService, Cidade } from '../../../core/services/busca.service';

/**
 * Campo de cidade com autocompletar (municípios do IBGE).
 * Digita o nome, escolhe na lista; o valor é a cidade escolhida ou null.
 * Teclado: ↑ ↓ para navegar, Enter para escolher, Esc para fechar.
 */
@Component({
  selector: 'gh-seletor-cidade',
  template: `
    <div class="campo">
      <input
        type="text"
        role="combobox"
        autocomplete="off"
        [id]="idCampo()"
        [attr.aria-expanded]="aberto()"
        [attr.aria-controls]="idCampo() + '-lista'"
        [attr.aria-activedescendant]="ativo() >= 0 ? idCampo() + '-op-' + ativo() : null"
        placeholder="Comece a digitar: Campinas, Curitiba…"
        [value]="texto()"
        (input)="digitar($any($event.target).value)"
        (keydown)="tecla($event)"
        (blur)="fecharDepois()"
      />
      @if (cidade()) {
        <button type="button" class="limpar" (click)="escolher(null)" aria-label="Remover cidade">×</button>
      }
    </div>
    @if (aberto() && opcoes().length) {
      <ul class="lista" role="listbox" [id]="idCampo() + '-lista'">
        @for (c of opcoes(); track c.id; let i = $index) {
          <li
            role="option"
            [id]="idCampo() + '-op-' + i"
            [class.ativo]="i === ativo()"
            [attr.aria-selected]="i === ativo()"
            (mousedown)="$event.preventDefault(); escolher(c)"
          >
            {{ c.nome }} <span>{{ c.uf }}</span>
          </li>
        }
      </ul>
    } @else if (aberto() && buscou() && texto().trim().length >= 2) {
      <p class="nada">Nenhuma cidade com esse nome.</p>
    }
  `,
  styles: `
    :host {
      position: relative;
      display: block;
    }
    .campo {
      position: relative;
    }
    input {
      width: 100%;
      height: 50px;
      padding: 0 40px 0 14px;
      background: var(--gh-input);
      border: 1px solid var(--gh-border);
      border-radius: var(--gh-radius);
      color: var(--gh-text);
      font: 400 15px var(--gh-font-body);
    }
    input:focus {
      outline: none;
      border-color: var(--gh-accent);
    }
    .limpar {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      width: 30px;
      height: 30px;
      border: 0;
      border-radius: 50%;
      background: none;
      color: var(--gh-text-muted);
      font-size: 20px;
      cursor: pointer;
    }
    .lista,
    .nada {
      position: absolute;
      z-index: 20;
      left: 0;
      right: 0;
      top: calc(100% + 4px);
      margin: 0;
      background: var(--gh-surface-2);
      border: 1px solid var(--gh-border-strong);
      border-radius: var(--gh-radius);
    }
    .lista {
      list-style: none;
      padding: 4px;
      max-height: 280px;
      overflow-y: auto;
    }
    li {
      padding: 10px 12px;
      border-radius: 6px;
      font-size: 14px;
      cursor: pointer;
    }
    li span {
      color: var(--gh-text-faint);
      margin-left: 4px;
    }
    li.ativo,
    li:hover {
      background: var(--gh-accent-soft);
    }
    .nada {
      padding: 12px;
      font-size: 13px;
      color: var(--gh-text-muted);
    }
  `,
})
export class SeletorCidade {
  private readonly busca = inject(BuscaService);

  readonly cidade = model<Cidade | null>(null);
  readonly idCampo = input('cidade');

  protected readonly texto = signal('');
  protected readonly opcoes = signal<Cidade[]>([]);
  protected readonly aberto = signal(false);
  protected readonly ativo = signal(-1);
  protected readonly buscou = signal(false);
  private readonly termos = new Subject<string>();

  constructor() {
    // Mostra no campo a cidade escolhida, inclusive a que já vem preenchida
    effect(() => {
      const c = this.cidade();
      if (c) untracked(() => this.texto.set(`${c.nome}, ${c.uf}`));
    });

    this.termos
      .pipe(
        debounceTime(250),
        distinctUntilChanged(),
        switchMap((t) => (t.trim().length >= 2 ? this.busca.cidades(t.trim()) : of([]))),
        takeUntilDestroyed(inject(DestroyRef)),
      )
      .subscribe((cidades) => {
        this.opcoes.set(cidades);
        this.ativo.set(cidades.length ? 0 : -1);
        this.buscou.set(true);
      });
  }

  protected digitar(valor: string): void {
    this.texto.set(valor);
    this.aberto.set(true);
    this.buscou.set(false);
    // Mudou o texto: a cidade escolhida antes deixa de valer até escolher outra
    if (this.cidade()) this.cidade.set(null);
    this.termos.next(valor);
  }

  protected escolher(c: Cidade | null): void {
    this.cidade.set(c);
    this.texto.set(c ? `${c.nome}, ${c.uf}` : '');
    this.aberto.set(false);
    this.opcoes.set([]);
  }

  protected tecla(evento: KeyboardEvent): void {
    const total = this.opcoes().length;
    if (evento.key === 'ArrowDown' && total) {
      evento.preventDefault();
      this.aberto.set(true);
      this.ativo.update((i) => (i + 1) % total);
    } else if (evento.key === 'ArrowUp' && total) {
      evento.preventDefault();
      this.ativo.update((i) => (i - 1 + total) % total);
    } else if (evento.key === 'Enter' && this.aberto() && this.ativo() >= 0) {
      evento.preventDefault();
      this.escolher(this.opcoes()[this.ativo()]);
    } else if (evento.key === 'Escape') {
      this.aberto.set(false);
    }
  }

  // Deixa o clique na opção acontecer antes de fechar a lista
  protected fecharDepois(): void {
    setTimeout(() => this.aberto.set(false), 150);
  }
}
