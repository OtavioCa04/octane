import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { mensagemDeErro } from '../../../core/api';
import { AuthService } from '../../../core/auth/auth.service';
import { AuthLayout } from '../auth-layout/auth-layout';

@Component({
  selector: 'gh-esqueci-senha',
  imports: [ReactiveFormsModule, RouterLink, AuthLayout],
  templateUrl: './esqueci-senha.html',
  styleUrl: '../login/login.scss',
})
export class EsqueciSenha {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly auth = inject(AuthService);

  protected readonly enviando = signal(false);
  protected readonly erro = signal<string | null>(null);
  // E-mail para o qual o pedido foi feito; a API responde igual exista a conta ou não
  protected readonly enviadoPara = signal<string | null>(null);

  protected readonly form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
  });

  protected get invalido(): boolean {
    const controle = this.form.controls.email;
    return controle.invalid && controle.touched;
  }

  protected enviar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const { email } = this.form.getRawValue();
    this.enviando.set(true);
    this.erro.set(null);

    this.auth
      .esqueciSenha(email)
      .pipe(finalize(() => this.enviando.set(false)))
      .subscribe({
        next: () => this.enviadoPara.set(email),
        error: (e) => this.erro.set(mensagemDeErro(e)),
      });
  }
}
