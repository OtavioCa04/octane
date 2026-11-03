import { inject } from '@angular/core';
import { Routes } from '@angular/router';
import { apenasLogado, apenasVisitante } from './core/auth/auth.guards';
import { AuthService } from './core/auth/auth.service';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    // Logado vai para o feed; visitante, para o login
    redirectTo: () => (inject(AuthService).logado() ? '/feed' : '/entrar'),
  },
  {
    path: 'feed',
    title: 'Feed · Octane',
    canActivate: [apenasLogado],
    loadComponent: () => import('./features/feed/feed').then((m) => m.Feed),
  },
  {
    path: 'entrar',
    title: 'Entrar · Octane',
    canActivate: [apenasVisitante],
    loadComponent: () => import('./features/auth/login/login').then((m) => m.Login),
  },
  {
    path: 'cadastro',
    title: 'Criar conta · Octane',
    canActivate: [apenasVisitante],
    loadComponent: () => import('./features/auth/cadastro/cadastro').then((m) => m.Cadastro),
  },
  {
    path: 'termos',
    title: 'Termos de uso · Octane',
    data: { documento: 'termos' },
    loadComponent: () => import('./features/legal/legal').then((m) => m.Legal),
  },
  {
    path: 'privacidade',
    title: 'Privacidade · Octane',
    data: { documento: 'privacidade' },
    loadComponent: () => import('./features/legal/legal').then((m) => m.Legal),
  },
  {
    path: 'esqueci-senha',
    title: 'Esqueci a senha · Octane',
    canActivate: [apenasVisitante],
    loadComponent: () => import('./features/auth/esqueci-senha/esqueci-senha').then((m) => m.EsqueciSenha),
  },
  {
    // Sem guard: quem abre o link do e-mail pode estar logado em outro aparelho
    path: 'redefinir-senha',
    title: 'Senha nova · Octane',
    loadComponent: () => import('./features/auth/redefinir-senha/redefinir-senha').then((m) => m.RedefinirSenha),
  },
  {
    path: 'buscar',
    title: 'Buscar · Octane',
    loadComponent: () => import('./features/busca/busca').then((m) => m.Busca),
  },
  // Antes de ":username/:slug", que também casaria com "conta/perfil" e "garagem/novo"
  {
    path: 'conta/perfil',
    title: 'Editar perfil · Octane',
    canActivate: [apenasLogado],
    loadComponent: () => import('./features/editar-perfil/editar-perfil').then((m) => m.EditarPerfil),
  },
  {
    path: 'garagem/novo',
    title: 'Adicionar carro · Octane',
    canActivate: [apenasLogado],
    loadComponent: () => import('./features/veiculo-form/veiculo-form').then((m) => m.VeiculoForm),
  },
  {
    path: 'garagem/:id/editar',
    title: 'Editar carro · Octane',
    canActivate: [apenasLogado],
    loadComponent: () => import('./features/veiculo-form/veiculo-form').then((m) => m.VeiculoForm),
  },
  {
    path: 'garagem/:id/fotos',
    title: 'Fotos do carro · Octane',
    canActivate: [apenasLogado],
    loadComponent: () => import('./features/fotos-veiculo/fotos-veiculo').then((m) => m.FotosVeiculo),
  },
  {
    path: 'garagem/:id/modificacoes/nova',
    title: 'Nova modificação · Octane',
    canActivate: [apenasLogado],
    loadComponent: () => import('./features/modificacao-form/modificacao-form').then((m) => m.ModificacaoForm),
  },
  {
    path: 'garagem/:id/modificacoes/:modId/editar',
    title: 'Editar modificação · Octane',
    canActivate: [apenasLogado],
    loadComponent: () => import('./features/modificacao-form/modificacao-form').then((m) => m.ModificacaoForm),
  },
  {
    path: ':username',
    title: 'Garagem · Octane',
    loadComponent: () => import('./features/perfil/perfil').then((m) => m.Perfil),
  },
  {
    path: ':username/:slug',
    title: 'Carro · Octane',
    loadComponent: () => import('./features/veiculo/veiculo-detalhe').then((m) => m.VeiculoDetalhe),
  },
];
