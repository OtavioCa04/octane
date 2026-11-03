import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Veiculo } from '../../../core/models/veiculo';
import { FotoVeiculo } from '../../../shared/components/foto-veiculo/foto-veiculo';
import { StatusChip } from '../../../shared/components/status-chip/status-chip';
import { contarModificacoes, formatarKm } from '../../../shared/formatadores';

@Component({
  selector: 'gh-card-veiculo',
  imports: [RouterLink, FotoVeiculo, StatusChip],
  template: `
    <a [routerLink]="['/', username(), veiculo().slug]">
      <div class="foto">
        <gh-foto-veiculo
          [carroceria]="veiculo().carroceria"
          [cor]="veiculo().corHex"
          [fotoUrl]="veiculo().fotoUrl"
          [fotoModeloUrl]="veiculo().fotoModeloUrl"
          [alt]="veiculo().marca + ' ' + veiculo().modelo"
        />
        <gh-status-chip class="chip" [status]="veiculo().status" />
      </div>
      <div class="info">
        <h3>{{ veiculo().marca }} {{ veiculo().modelo }}</h3>
        <p class="versao">{{ veiculo().versao }}</p>
        <div class="rodape">
          <span class="gh-mono">{{ veiculo().anoFabricacao }}/{{ veiculo().anoModelo }} · {{ km(veiculo().quilometragem) }}</span>
          <span class="mods">{{ mods(veiculo().modificacoes.length) }}</span>
        </div>
      </div>
    </a>
  `,
  styles: `
    a {
      display: flex;
      flex-direction: column;
      height: 100%;
      background: var(--gh-surface);
      border: 1px solid var(--gh-border);
      border-radius: 16px;
      overflow: hidden;
      transition: border-color 0.15s, transform 0.15s;
    }
    a:hover {
      border-color: var(--gh-border-strong);
      transform: translateY(-2px);
    }
    .foto {
      position: relative;
      aspect-ratio: 16 / 10;
    }
    gh-foto-veiculo {
      position: absolute;
      inset: 0;
    }
    .chip {
      position: absolute;
      top: 12px;
      left: 12px;
    }
    .info {
      padding: 18px 18px 20px;
      display: flex;
      flex-direction: column;
      gap: 4px;
      flex: 1;
    }
    h3 {
      font-size: 20px;
      font-weight: 700;
      letter-spacing: -0.01em;
      line-height: 1.2;
    }
    .versao {
      margin: 0;
      font-size: 13px;
      color: var(--gh-text-muted);
    }
    .rodape {
      margin-top: auto;
      padding-top: 12px;
      border-top: 1px solid var(--gh-border);
      display: flex;
      justify-content: space-between;
      gap: 8px;
      font-size: 12px;
      color: var(--gh-text-muted);
    }
    .mods {
      color: var(--gh-accent-text);
      font-weight: 600;
      white-space: nowrap;
    }
  `,
})
export class CardVeiculo {
  readonly veiculo = input.required<Veiculo>();
  readonly username = input.required<string>();

  protected readonly km = formatarKm;
  protected readonly mods = contarModificacoes;
}
