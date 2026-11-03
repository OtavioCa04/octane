import { Component, input } from '@angular/core';

/** Símbolo (recorte da logo completa) + nome em Michroma. O tamanho segue o font-size do host. */
@Component({
  selector: 'gh-logo',
  template: `
    @if (marca()) {
      <span class="marca" aria-hidden="true"></span>
    }
    <span class="nome">OCTANE</span>
  `,
  styles: `
    :host {
      display: inline-flex;
      align-items: center;
      gap: 0.6em;
      font: 400 1em/1 var(--gh-font-brand);
      letter-spacing: 0.12em;
      color: var(--gh-text);
    }
    .marca {
      width: 2.1em;
      height: 2.1em;
      flex: none;
      border-radius: 0.5em;
      background: url(/octane-logo.webp) 50% 33% / 175% no-repeat;
    }
  `,
  host: { 'aria-label': 'Octane', role: 'img' },
})
export class Logo {
  readonly marca = input(true);
}
