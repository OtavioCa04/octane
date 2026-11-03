import { Component, ElementRef, OnDestroy, afterNextRender, input, model, output, viewChild } from '@angular/core';
import { Foto } from '../../../core/models/veiculo';

/**
 * Fotos em tela cheia: setas (ou teclado ← →) para navegar, Esc ou clique fora para fechar.
 * Trava a rolagem da página enquanto aberto.
 */
@Component({
  selector: 'gh-visualizador-fotos',
  template: `
    <div class="fundo" role="dialog" aria-modal="true" [attr.aria-label]="titulo()" (click)="fechar.emit()">
      <img [src]="fotos()[indice()].url" [alt]="titulo() + ', foto ' + (indice() + 1)" (click)="$event.stopPropagation()" />

      <button #botaoFechar type="button" class="fechar" (click)="fechar.emit()" aria-label="Fechar">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      @if (fotos().length > 1) {
        <button type="button" class="seta seta--anterior" (click)="mover(-1, $event)" aria-label="Foto anterior">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        </button>
        <button type="button" class="seta seta--proxima" (click)="mover(1, $event)" aria-label="Próxima foto">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
        </button>
        <span class="contador">{{ indice() + 1 }} / {{ fotos().length }}</span>
      }
    </div>
  `,
  styles: `
    .fundo {
      position: fixed;
      inset: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 56px 72px;
      background: rgba(5, 6, 7, 0.94);
    }
    img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      border-radius: 6px;
    }
    button {
      position: absolute;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      border: 1px solid rgba(255, 255, 255, 0.2);
      background: rgba(11, 13, 17, 0.7);
      color: var(--gh-text);
      cursor: pointer;
    }
    button:hover {
      border-color: var(--gh-accent);
    }
    .fechar {
      top: 16px;
      right: 16px;
    }
    .seta {
      top: 50%;
      transform: translateY(-50%);
    }
    .seta--anterior {
      left: 16px;
    }
    .seta--proxima {
      right: 16px;
    }
    .contador {
      position: absolute;
      bottom: 18px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 13px;
      color: var(--gh-text-2);
    }
    @media (max-width: 640px) {
      .fundo {
        padding: 64px 8px;
      }
      .seta {
        top: auto;
        bottom: 8px;
        transform: none;
      }
    }
  `,
  host: { '(document:keydown)': 'tecla($event)' },
})
export class VisualizadorFotos implements OnDestroy {
  readonly fotos = input.required<Foto[]>();
  readonly indice = model.required<number>();
  readonly titulo = input('Fotos do carro');
  readonly fechar = output<void>();

  private readonly botaoFechar = viewChild<ElementRef<HTMLButtonElement>>('botaoFechar');
  private readonly overflowAnterior = document.body.style.overflow;

  constructor() {
    document.body.style.overflow = 'hidden';
    afterNextRender(() => this.botaoFechar()?.nativeElement.focus());
  }

  ngOnDestroy(): void {
    document.body.style.overflow = this.overflowAnterior;
  }

  protected mover(passo: number, evento?: Event): void {
    evento?.stopPropagation();
    const total = this.fotos().length;
    this.indice.set((this.indice() + passo + total) % total);
  }

  protected tecla(evento: KeyboardEvent): void {
    if (evento.key === 'Escape') {
      this.fechar.emit();
    } else if (evento.key === 'ArrowLeft') {
      this.mover(-1);
    } else if (evento.key === 'ArrowRight') {
      this.mover(1);
    }
  }
}
