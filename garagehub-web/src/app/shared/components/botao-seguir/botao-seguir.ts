import { Component, computed, inject, input, output, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { mensagemDeErro } from '../../../core/api';
import { AuthService } from '../../../core/auth/auth.service';
import { EstadoSeguir } from '../../../core/models/social';
import { SocialService } from '../../../core/services/social.service';

/**
 * Botão Seguir / Seguindo, para uma pessoa (username) ou um carro (veiculoId).
 * Visitante sem login vê um link para entrar.
 */
@Component({
  selector: 'gh-botao-seguir',
  imports: [RouterLink],
  template: `
    @if (!auth.logado()) {
      <a routerLink="/entrar" class="gh-btn gh-btn--primary" [class.compacto]="compacto()">Seguir</a>
    } @else {
      <button
        type="button"
        class="gh-btn"
        [class.gh-btn--primary]="!euSigo()"
        [class.seguindo]="euSigo()"
        [class.compacto]="compacto()"
        [disabled]="enviando()"
        [attr.aria-pressed]="euSigo()"
        [attr.title]="erro()"
        (click)="alternar()"
      >
        @if (euSigo()) {
          <span class="normal">Seguindo</span>
          <span class="hover">Deixar de seguir</span>
        } @else {
          {{ rotulo() }}
        }
      </button>
    }
  `,
  styles: `
    :host {
      display: inline-flex;
    }
    .compacto {
      height: 36px;
      padding: 0 14px;
      font-size: 13px;
    }
    .seguindo .hover {
      display: none;
    }
    .seguindo:hover .normal,
    .seguindo:focus-visible .normal {
      display: none;
    }
    .seguindo:hover .hover,
    .seguindo:focus-visible .hover {
      display: inline;
      color: var(--gh-danger-text);
    }
  `,
})
export class BotaoSeguir {
  protected readonly auth = inject(AuthService);
  private readonly social = inject(SocialService);

  readonly username = input<string>();
  readonly veiculoId = input<number>();
  readonly euSigo = input(false);
  readonly compacto = input(false);
  readonly mudou = output<EstadoSeguir>();

  protected readonly enviando = signal(false);
  protected readonly erro = signal<string | null>(null);
  protected readonly rotulo = computed(() => (this.veiculoId() ? 'Seguir carro' : 'Seguir'));

  protected alternar(): void {
    const seguir = !this.euSigo();
    const veiculoId = this.veiculoId();
    const pedido: Observable<EstadoSeguir> = veiculoId
      ? this.social.seguirVeiculo(veiculoId, seguir)
      : this.social.seguirUsuario(this.username()!, seguir);

    this.enviando.set(true);
    this.erro.set(null);
    pedido.subscribe({
      next: (estado) => {
        this.enviando.set(false);
        this.mudou.emit(estado);
      },
      error: (e) => {
        this.enviando.set(false);
        this.erro.set(mensagemDeErro(e));
      },
    });
  }
}
