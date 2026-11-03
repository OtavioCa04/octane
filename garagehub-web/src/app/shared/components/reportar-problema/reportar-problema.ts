import { Component } from '@angular/core';
import { EMAIL_CONTATO, LINK_FEEDBACK } from '../../../core/config';

/** Botão fixo do beta, no canto da tela, para a turma mandar bugs e ideias. */
@Component({
  selector: 'gh-reportar-problema',
  template: `
    <a [href]="link" target="_blank" rel="noopener">
      <span class="beta">BETA</span>
      <span>Reportar<span class="longo"> problema</span></span>
    </a>
  `,
  styles: `
    :host {
      position: fixed;
      right: 16px;
      bottom: 16px;
      z-index: 30;
    }
    a {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      height: 38px;
      padding: 0 14px 0 8px;
      border-radius: 999px;
      background: rgba(20, 23, 31, 0.86);
      border: 1px solid var(--gh-border-strong);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      font: 600 13px var(--gh-font-body);
      color: var(--gh-text);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    }
    a:hover {
      border-color: var(--gh-accent-line);
    }
    @media (max-width: 640px) {
      :host {
        right: 12px;
        bottom: 12px;
      }
      a {
        height: 32px;
        font-size: 12px;
        padding: 0 10px 0 6px;
      }
      .longo {
        display: none;
      }
    }
    .beta {
      font: 600 10px var(--gh-font-mono);
      letter-spacing: 0.12em;
      padding: 4px 7px;
      border-radius: 999px;
      background: var(--gh-accent);
      color: var(--gh-on-accent);
    }
  `,
})
export class ReportarProblema {
  protected readonly link =
    LINK_FEEDBACK ||
    `mailto:${EMAIL_CONTATO}?subject=${encodeURIComponent('Problema na Octane (beta)')}&body=${encodeURIComponent(
      'O que aconteceu:\n\nEm qual tela:\n\nCelular ou computador:\n',
    )}`;
}
