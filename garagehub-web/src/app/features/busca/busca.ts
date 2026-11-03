import { Component, computed, inject, input } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { mensagemDeErro } from '../../core/api';
import { AuthService } from '../../core/auth/auth.service';
import { BuscaService } from '../../core/services/busca.service';
import { BotaoSeguir } from '../../shared/components/botao-seguir/botao-seguir';
import { FiltroLocal, Lugar } from '../../shared/components/filtro-local/filtro-local';
import { FotoVeiculo } from '../../shared/components/foto-veiculo/foto-veiculo';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { StatusChip } from '../../shared/components/status-chip/status-chip';
import { lugarDaRota, paramsDoLugar } from '../../shared/lugar';

/** Busca (/buscar?q=golf&cidade=3509502): pessoas e carros, por nome, modelo ou peça. */
@Component({
  selector: 'gh-busca',
  imports: [RouterLink, SiteHeader, FiltroLocal, FotoVeiculo, StatusChip, BotaoSeguir],
  templateUrl: './busca.html',
  styleUrl: './busca.scss',
})
export class Busca {
  private readonly buscaService = inject(BuscaService);
  private readonly router = inject(Router);
  protected readonly auth = inject(AuthService);

  // Vêm da query string
  readonly q = input<string>();
  readonly cidade = input<string>();
  readonly uf = input<string>();
  readonly local = input<string>();

  protected readonly termo = computed(() => (this.q() ?? '').trim());
  protected readonly local$ = lugarDaRota(this.cidade, this.uf, this.local);
  private readonly filtro = this.local$.filtro;

  protected readonly resultado = rxResource({
    params: () => ({ termo: this.termo(), filtro: this.filtro() }),
    stream: ({ params }) =>
      params.termo.length >= 2 ? this.buscaService.buscar(params.termo, params.filtro) : of({ pessoas: [], carros: [] }),
  });

  protected readonly dados = computed(() => (this.resultado.hasValue() ? this.resultado.value() : { pessoas: [], carros: [] }));
  protected readonly vazio = computed(() => !this.dados().pessoas.length && !this.dados().carros.length);
  protected readonly mensagemDeErro = mensagemDeErro;

  protected trocarLocal(lugar: Lugar): void {
    if (lugar.tipo === 'cidade') this.local$.ultimaCidade.set(lugar.cidade);
    this.router.navigate([], { queryParams: paramsDoLugar(lugar), queryParamsHandling: 'merge' });
  }
}
