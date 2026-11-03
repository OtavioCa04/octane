import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { mensagemDeErro } from '../../core/api';
import { AbaFeed, ItemFeed, Sugestao } from '../../core/models/social';
import { SocialService } from '../../core/services/social.service';
import { BotaoSeguir } from '../../shared/components/botao-seguir/botao-seguir';
import { FiltroLocal, Lugar } from '../../shared/components/filtro-local/filtro-local';
import { FotoVeiculo } from '../../shared/components/foto-veiculo/foto-veiculo';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { lugarDaRota, paramsDoLugar } from '../../shared/lugar';
import { ItemFeedComponent } from './item-feed/item-feed';

/** Feed (/feed): o Explorar, com todos os perfis públicos, é o padrão; /feed?aba=seguindo mostra só quem a pessoa segue. */
@Component({
  selector: 'gh-feed',
  imports: [RouterLink, SiteHeader, ItemFeedComponent, BotaoSeguir, FotoVeiculo, FiltroLocal],
  templateUrl: './feed.html',
  styleUrl: './feed.scss',
})
export class Feed {
  private readonly social = inject(SocialService);
  private readonly router = inject(Router);

  // Vêm da query string (?cidade=3509502 ou ?uf=SP; ?aba=seguindo para a outra aba)
  readonly aba = input<string>();
  readonly cidade = input<string>();
  readonly uf = input<string>();
  readonly local = input<string>();
  protected readonly abaAtual = computed<AbaFeed>(() => (this.aba() === 'seguindo' ? 'seguindo' : 'explorar'));
  protected readonly local$ = lugarDaRota(this.cidade, this.uf, this.local);
  // Chave que identifica a lista em exibição: mudou, recomeça da primeira página
  private readonly lista = computed(() => `${this.abaAtual()}|${JSON.stringify(this.local$.filtro())}`);

  protected readonly itens = signal<ItemFeed[]>([]);
  protected readonly pagina = signal(1);
  protected readonly temMais = signal(false);
  protected readonly carregando = signal(false);
  protected readonly erro = signal<string | null>(null);
  protected readonly carregouUmaVez = signal(false);

  protected readonly sugestoes = rxResource({ stream: () => this.social.sugestoes() });
  // Quem a pessoa seguiu pelas sugestões nesta visita (o botão vira "Seguindo")
  protected readonly seguidosAgora = signal<Set<string>>(new Set());

  protected readonly listaSugestoes = computed<Sugestao[]>(() => (this.sugestoes.hasValue() ? this.sugestoes.value() : []));

  constructor() {
    // Trocou de aba ou de local: recomeça da primeira página
    effect(() => {
      this.lista();
      untracked(() => this.carregar(1));
    });
  }

  protected trocarAba(aba: AbaFeed): void {
    this.router.navigate([], { queryParams: { aba: aba === 'seguindo' ? 'seguindo' : null, cidade: null, uf: null, local: null } });
  }

  protected readonly nomeDoLugar = computed(() => {
    const l = this.local$.lugar();
    return l.tipo === 'cidade' ? `de ${l.cidade.nome}` : l.tipo === 'estado' ? `de ${l.uf}` : 'por aqui';
  });

  protected trocarLocal(lugar: Lugar): void {
    if (lugar.tipo === 'cidade') this.local$.ultimaCidade.set(lugar.cidade);
    this.router.navigate([], { queryParams: { aba: null, ...paramsDoLugar(lugar) } });
  }

  protected carregarMais(): void {
    this.carregar(this.pagina() + 1);
  }

  protected tentarDeNovo(): void {
    this.carregar(this.itens().length ? this.pagina() + 1 : 1);
  }

  private carregar(pagina: number): void {
    const aba = this.abaAtual();
    const lista = this.lista();
    const local = this.local$.filtro();
    this.carregando.set(true);
    this.erro.set(null);
    if (pagina === 1) {
      this.itens.set([]);
    }

    this.social.feed(aba, pagina, local).subscribe({
      next: (r) => {
        // Ignora respostas de uma aba ou de um filtro que o usuário já deixou
        if (lista !== this.lista()) return;
        this.itens.update((atuais) => (pagina === 1 ? r.itens : [...atuais, ...r.itens]));
        this.pagina.set(r.pagina);
        this.temMais.set(r.temMais);
        this.carregando.set(false);
        this.carregouUmaVez.set(true);
      },
      error: (e) => {
        if (lista !== this.lista()) return;
        this.erro.set(mensagemDeErro(e));
        this.carregando.set(false);
      },
    });
  }

  protected seguiuSugestao(username: string, euSigo: boolean): void {
    this.seguidosAgora.update((s) => {
      const novo = new Set(s);
      euSigo ? novo.add(username) : novo.delete(username);
      return novo;
    });
    // As novidades de quem acabou de ser seguido aparecem no "Seguindo"
    if (this.abaAtual() === 'seguindo') {
      this.carregar(1);
    }
  }
}
