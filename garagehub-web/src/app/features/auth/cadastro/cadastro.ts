import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { errosDosCampos, mensagemDeErro } from '../../../core/api';
import { AuthService } from '../../../core/auth/auth.service';
import { AuthLayout } from '../auth-layout/auth-layout';

type Campo = 'nome' | 'username' | 'email' | 'senha';

@Component({
  selector: 'gh-cadastro',
  imports: [ReactiveFormsModule, RouterLink, AuthLayout],
  templateUrl: './cadastro.html',
  styleUrl: './cadastro.scss',
})
export class Cadastro {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly enviando = signal(false);
  protected readonly erro = signal<string | null>(null);
  // Erros que vieram da API (ex.: e-mail já cadastrado)
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = this.fb.group({
    nome: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(120)]],
    username: ['', [Validators.required, Validators.pattern(/^[a-zA-Z0-9_.]{3,30}$/)]],
    email: ['', [Validators.required, Validators.email]],
    senha: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(72)]],
  });

  private readonly mensagens: Record<Campo, string> = {
    nome: 'Digite seu nome.',
    username: 'Use de 3 a 30 letras, números, ponto ou _.',
    email: 'Digite um e-mail válido.',
    senha: 'Use de 8 a 72 caracteres.',
  };

  protected erroDo(campo: Campo): string | null {
    const controle = this.form.controls[campo];
    if (controle.invalid && controle.touched) {
      return this.mensagens[campo];
    }
    return this.errosApi()[campo] ?? null;
  }

  protected limparErroApi(campo: Campo): void {
    if (this.errosApi()[campo]) {
      const { [campo]: _, ...resto } = this.errosApi();
      this.errosApi.set(resto);
    }
  }

  protected cadastrar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.errosApi.set({});

    this.auth
      .cadastrar(this.form.getRawValue())
      .pipe(finalize(() => this.enviando.set(false)))
      .subscribe({
        next: (usuario) => this.router.navigate(['/', usuario.username]),
        error: (e) => {
          const campos = errosDosCampos(e);
          this.errosApi.set(campos);
          if (!Object.keys(campos).length) {
            this.erro.set(mensagemDeErro(e));
          }
        },
      });
  }
}
