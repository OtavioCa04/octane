import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { errosDosCampos, mensagemDeErro } from '../../core/api';
import { AuthService } from '../../core/auth/auth.service';
import { CARROCERIAS, Carroceria, STATUS_LABEL, StatusVeiculo, VeiculoDono, VeiculoEntrada } from '../../core/models/veiculo';
import { CatalogoService, DetalheFipe } from '../../core/services/catalogo.service';
import { GaragemService } from '../../core/services/garagem.service';
import { FotoVeiculo } from '../../shared/components/foto-veiculo/foto-veiculo';
import { SiteHeader } from '../../shared/components/site-header/site-header';
import { formatarMesAno, formatarMoeda } from '../../shared/formatadores';

const ANO_ATUAL = new Date().getFullYear();

// Mensagens dos erros de validação locais, por campo
const MENSAGENS: Record<string, string> = {
  marca: 'Informe a marca.',
  modelo: 'Informe o modelo.',
  anoFabricacao: `Use um ano entre 1886 e ${ANO_ATUAL + 1}.`,
  anoModelo: `Use um ano entre 1886 e ${ANO_ATUAL + 2}.`,
  quilometragem: 'Informe a quilometragem atual (0 ou mais).',
  placa: 'Use o formato ABC1234 ou ABC1D23.',
  precoCompra: 'Use um valor positivo.',
  descricao: 'Use no máximo 2000 caracteres.',
};

