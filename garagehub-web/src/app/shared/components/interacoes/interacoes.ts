import { Component, afterNextRender, inject, input, linkedSignal, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { mensagemDeErro } from '../../../core/api';
import { AuthService } from '../../../core/auth/auth.service';
import { Comentario, Interacao } from '../../../core/models/social';
import { SocialService } from '../../../core/services/social.service';
import { formatarTempo } from '../../formatadores';

/**
 * Curtir e comentar um carro ou uma modificação.
 * A curtida aparece na hora (e volta atrás se a API recusar); os comentários abrem embaixo, sob demanda.
 */
@Component({
  selector: 'gh-interacoes',
  imports: [FormsModule, RouterLink],
  templateUrl: './interacoes.html',
  styleUrl: './interacoes.scss',
})
export class Interacoes {
  protected readonly auth = inject(AuthService);
  private readonly social = inject(SocialService);

  readonly interacao = input.required<Interacao>();
  // Começa com os comentários abertos (ex.: na página do carro)
  readonly abertoInicial = input(false);

  protected readonly estado = linkedSignal(() => ({ ...this.interacao() }));
  protected readonly aberto = linkedSignal(() => this.abertoInicial());
  protected readonly comentarios = signal<Comentario[] | null>(null);
  protected readonly carregando = signal(false);
  protected readonly texto = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<string | null>(null);
  protected readonly tempo = formatarTempo;

  constructor() {
    afterNextRender(() => {
      if (this.aberto()) this.carregar();
    });
  }

  protected curtir(): void {
    const antes = this.estado();
    const curtir = !antes.curtido;
    // Mostra na hora; corrige com a resposta da API
    this.estado.set({ ...antes, curtido: curtir, curtidas: antes.curtidas + (curtir ? 1 : -1) });
    this.social.curtir(antes.tipo, antes.id, curtir).subscribe({
      next: (r) => this.estado.update((e) => ({ ...e, ...r })),
      error: () => this.estado.set(antes),
    });
  }

  protected alternarComentarios(): void {
    this.aberto.update((a) => !a);
    if (this.aberto() && this.comentarios() === null) {
      this.carregar();
    }
  }

  private carregar(): void {
    this.carregando.set(true);
    this.erro.set(null);
    this.social.comentarios(this.estado().tipo, this.estado().id).subscribe({
      next: (r) => {
        this.comentarios.set(r.itens);
        this.estado.update((e) => ({ ...e, comentarios: r.total }));
        this.carregando.set(false);
      },
      error: (e) => {
        this.erro.set(mensagemDeErro(e));
        this.carregando.set(false);
      },
    });
  }

  protected enviar(): void {
    const texto = this.texto().trim();
    if (!texto || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.social.comentar(this.estado().tipo, this.estado().id, texto).subscribe({
      next: (c) => {
        this.comentarios.update((lista) => [...(lista ?? []), c]);
        this.estado.update((e) => ({ ...e, comentarios: e.comentarios + 1 }));
        this.texto.set('');
        this.enviando.set(false);
      },
      error: (e) => {
        this.erro.set(mensagemDeErro(e));
        this.enviando.set(false);
      },
    });
  }

  protected apagar(c: Comentario): void {
    if (!confirm('Apagar este comentário?')) {
      return;
    }
    this.social.apagarComentario(c.id).subscribe({
      next: () => {
        this.comentarios.update((lista) => (lista ?? []).filter((x) => x.id !== c.id));
        this.estado.update((e) => ({ ...e, comentarios: Math.max(0, e.comentarios - 1) }));
      },
      error: (e) => this.erro.set(mensagemDeErro(e)),
    });
  }

  // Enter envia; Shift+Enter quebra a linha
  protected tecla(evento: KeyboardEvent): void {
    if (evento.key === 'Enter' && !evento.shiftKey) {
      evento.preventDefault();
      this.enviar();
    }
  }
}
