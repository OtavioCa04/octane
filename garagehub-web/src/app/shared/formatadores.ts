const numero = new Intl.NumberFormat('pt-BR');
const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
const mesAno = new Intl.DateTimeFormat('pt-BR', { month: 'short', year: 'numeric', timeZone: 'UTC' });

export const formatarKm = (km: number) => `${numero.format(km)} km`;

export const formatarMoeda = (valor: number) => moeda.format(valor);

// "2026-03-20" → "mar. de 2026"
export const formatarMesAno = (iso: string) => mesAno.format(new Date(iso));

const diaMes = new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'short' });

/** "2026-11-03 10:19:49" (UTC, vindo da API) → "agora", "há 5 min", "há 3 h", "há 2 dias", "12 de out." */
export function formatarTempo(utc: string, agora = Date.now()): string {
  const data = new Date(utc.replace(' ', 'T') + 'Z');
  const minutos = Math.floor((agora - data.getTime()) / 60_000);
  if (minutos < 1) return 'agora';
  if (minutos < 60) return `há ${minutos} min`;
  const horas = Math.floor(minutos / 60);
  if (horas < 24) return `há ${horas} h`;
  const dias = Math.floor(horas / 24);
  if (dias < 7) return dias === 1 ? 'ontem' : `há ${dias} dias`;
  return diaMes.format(data);
}

export const contarModificacoes = (n: number) =>
  n === 0 ? 'Original' : n === 1 ? '1 modificação' : `${n} modificações`;
