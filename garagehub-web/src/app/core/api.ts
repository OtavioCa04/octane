import { HttpErrorResponse } from '@angular/common/http';

// Em desenvolvimento o ng serve repassa /api para a API PHP (proxy.conf.json).
// Em produção, o front e a API ficam no mesmo domínio, ou isto passa a vir de configuração.
export const API_URL = '/api';

// Formato das respostas da API
export interface RespostaApi<T> {
  data: T;
}

interface ErroApi {
  error?: { message?: string; fields?: Record<string, string> };
}

/** Mensagem para mostrar na tela a partir de um erro HTTP. */
export function mensagemDeErro(erro: unknown): string {
  if (erro instanceof HttpErrorResponse) {
    if (erro.status === 0) {
      return 'Não foi possível falar com o servidor. Confira sua conexão.';
    }
    const mensagem = (erro.error as ErroApi | null)?.error?.message;
    if (mensagem) {
      return mensagem;
    }
  }
  return 'Algo deu errado. Tente de novo em instantes.';
}

/** Erros por campo devolvidos pela validação da API (status 422). */
export function errosDosCampos(erro: unknown): Record<string, string> {
  if (erro instanceof HttpErrorResponse && erro.status === 422) {
    return (erro.error as ErroApi | null)?.error?.fields ?? {};
  }
  return {};
}
