import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FotoVeiculo } from '../../../shared/components/foto-veiculo/foto-veiculo';
import { Logo } from '../../../shared/components/logo/logo';
import { sortearFotoLogin } from '../login/fotos-login';

/** Layout das telas de entrar e cadastro: foto sorteada à esquerda, formulário à direita. */
@Component({
  selector: 'gh-auth-layout',
  imports: [RouterLink, Logo, FotoVeiculo],
  templateUrl: './auth-layout.html',
  styleUrl: './auth-layout.scss',
})
export class AuthLayout {
  protected readonly foto = sortearFotoLogin();
}
