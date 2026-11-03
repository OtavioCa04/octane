import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { API_URL } from '../api';
import { AuthService } from './auth.service';

/**
 * Envia o token nas chamadas à API. Se a API responder 401 com um token salvo
 * (expirou ou foi revogado), limpa a sessão e volta para o login.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token();

  const paraApi = req.url.startsWith(API_URL);
  const requisicao = token && paraApi ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;

  return next(requisicao).pipe(
    catchError((erro: unknown) => {
      if (erro instanceof HttpErrorResponse && erro.status === 401 && token && paraApi) {
        auth.limpar();
        router.navigate(['/entrar']);
      }
      return throwError(() => erro);
    }),
  );
};
