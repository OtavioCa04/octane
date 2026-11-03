import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AuthService } from './core/auth/auth.service';
import { ReportarProblema } from './shared/components/reportar-problema/reportar-problema';

@Component({
  imports: [RouterOutlet, ReportarProblema],
  selector: 'app-root',
  templateUrl: './app.html',
})
export class App {
  protected readonly auth = inject(AuthService);
}
