import { Component, inject, input, signal } from '@angular/core';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { mensagemDeErro } from '../../../core/api';
import { AuthService } from '../../../core/auth/auth.service';
import { AuthLayout } from '../auth-layout/auth-layout';

function senhasIguais(grupo: AbstractControl): ValidationErrors | null {
  const { senha, confirmacao } = grupo.value as { senha: string; confirmacao: string };
  return senha === confirmacao ? null : { diferentes: true };
}

@Component({
  selector: 'gh-redefinir-senha',
  imports: [ReactiveFormsModule, RouterLink, AuthLayout],
  templateUrl: './redefinir-senha.html',
  styleUrl: '../login/login.scss',
})
export class RedefinirSenha {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly auth = inject(AuthService);

  // ?token= do link do e-mail (withComponentInputBinding)
  readonly token = input<string>();

  protected readonly enviando = signal(false);
  protected readonly erro = signal<string | null>(null);
  protected readonly concluido = signal(false);

  protected readonly form = this.fb.group(
    {
      senha: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(72)]],
      confirmacao: ['', Validators.required],
    },
    { validators: senhasIguais },
  );

  protected tokenValido(): boolean {
    return /^[a-f0-9]{64}$/.test(this.token() ?? '');
  }

  protected invalido(campo: 'senha' | 'confirmacao'): boolean {
    const controle = this.form.controls[campo];
    if (campo === 'confirmacao') {
      return controle.touched && (controle.invalid || this.form.hasError('diferentes'));
    }
    return controle.invalid && controle.touched;
  }

  protected salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);

    this.auth
      .redefinirSenha(this.token()!, this.form.getRawValue().senha)
      .pipe(finalize(() => this.enviando.set(false)))
      .subscribe({
        next: () => this.concluido.set(true),
        error: (e) => this.erro.set(mensagemDeErro(e)),
      });
  }
}
