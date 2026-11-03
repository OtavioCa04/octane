import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DATA_DOCUMENTOS_LEGAIS, EMAIL_CONTATO } from '../../core/config';
import { Logo } from '../../shared/components/logo/logo';

/** Termos de uso e política de privacidade (versão do beta). O documento vem do data da rota. */
@Component({
  selector: 'gh-legal',
  imports: [RouterLink, Logo],
  templateUrl: './legal.html',
  styleUrl: './legal.scss',
})
export class Legal {
  readonly documento = input.required<'termos' | 'privacidade'>();

  protected readonly email = EMAIL_CONTATO;
  protected readonly data = DATA_DOCUMENTOS_LEGAIS;
}
