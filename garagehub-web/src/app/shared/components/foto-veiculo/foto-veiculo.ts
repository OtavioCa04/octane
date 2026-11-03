import { Component, computed, input } from '@angular/core';
import { Carroceria } from '../../../core/models/veiculo';

// Silhueta lateral de cada carroceria, num viewBox de 200x80.
// Carrocerias sem desenho próprio usam a mais parecida.
const SILHUETAS: Partial<Record<Carroceria, string>> = {
  hatch: 'M10 58V46q2-6 12-8l26-4 22-16q4-3 12-3h48q8 0 12 5l18 16 22 4q8 2 8 10v8z',
  sedan: 'M8 58V46q2-5 12-7l30-4 22-16q4-3 12-3h40q8 0 14 6l14 12 32 3q8 2 8 11v10z',
  suv: 'M10 60V40q2-8 14-10l22-2 14-16q3-3 10-3h90q8 0 12 5l10 16q8 3 8 12v18z',
  picape: 'M8 60V42q2-7 14-9l28-2 14-17q3-3 10-3h38q6 0 8 5l2 18h70v26z',
  cupe: 'M8 58V48q2-5 14-7l34-5 26-14q5-3 14-3h28q8 0 14 5l18 12 28 4q8 2 8 10v8z',
  classico: 'M14 60q-2-10 8-14l18-4q12-26 56-28 44 2 60 26l20 4q12 4 10 16z',
};

@Component({
  selector: 'gh-foto-veiculo',
  template: `
    @if (fotoUrl(); as url) {
      <img [src]="url" [alt]="alt()" loading="lazy" />
    } @else if (fotoModeloUrl(); as url) {
      <img [src]="url" [alt]="alt()" loading="lazy" />
      @if (selo()) {
        <span class="selo selo--sobre-foto">Foto ilustrativa</span>
      }
    } @else {
      <svg viewBox="0 0 200 80" role="img" [attr.aria-label]="alt()">
        <ellipse cx="100" cy="66" rx="88" ry="5" fill="#0b0c0e" />
        <path [attr.d]="silhueta()" [attr.fill]="corFinal()" />
        <path [attr.d]="silhueta()" fill="none" stroke="rgba(0,0,0,.35)" stroke-width="1" />
        <circle cx="50" cy="60" r="13" fill="#0b0d11" stroke="#3a414a" stroke-width="3" />
        <circle cx="150" cy="60" r="13" fill="#0b0d11" stroke="#3a414a" stroke-width="3" />
      </svg>
      @if (selo()) {
        <span class="selo">Foto ilustrativa</span>
      }
    }
  `,
  styles: `
    :host {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: var(--gh-surface-2);
    }
    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    svg {
      width: 70%;
      max-width: 520px;
      height: auto;
    }
    .selo {
      position: absolute;
      right: 10px;
      bottom: 10px;
      font-size: 11px;
      color: var(--gh-text-faint);
    }
    .selo--sobre-foto {
      padding: 3px 6px;
      border-radius: 4px;
      background: rgba(11, 13, 17, 0.75);
      color: var(--gh-text-2);
    }
  `,
})
export class FotoVeiculo {
  readonly carroceria = input<Carroceria | null>(null);
  readonly cor = input<string | null>(null);
  readonly fotoUrl = input<string | null>(null);
  readonly fotoModeloUrl = input<string | null>(null);
  readonly alt = input('Foto do carro');
  // Miniaturas pequenas demais para o aviso "Foto ilustrativa"
  readonly selo = input(true);

  protected readonly silhueta = computed(() => {
    const tipo = this.carroceria();
    const parecida: Partial<Record<Carroceria, Carroceria>> = { perua: 'sedan', conversivel: 'cupe', van: 'suv' };
    return SILHUETAS[tipo ?? 'hatch'] ?? SILHUETAS[parecida[tipo!] ?? 'hatch']!;
  });
  protected readonly corFinal = computed(() => this.cor() ?? '#9ea4aa');
}
