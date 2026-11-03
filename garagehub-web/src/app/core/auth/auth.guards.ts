import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/** Telas de quem está logado (feed, adicionar, editar): sem login, vai para a tela de entrar. */
export const apenasLogado: CanActivateFn = () =>
  inject(AuthService).logado() || inject(Router).createUrlTree(['/entrar']);

/** Quem já está logado não precisa ver login nem cadastro: vai para o feed. */
export const apenasVisitante: CanActivateFn = () =>
  !inject(AuthService).logado() || inject(Router).createUrlTree(['/feed']);
