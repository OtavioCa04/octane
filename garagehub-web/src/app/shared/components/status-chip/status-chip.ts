import { Component, computed, input } from '@angular/core';
import { STATUS_LABEL, StatusVeiculo } from '../../../core/models/veiculo';

@Component({
  selector: 'gh-status-chip',
  template: `{{ label() }}`,
  styles: `
    :host {
      display: inline-block;
      font: 600 11px/1 var(--gh-font-mono);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 6px 9px;
      border-radius: 6px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      background: rgba(11, 13, 17, 0.72);
      color: var(--gh-text-2);
    }
    :host(.projeto) {
      background: var(--gh-accent);
      border-color: var(--gh-accent);
      color: var(--gh-on-accent);
    }
    :host(.vendido) {
      color: var(--gh-text-muted);
    }
  `,
  host: { '[class]': 'status()' },
})
export class StatusChip {
  readonly status = input.required<StatusVeiculo>();
  protected readonly label = computed(() => STATUS_LABEL[this.status()]);
}
