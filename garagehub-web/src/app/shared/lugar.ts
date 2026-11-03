import { Signal, computed, inject, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { AuthService } from '../core/auth/auth.service';
import { BuscaService, Cidade, FiltroLocal } from '../core/services/busca.service';
import { Lugar } from './components/filtro-local/filtro-local';

const CHAVE_ULTIMA_CIDADE = 'gh.ultimaCidade';

/**
 * O lugar escolhido no filtro, lido da URL (?cidade=<id do IBGE> ou ?uf=SP).
 * Também entende o formato antigo ?local=cidade|estado, que usa a cidade do perfil.
 * Precisa rodar no construtor do componente (usa inject).
 */
export function lugarDaRota(cidade: Signal<string | undefined>, uf: Signal<string | undefined>, local: Signal<string | undefined>) {
  const auth = inject(AuthService);
  const busca = inject(BuscaService);

  const cidadeId = computed<number | null>(() => {
    const c = cidade();
    if (c && /^\d+$/.test(c)) return Number(c);
    return local() === 'cidade' ? (auth.usuario()?.cidadeId ?? null) : null;
  });
  const ufFiltro = computed<string | null>(() => {
    const u = uf();
    if (u && /^[A-Za-z]{2}$/.test(u)) return u.toUpperCase();
    return local() === 'estado' ? (auth.usuario()?.uf ?? null) : null;
  });

  // O nome da cidade, para os botões (a URL só tem o id)
  const cidadeInfo = rxResource({
    params: () => cidadeId() ?? undefined,
    stream: ({ params }) => busca.cidade(params),
  });

  // Filtro para a API: não espera o nome da cidade chegar
  const filtro = computed<FiltroLocal>(() => {
    const id = cidadeId();
    if (id) return { cidade: id };
    const u = ufFiltro();
    return u ? { uf: u } : {};
  });

  const lugar = computed<Lugar>(() => {
    if (cidadeId() && cidadeInfo.hasValue()) return { tipo: 'cidade', cidade: cidadeInfo.value() };
    const u = ufFiltro();
    return u ? { tipo: 'estado', uf: u } : { tipo: 'brasil' };
  });

  // Última cidade escolhida neste navegador, para os botões voltarem nela depois
  const ultimaCidade = signal<Cidade | null>(lerUltimaCidade());

  return { filtro, lugar, ultimaCidade, temFiltro: computed(() => !!cidadeId() || !!ufFiltro()) };
}

/** Parâmetros de URL para um lugar (e guarda a cidade como a última escolhida). */
export function paramsDoLugar(l: Lugar): { cidade: number | null; uf: string | null; local: null } {
  if (l.tipo === 'cidade') {
    try {
      localStorage.setItem(CHAVE_ULTIMA_CIDADE, JSON.stringify(l.cidade));
    } catch {
      // Armazenamento bloqueado: os botões só não lembram a cidade
    }
  }
  return { cidade: l.tipo === 'cidade' ? l.cidade.id : null, uf: l.tipo === 'estado' ? l.uf : null, local: null };
}

function lerUltimaCidade(): Cidade | null {
  try {
    const c = JSON.parse(localStorage.getItem(CHAVE_ULTIMA_CIDADE) ?? 'null');
    return c && typeof c.id === 'number' && typeof c.nome === 'string' && typeof c.uf === 'string' ? c : null;
  } catch {
    return null;
  }
}
