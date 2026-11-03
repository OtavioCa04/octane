import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { errosDosCampos, mensagemDeErro } from '../../core/api';
import { AuthService } from '../../core/auth/auth.service';
import { Cidade } from '../../core/services/busca.service';
import { SeletorCidade } from '../../shared/components/seletor-cidade/seletor-cidade';
import { prepararFoto } from '../../shared/preparar-foto';
import { SiteHeader } from '../../shared/components/site-header/site-header';

/** Editar perfil (/conta/perfil): foto, nome, bio, cidade e perfil privado. */
@Component({
  selector: 'gh-editar-perfil',
  imports: [ReactiveFormsModule, RouterLink, SiteHeader, SeletorCidade],
  templateUrl: './editar-perfil.html',
  styleUrl: './editar-perfil.scss',
})
export class EditarPerfil {
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  protected readonly auth = inject(AuthService);

  private readonly usuario = this.auth.usuario()!;

  protected readonly form = this.fb.group({
    nome: [this.usuario.nome, [Validators.required, Validators.minLength(2), Validators.maxLength(120)]],
    bio: [this.usuario.bio ?? '', Validators.maxLength(500)],
    perfilPrivado: [this.usuario.perfilPrivado],
  });

  // A cidade guardada é só id, nome e UF; "Campinas, SP" vira { nome: 'Campinas', uf: 'SP' }
  protected readonly cidade = signal<Cidade | null>(
    this.usuario.cidadeId && this.usuario.cidade && this.usuario.uf
      ? { id: this.usuario.cidadeId, nome: this.usuario.cidade.replace(/, [A-Z]{2}$/, ''), uf: this.usuario.uf }
      : null,
  );

  protected readonly iniciais = this.usuario.nome
    .trim()
    .split(/\s+/)
    .filter((p, i, partes) => i === 0 || i === partes.length - 1)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('');
  protected readonly enviandoFoto = signal(false);
  protected readonly erroFoto = signal<string | null>(null);

  protected async escolherFoto(evento: Event): Promise<void> {
    const campo = evento.target as HTMLInputElement;
    const arquivo = campo.files?.[0];
    campo.value = '';
    if (!arquivo) {
      return;
    }
    this.erroFoto.set(null);
    this.enviandoFoto.set(true);
    try {
      const foto = await prepararFoto(arquivo, { quadrado: 512 });
      this.auth
        .enviarAvatar(foto)
        .pipe(finalize(() => this.enviandoFoto.set(false)))
        .subscribe({ error: (e) => this.erroFoto.set(errosDosCampos(e)['avatar'] ?? mensagemDeErro(e)) });
    } catch (e) {
      this.erroFoto.set(e instanceof Error ? e.message : 'Não foi possível usar essa foto.');
      this.enviandoFoto.set(false);
    }
  }

  protected removerFoto(): void {
    this.erroFoto.set(null);
    this.enviandoFoto.set(true);
    this.auth
      .removerAvatar()
      .pipe(finalize(() => this.enviandoFoto.set(false)))
      .subscribe({ error: (e) => this.erroFoto.set(mensagemDeErro(e)) });
  }

  protected readonly salvando = signal(false);
  protected readonly erro = signal<string | null>(null);
  protected readonly errosApi = signal<Record<string, string>>({});

  protected erroDo(campo: 'nome' | 'bio'): string | null {
    const controle = this.form.controls[campo];
    if (controle.invalid && controle.touched) {
      return campo === 'nome' ? 'Digite seu nome (2 a 120 caracteres).' : 'Use no máximo 500 caracteres.';
    }
    return this.errosApi()[campo] ?? null;
  }

  protected salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const f = this.form.getRawValue();
    this.salvando.set(true);
    this.erro.set(null);
    this.errosApi.set({});

    this.auth
      .editarPerfil({
        nome: f.nome!.trim(),
        bio: f.bio?.trim() || null,
        cidadeId: this.cidade()?.id ?? null,
        perfilPrivado: !!f.perfilPrivado,
      })
      .pipe(finalize(() => this.salvando.set(false)))
      .subscribe({
        next: (u) => this.router.navigate(['/', u.username]),
        error: (e) => {
          this.errosApi.set(errosDosCampos(e));
          this.erro.set(mensagemDeErro(e));
        },
      });
  }
}
