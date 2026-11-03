import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { errosDosCampos, mensagemDeErro } from '../../core/api';
import { AuthService } from '../../core/auth/auth.service';
import { CATEGORIAS_MODIFICACAO, ModificacaoEntrada } from '../../core/models/veiculo';
import { GaragemService } from '../../core/services/garagem.service';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { formatarKm } from '../../shared/formatadores';

// Data de hoje no fuso local, no formato do <input type="date">
const HOJE = new Date(Date.now() - new Date().getTimezoneOffset() * 60_000).toISOString().slice(0, 10);

const MENSAGENS: Record<string, string> = {
  nome: 'Diga o que foi feito (até 120 caracteres).',
  categoria: 'Escolha ou digite uma categoria (até 60 caracteres).',
  data: 'A data não pode estar no futuro.',
  quilometragem: 'Use um número entre 0 e 3.000.000.',
  valor: 'Use um valor positivo.',
  marcaPeca: 'Use no máximo 80 caracteres.',
  oficina: 'Use no máximo 120 caracteres.',
  descricao: 'Use no máximo 2000 caracteres.',
};

/** Nova modificação (/garagem/:id/modificacoes/nova) e edição (/garagem/:id/modificacoes/:modId/editar). */
@Component({
  selector: 'gh-modificacao-form',
  imports: [ReactiveFormsModule, RouterLink, SiteHeader],
  templateUrl: './modificacao-form.html',
  styleUrl: './modificacao-form.scss',
})
export class ModificacaoForm {
  private readonly fb = inject(FormBuilder);
  private readonly garagem = inject(GaragemService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  // Vêm da rota: id do carro e, na edição, id da modificação
  readonly id = input.required<string>();
  readonly modId = input<string>();
  protected readonly editando = computed(() => !!this.modId());

  protected readonly categorias = CATEGORIAS_MODIFICACAO;
  protected readonly hoje = HOJE;
  protected readonly km = formatarKm;

  protected readonly salvando = signal(false);
  protected readonly excluindo = signal(false);
  protected readonly erro = signal<string | null>(null);
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = this.fb.group({
    nome: ['', [Validators.required, Validators.maxLength(120)]],
    categoria: ['', [Validators.required, Validators.maxLength(60)]],
    data: [HOJE as string | null],
    quilometragem: [null as number | null, [Validators.min(0), Validators.max(3_000_000)]],
    valor: [null as number | null, Validators.min(0)],
    marcaPeca: ['', Validators.maxLength(80)],
    oficina: ['', Validators.maxLength(120)],
    descricao: ['', Validators.maxLength(2000)],
  });

  // O carro do dono: traz as modificações com o valor gasto (que a página pública esconde)
  protected readonly carro = rxResource({
    params: () => Number(this.id()),
    stream: ({ params }) => this.garagem.meuVeiculo(params),
  });

  protected readonly veiculo = computed(() => (this.carro.hasValue() ? this.carro.value() : undefined));

  protected readonly modificacao = computed(() =>
    this.veiculo()?.modificacoes.find((m) => m.id === Number(this.modId())),
  );

  constructor() {
    effect(() => {
      const m = this.modificacao();
      if (m) {
        this.form.patchValue({
          nome: m.nome,
          categoria: m.categoria,
          data: m.data,
          quilometragem: m.quilometragem,
          valor: m.valor ?? null,
          marcaPeca: m.marcaPeca ?? '',
          oficina: m.oficina ?? '',
          descricao: m.descricao ?? '',
        });
      }
    });
  }

  protected erroDo(campo: string): string | null {
    const controle = this.form.get(campo);
    if (controle?.invalid && controle.touched) {
      return MENSAGENS[campo] ?? 'Confira este campo.';
    }
    return this.errosApi()[campo] ?? null;
  }

  protected salvar(): void {
    const data = this.form.controls.data.value;
    if (data && data > HOJE) {
      this.form.controls.data.setErrors({ futuro: true });
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.erro.set('Confira os campos destacados.');
      return;
    }

    const f = this.form.getRawValue();
    const texto = (valor: string | null) => valor?.trim() || null;
    const dados: ModificacaoEntrada = {
      nome: f.nome!.trim(),
      categoria: f.categoria!.trim(),
      data: f.data || null,
      quilometragem: f.quilometragem,
      valor: f.valor,
      marcaPeca: texto(f.marcaPeca),
      oficina: texto(f.oficina),
      descricao: texto(f.descricao),
    };

    this.salvando.set(true);
    this.erro.set(null);
    this.errosApi.set({});

    const pedido = this.modId()
      ? this.garagem.atualizarModificacao(Number(this.modId()), dados)
      : this.garagem.criarModificacao(Number(this.id()), dados);

    pedido.pipe(finalize(() => this.salvando.set(false))).subscribe({
      next: () => this.router.navigate(this.voltar()),
      error: (e) => {
        this.errosApi.set(errosDosCampos(e));
        this.erro.set(mensagemDeErro(e));
      },
    });
  }

  protected excluir(): void {
    const m = this.modificacao();
    if (!m || !confirm(`Excluir "${m.nome}" da linha do tempo? Não dá para desfazer.`)) {
      return;
    }
    this.excluindo.set(true);
    this.garagem
      .excluirModificacao(m.id)
      .pipe(finalize(() => this.excluindo.set(false)))
      .subscribe({
        next: () => this.router.navigate(this.voltar()),
        error: (e) => this.erro.set(mensagemDeErro(e)),
      });
  }

  /** Volta para a página do carro (ou para a garagem, se o carro não carregou). */
  protected voltar(): string[] {
    const usuario = this.auth.usuario()!.username;
    const v = this.veiculo();
    return v ? ['/', usuario, v.slug] : ['/', usuario];
  }
}
