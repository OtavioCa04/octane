import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, input, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { Observable, firstValueFrom } from 'rxjs';
import { errosDosCampos, mensagemDeErro } from '../../core/api';
import { AuthService } from '../../core/auth/auth.service';
import { Foto } from '../../core/models/veiculo';
import { GaragemService } from '../../core/services/garagem.service';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { prepararFoto } from '../../shared/preparar-foto';

// Mesmo limite da API (FotoController::MAX_POR_CARRO)
const MAX_FOTOS = 30;

interface Envio {
  id: number;
  nome: string;
  status: 'esperando' | 'enviando' | 'erro';
  erro?: string;
}

/** Fotos do carro (/garagem/:id/fotos): enviar várias de uma vez, escolher a capa, excluir. */
@Component({
  selector: 'gh-fotos-veiculo',
  imports: [RouterLink, SiteHeader],
  templateUrl: './fotos-veiculo.html',
  styleUrl: './fotos-veiculo.scss',
})
export class FotosVeiculo {
  private readonly garagem = inject(GaragemService);
  private readonly auth = inject(AuthService);

  // Vem da rota /garagem/:id/fotos
  readonly id = input.required<string>();

  protected readonly max = MAX_FOTOS;
  protected readonly envios = signal<Envio[]>([]);
  protected readonly ocupada = signal<number | null>(null); // foto com ação em andamento
  protected readonly erro = signal<string | null>(null);
  protected readonly arrastando = signal(false);
  private proximoEnvio = 0;
  private processando = false;

  protected readonly carro = rxResource({
    params: () => Number(this.id()),
    stream: ({ params }) => this.garagem.meuVeiculo(params),
  });

  protected readonly veiculo = computed(() => (this.carro.hasValue() ? this.carro.value() : undefined));
  protected readonly fotos = computed(() => this.veiculo()?.fotos ?? []);
  protected readonly vagas = computed(() => MAX_FOTOS - this.fotos().length - this.envios().filter((e) => e.status !== 'erro').length);

  protected readonly voltar = computed(() => {
    const v = this.veiculo();
    return v ? ['/', this.auth.usuario()!.username, v.slug] : ['/', this.auth.usuario()!.username];
  });

  protected escolher(evento: Event): void {
    const campo = evento.target as HTMLInputElement;
    this.adicionar(Array.from(campo.files ?? []));
    campo.value = '';
  }

  protected soltar(evento: DragEvent): void {
    evento.preventDefault();
    this.arrastando.set(false);
    this.adicionar(Array.from(evento.dataTransfer?.files ?? []));
  }

  protected arrastar(evento: DragEvent, dentro: boolean): void {
    evento.preventDefault();
    this.arrastando.set(dentro);
  }

  private pendentes = new Map<number, File>();

  private adicionar(arquivos: File[]): void {
    this.erro.set(null);
    const imagens = arquivos.filter((a) => a.type.startsWith('image/'));
    if (imagens.length < arquivos.length) {
      this.erro.set('Alguns arquivos não eram imagens e foram ignorados.');
    }
    const cabem = imagens.slice(0, Math.max(0, this.vagas()));
    if (cabem.length < imagens.length) {
      this.erro.set(`Cada carro pode ter até ${MAX_FOTOS} fotos. Algumas ficaram de fora.`);
    }

    const novos = cabem.map((arquivo) => {
      const id = ++this.proximoEnvio;
      this.pendentes.set(id, arquivo);
      return { id, nome: arquivo.name, status: 'esperando' as const };
    });
    this.envios.update((lista) => [...lista, ...novos]);
    this.processarFila();
  }

  // Envia uma foto de cada vez, para não sobrecarregar a conexão do celular
  private async processarFila(): Promise<void> {
    if (this.processando) {
      return;
    }
    this.processando = true;
    try {
      let proximo: Envio | undefined;
      while ((proximo = this.envios().find((e) => e.status === 'esperando'))) {
        const envio = proximo;
        this.atualizarEnvio(envio.id, { status: 'enviando' });
        try {
          const blob = await prepararFoto(this.pendentes.get(envio.id)!);
          await firstValueFrom(this.garagem.enviarFoto(Number(this.id()), blob));
          this.pendentes.delete(envio.id);
          this.envios.update((lista) => lista.filter((e) => e.id !== envio.id));
          this.carro.reload();
        } catch (e) {
          // Erro da API (ex.: foto recusada) ou do preparo no navegador (ex.: imagem que não abre)
          const mensagem = e instanceof HttpErrorResponse ? (errosDosCampos(e)['foto'] ?? mensagemDeErro(e)) : e instanceof Error ? e.message : mensagemDeErro(e);
          this.atualizarEnvio(envio.id, { status: 'erro', erro: mensagem });
        }
      }
    } finally {
      this.processando = false;
    }
  }

  private atualizarEnvio(id: number, mudancas: Partial<Envio>): void {
    this.envios.update((lista) => lista.map((e) => (e.id === id ? { ...e, ...mudancas } : e)));
  }

  protected descartarErro(id: number): void {
    this.pendentes.delete(id);
    this.envios.update((lista) => lista.filter((e) => e.id !== id));
  }

  protected tentarDeNovo(id: number): void {
    this.atualizarEnvio(id, { status: 'esperando', erro: undefined });
    this.processarFila();
  }

  protected usarComoCapa(foto: Foto): void {
    this.acao(foto, this.garagem.definirCapa(foto.id));
  }

  protected excluir(foto: Foto): void {
    if (confirm(foto.capa ? 'Excluir a foto de capa? A próxima foto vira a capa.' : 'Excluir esta foto?')) {
      this.acao(foto, this.garagem.excluirFoto(foto.id));
    }
  }

  private acao(foto: Foto, pedido: Observable<unknown>): void {
    this.ocupada.set(foto.id);
    this.erro.set(null);
    pedido.subscribe({
      next: () => {
        this.ocupada.set(null);
        this.carro.reload();
      },
      error: (e) => {
        this.ocupada.set(null);
        this.erro.set(mensagemDeErro(e));
      },
    });
  }
}