/** Adicionar carro (/garagem/novo) e editar carro (/garagem/:id/editar). */
@Component({
  selector: 'gh-veiculo-form',
  imports: [ReactiveFormsModule, RouterLink, SiteHeader, FotoVeiculo],
  templateUrl: './veiculo-form.html',
  styleUrl: './veiculo-form.scss',
})
export class VeiculoForm {
  private readonly fb = inject(FormBuilder);
  private readonly catalogo = inject(CatalogoService);
  private readonly garagem = inject(GaragemService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  // Vem da rota /garagem/:id/editar; vazio em /garagem/novo
  readonly id = input<string>();
  protected readonly editando = computed(() => !!this.id());

  protected readonly carrocerias = CARROCERIAS;
  protected readonly statusOpcoes = Object.entries(STATUS_LABEL) as [StatusVeiculo, string][];
  protected readonly moeda = formatarMoeda;
  protected readonly mesAno = formatarMesAno;

  // "fipe": escolhe marca/modelo/ano no catálogo; "manual": digita tudo
  protected readonly modo = signal<'fipe' | 'manual'>('fipe');
  protected readonly salvando = signal(false);
  protected readonly excluindo = signal(false);
  protected readonly erro = signal<string | null>(null);
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = this.fb.group({
    marca: ['', [Validators.required, Validators.maxLength(80)]],
    modelo: ['', [Validators.required, Validators.maxLength(120)]],
    versao: ['', Validators.maxLength(120)],
    anoFabricacao: [null as number | null, [Validators.required, Validators.min(1886), Validators.max(ANO_ATUAL + 1)]],
    anoModelo: [null as number | null, [Validators.required, Validators.min(1886), Validators.max(ANO_ATUAL + 2)]],
    quilometragem: [null as number | null, [Validators.required, Validators.min(0), Validators.max(3_000_000)]],
    status: ['garagem' as StatusVeiculo],
    cor: ['', Validators.maxLength(60)],
    corHex: ['#9ea4aa'],
    carroceria: ['' as Carroceria | ''],
    motor: ['', Validators.maxLength(60)],
    cambio: ['', Validators.maxLength(60)],
    combustivel: ['', Validators.maxLength(30)],
    placa: ['', Validators.pattern(/^[A-Za-z]{3}-?\d[A-Za-z0-9]\d{2}$/)],
    precoCompra: [null as number | null, Validators.min(0)],
    descricao: ['', Validators.maxLength(2000)],
    visivel: [true],
  });

  // Dados da FIPE escolhidos (valor, código e ids), enviados junto com o carro
  protected readonly fipe = signal<Pick<VeiculoEntrada, 'valorFipe' | 'dataConsultaFipe' | 'fipeCodigo' | 'fipeMarcaId' | 'fipeModeloId' | 'fipeAnoId'>>({
    valorFipe: null,
    dataConsultaFipe: null,
    fipeCodigo: null,
    fipeMarcaId: null,
    fipeModeloId: null,
    fipeAnoId: null,
  });

  // --- Seletores encadeados da FIPE ---

  protected readonly marcaId = signal('');
  protected readonly modeloId = signal('');
  protected readonly anoId = signal('');
  protected readonly filtroModelo = signal('');

  protected readonly marcas = rxResource({
    params: () => (this.modo() === 'fipe' ? true : undefined),
    stream: () => this.catalogo.marcas(),
  });

  protected readonly modelos = rxResource({
    params: () => this.marcaId() || undefined,
    stream: ({ params }) => this.catalogo.modelos(params),
  });

  protected readonly anos = rxResource({
    params: () => (this.marcaId() && this.modeloId() ? { marca: this.marcaId(), modelo: this.modeloId() } : undefined),
    stream: ({ params }) => this.catalogo.anos(params.marca, params.modelo),
  });

  protected readonly detalhe = rxResource({
    params: () =>
      this.marcaId() && this.modeloId() && this.anoId()
        ? { marca: this.marcaId(), modelo: this.modeloId(), ano: this.anoId() }
        : undefined,
    stream: ({ params }) => this.catalogo.detalhe(params.marca, params.modelo, params.ano),
  });

  protected readonly modelosFiltrados = computed(() => {
    const lista = this.modelos.hasValue() ? this.modelos.value() : [];
    const filtro = semAcento(this.filtroModelo().trim());
    return filtro ? lista.filter((m) => semAcento(m.nome).includes(filtro)) : lista;
  });

  protected readonly contagemModelos = computed(() => {
    const n = this.modelosFiltrados().length;
    return n === 0 ? 'Nenhum modelo com esse filtro' : n === 1 ? '1 modelo' : `${n} modelos`;
  });

  protected readonly detalheFipe = computed(() => (this.detalhe.hasValue() ? this.detalhe.value() : undefined));

  // --- Edição: carrega o carro e preenche o formulário ---

  protected readonly existente = rxResource({
    params: () => (this.id() ? Number(this.id()) : undefined),
    stream: ({ params }) => this.garagem.meuVeiculo(params),
  });

  // Prévia do card com a cor e a carroceria escolhidas
  private readonly valores = signal(this.form.getRawValue());
  protected readonly previa = computed(() => this.valores());

  constructor() {
    this.form.valueChanges.subscribe(() => this.valores.set(this.form.getRawValue()));

    effect(() => {
      const d = this.detalheFipe();
      if (d) {
        this.aplicarFipe(d);
      }
    });

    effect(() => {
      const v = this.existente.hasValue() ? this.existente.value() : undefined;
      if (v) {
        this.preencher(v);
      }
    });
  }

  protected escolherMarca(id: string): void {
    this.marcaId.set(id);
    this.modeloId.set('');
    this.anoId.set('');
    this.filtroModelo.set('');
  }

  protected escolherModelo(id: string): void {
    this.modeloId.set(id);
    this.anoId.set('');
  }

  protected usarManual(): void {
    this.modo.set('manual');
  }

  protected usarFipe(): void {
    this.modo.set('fipe');
    this.marcas.reload();
  }

  private aplicarFipe(d: DetalheFipe): void {
    this.form.patchValue({
      marca: d.marca,
      modelo: d.modelo,
      versao: d.versao ?? '',
      anoModelo: d.anoModelo,
      // O ano de fabricação costuma ser o mesmo ou o anterior; começa igual e o dono ajusta
      anoFabricacao: this.form.controls.anoFabricacao.value ?? d.anoModelo,
      combustivel: d.combustivel,
      quilometragem: this.form.controls.quilometragem.value ?? (d.zeroKm ? 0 : null),
    });
    this.fipe.set({
      valorFipe: d.valor,
      dataConsultaFipe: d.mesReferencia,
      fipeCodigo: d.codigoFipe,
      fipeMarcaId: d.fipeMarcaId,
      fipeModeloId: d.fipeModeloId,
      fipeAnoId: d.fipeAnoId,
    });
  }

  private preencher(v: VeiculoDono): void {
    this.modo.set('manual');
    this.form.patchValue({
      marca: v.marca,
      modelo: v.modelo,
      versao: v.versao ?? '',
      anoFabricacao: v.anoFabricacao,
      anoModelo: v.anoModelo,
      quilometragem: v.quilometragem,
      status: v.status,
      cor: v.cor ?? '',
      corHex: v.corHex ?? '#9ea4aa',
      carroceria: v.carroceria ?? '',
      motor: v.motor ?? '',
      cambio: v.cambio ?? '',
      combustivel: v.combustivel ?? '',
      placa: v.placa ?? '',
      precoCompra: v.precoCompra,
      descricao: v.descricao ?? '',
      visivel: v.visivel,
    });
    this.fipe.set({
      valorFipe: v.valorFipe,
      dataConsultaFipe: v.dataConsultaFipe,
      fipeCodigo: v.fipeCodigo,
      fipeMarcaId: v.fipeMarcaId,
      fipeModeloId: v.fipeModeloId,
      fipeAnoId: v.fipeAnoId,
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
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.erro.set('Confira os campos destacados.');
      return;
    }
    const f = this.form.getRawValue();
    const texto = (valor: string | null) => valor?.trim() || null;

    const dados: VeiculoEntrada = {
      marca: f.marca!.trim(),
      modelo: f.modelo!.trim(),
      versao: texto(f.versao),
      anoFabricacao: f.anoFabricacao!,
      anoModelo: f.anoModelo!,
      quilometragem: f.quilometragem!,
      status: f.status ?? 'garagem',
      cor: texto(f.cor),
      corHex: f.corHex,
      carroceria: f.carroceria || null,
      motor: texto(f.motor),
      cambio: texto(f.cambio),
      combustivel: texto(f.combustivel),
      placa: texto(f.placa),
      precoCompra: f.precoCompra,
      descricao: texto(f.descricao),
      visivel: f.visivel ?? true,
      ...this.fipe(),
    };

    this.salvando.set(true);
    this.erro.set(null);
    this.errosApi.set({});

    const pedido = this.id() ? this.garagem.atualizar(Number(this.id()), dados) : this.garagem.criar(dados);
    pedido.pipe(finalize(() => this.salvando.set(false))).subscribe({
      next: (veiculo) => this.router.navigate(['/', this.auth.usuario()!.username, veiculo.slug]),
      error: (e) => {
        this.errosApi.set(errosDosCampos(e));
        this.erro.set(mensagemDeErro(e));
      },
    });
  }

  protected excluir(): void {
    const v = this.existente.hasValue() ? this.existente.value() : undefined;
    if (!v || !confirm(`Excluir ${v.marca} ${v.modelo}? As modificações dele também serão apagadas. Não dá para desfazer.`)) {
      return;
    }
    this.excluindo.set(true);
    this.garagem
      .excluir(v.id)
      .pipe(finalize(() => this.excluindo.set(false)))
      .subscribe({
        next: () => this.router.navigate(['/', this.auth.usuario()!.username]),
        error: (e) => this.erro.set(mensagemDeErro(e)),
      });
  }

  protected voltar(): string[] {
    const v = this.existente.hasValue() ? this.existente.value() : undefined;
    const usuario = this.auth.usuario()!.username;
    return v ? ['/', usuario, v.slug] : ['/', usuario];
  }

  protected readonly mensagemDeErro = mensagemDeErro;
}

function semAcento(texto: string): string {
  return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}
