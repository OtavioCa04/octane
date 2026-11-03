import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { EstadoSeguir } from '../../core/models/social';
import { Modificacao } from '../../core/models/veiculo';
import { BotaoSeguir } from '../../shared/components/botao-seguir/botao-seguir';
import { Interacoes } from '../../shared/components/interacoes/interacoes';
import { GaragemService } from '../../core/services/garagem.service';
import { FotoVeiculo } from '../../shared/components/foto-veiculo/foto-veiculo';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { StatusChip } from '../../shared/components/status-chip/status-chip';
import { VisualizadorFotos } from '../../shared/components/visualizador-fotos/visualizador-fotos';
import { formatarKm, formatarMesAno, formatarMoeda } from '../../shared/formatadores';

@Component({
  selector: 'gh-veiculo-detalhe',
  imports: [RouterLink, SiteHeader, FotoVeiculo, StatusChip, VisualizadorFotos, BotaoSeguir, Interacoes],
  templateUrl: './veiculo-detalhe.html',
  styleUrl: './veiculo-detalhe.scss',
})
export class VeiculoDetalhe {
  private readonly garagem = inject(GaragemService);
  private readonly auth = inject(AuthService);

  // Vêm da rota /:username/:slug
  readonly username = input.required<string>();
  readonly slug = input.required<string>();

  protected readonly dados = rxResource({
    params: () => ({ username: this.username(), slug: this.slug() }),
    stream: ({ params }) => this.garagem.veiculo(params.username, params.slug),
  });

  // value() lança erro quando o recurso falhou; hasValue() evita isso
  protected readonly veiculo = computed(() => (this.dados.hasValue() ? this.dados.value()?.veiculo : undefined));

  // A API já devolve as modificações das mais recentes para as mais antigas
  protected readonly linhaDoTempo = computed(() => this.veiculo()?.modificacoes ?? []);

  protected readonly fotos = computed(() => this.veiculo()?.fotos ?? []);
  // Estado de seguir depois de clicar no botão; volta a vazio ao trocar de carro
  protected readonly social = linkedSignal<string, EstadoSeguir | null>({ source: this.slug, computation: () => null });
  // Índice da foto aberta no visualizador; null = fechado
  protected readonly aberta = signal<number | null>(null);

  protected readonly ehDono = computed(() => this.auth.usuario()?.username === this.username());

  protected readonly linkCopiado = signal(false);

  protected readonly km = formatarKm;
  protected readonly moeda = formatarMoeda;
  protected readonly mesAno = formatarMesAno;

  // "98.000 km · Marca · Oficina", só com o que foi informado
  protected detalhesDa(m: Modificacao): string {
    return [m.quilometragem !== null ? formatarKm(m.quilometragem) : null, m.marcaPeca, m.oficina]
      .filter(Boolean)
      .join(' · ');
  }

  protected async copiarLink(): Promise<void> {
    try {
      await navigator.clipboard.writeText(location.href);
      this.linkCopiado.set(true);
      setTimeout(() => this.linkCopiado.set(false), 2000);
    } catch {
      // Sem permissão de área de transferência
    }
  }
}
