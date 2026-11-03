import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ItemFeed } from '../../../core/models/social';
import { FotoVeiculo } from '../../../shared/components/foto-veiculo/foto-veiculo';
import { Interacoes } from '../../../shared/components/interacoes/interacoes';
import { StatusChip } from '../../../shared/components/status-chip/status-chip';
import { formatarTempo } from '../../../shared/formatadores';

/** Um card do feed: carro novo, modificação ou fotos novas. */
@Component({
  selector: 'gh-item-feed',
  imports: [RouterLink, FotoVeiculo, StatusChip, Interacoes],
  templateUrl: './item-feed.html',
  styleUrl: './item-feed.scss',
})
export class ItemFeedComponent {
  readonly item = input.required<ItemFeed>();

  protected readonly tempo = computed(() => formatarTempo(this.item().data));
  protected readonly linkCarro = computed(() => ['/', this.item().autor.username, this.item().veiculo.slug]);
  protected readonly nomeCarro = computed(() => `${this.item().veiculo.marca} ${this.item().veiculo.modelo}`);

  protected readonly acao = computed(() => {
    const i = this.item();
    switch (i.tipo) {
      case 'carro':
        return 'colocou um carro na garagem';
      case 'modificacao':
        return 'registrou uma modificação';
      case 'fotos':
        return i.quantidade === 1 ? 'adicionou 1 foto' : `adicionou ${i.quantidade} fotos`;
    }
  });

  // "Marca da peça · Oficina", só com o que foi informado
  protected readonly detalhesMod = computed(() => {
    const i = this.item();
    if (i.tipo !== 'modificacao') return '';
    const m = i.modificacao;
    return [m.marcaPeca, m.oficina].filter(Boolean).join(' · ');
  });
}
