import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { EstadoSeguir } from '../../core/models/social';
import { BotaoSeguir } from '../../shared/components/botao-seguir/botao-seguir';
import { GaragemService } from '../../core/services/garagem.service';
import { StatusVeiculo } from '../../core/models/veiculo';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { CardVeiculo } from './card-veiculo/card-veiculo';

type Filtro = 'todos' | StatusVeiculo;

const FILTROS: { id: Filtro; label: string }[] = [
  { id: 'todos', label: 'Todos' },
  { id: 'garagem', label: 'Na garagem' },
  { id: 'projeto', label: 'Projetos' },
  { id: 'vendido', label: 'Já tive' },
];

@Component({
  selector: 'gh-perfil',
  imports: [RouterLink, SiteHeader, CardVeiculo, BotaoSeguir],
  templateUrl: './perfil.html',
  styleUrl: './perfil.scss',
})
export class Perfil {
  private readonly garagem = inject(GaragemService);
  private readonly auth = inject(AuthService);

  // Vem da rota /:username (withComponentInputBinding)
  readonly username = input.required<string>();

  protected readonly perfil = rxResource({
    params: () => this.username(),
    stream: ({ params }) => this.garagem.perfil(params),
  });

  // Estado de seguir depois de clicar no botão; volta a vazio ao trocar de perfil
  protected readonly social = linkedSignal<string, EstadoSeguir | null>({ source: this.username, computation: () => null });

  protected readonly filtro = signal<Filtro>('todos');
  protected readonly linkCopiado = signal(false);

  // value() lança erro quando o recurso falhou; hasValue() evita isso
  protected readonly veiculos = computed(() => (this.perfil.hasValue() ? this.perfil.value()?.veiculos ?? [] : []));
  protected readonly ehDono = computed(() => this.auth.usuario()?.username === this.username());

  protected readonly abas = computed(() =>
    FILTROS.map((f) => ({
      ...f,
      total: f.id === 'todos' ? this.veiculos().length : this.veiculos().filter((v) => v.status === f.id).length,
    })),
  );

  protected readonly veiculosFiltrados = computed(() => {
    const f = this.filtro();
    return f === 'todos' ? this.veiculos() : this.veiculos().filter((v) => v.status === f);
  });

  // Capa do perfil: a primeira foto enviada pelo dono; sem nenhuma, a foto padrão do primeiro modelo que tiver
  protected readonly capa = computed(
    () => this.veiculos().find((v) => v.fotoUrl)?.fotoUrl ?? this.veiculos().find((v) => v.fotoModeloUrl)?.fotoModeloUrl ?? null,
  );
  protected readonly dominio = location.host;

  protected readonly totalModificacoes = computed(() =>
    this.veiculos().reduce((soma, v) => soma + v.modificacoes.length, 0),
  );

  protected async compartilhar(): Promise<void> {
    const url = `${location.origin}/${this.username()}`;
    try {
      await navigator.clipboard.writeText(url);
      this.linkCopiado.set(true);
      setTimeout(() => this.linkCopiado.set(false), 2000);
    } catch {
      // Sem permissão de área de transferência: não faz nada por enquanto
    }
  }
}
