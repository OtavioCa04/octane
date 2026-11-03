// Formatos devolvidos pela API (garagehub-api/src/Garagem/Apresentador.php)

import type { Interacao } from './social';

export type StatusVeiculo = 'garagem' | 'projeto' | 'vendido';

export type Carroceria = 'hatch' | 'sedan' | 'suv' | 'picape' | 'cupe' | 'classico' | 'perua' | 'conversivel' | 'van';

export interface Modificacao {
  id: number;
  nome: string;
  categoria: string;
  descricao: string | null;
  data: string | null; // ISO, ex.: 2026-03-15
  quilometragem: number | null;
  marcaPeca: string | null;
  oficina: string | null;
  valor?: number | null; // só para o dono
  interacao?: Interacao; // só na página pública do carro
}

/** Corpo do POST /api/vehicles/{id}/modifications e do PUT /api/modifications/{id} */
export interface ModificacaoEntrada {
  nome: string;
  categoria: string;
  descricao: string | null;
  data: string | null;
  quilometragem: number | null;
  valor: number | null;
  marcaPeca: string | null;
  oficina: string | null;
}

// Sugestões no campo de categoria; o dono pode digitar outra
export const CATEGORIAS_MODIFICACAO = [
  'Motor',
  'Escape',
  'Admissão',
  'Suspensão',
  'Rodas e pneus',
  'Freios',
  'Transmissão',
  'Iluminação',
  'Som',
  'Elétrica',
  'Estética externa',
  'Interior',
  'Funilaria e pintura',
  'Outros',
];

export interface Veiculo {
  id: number;
  slug: string;
  marca: string;
  modelo: string;
  versao: string | null;
  anoFabricacao: number;
  anoModelo: number;
  motor: string | null;
  cambio: string | null;
  combustivel: string | null;
  cor: string | null;
  corHex: string | null;
  carroceria: Carroceria | null;
  quilometragem: number;
  status: StatusVeiculo;
  descricao: string | null;
  valorFipe: number | null;
  dataConsultaFipe: string | null;
  // Foto enviada pelo dono
  fotoUrl: string | null;
  // Foto padrão do modelo (tabela fotos_modelo), usada quando o dono não enviou foto
  fotoModeloUrl: string | null;
  modificacoes: Modificacao[];
  // Só nas páginas de detalhe; as listas trazem apenas a capa em fotoUrl
  fotos?: Foto[];
  // Só na página pública do carro
  seguidores?: number;
  euSigo?: boolean;
  interacao?: Interacao;
}

export interface Foto {
  id: number;
  url: string;
  capa: boolean;
  largura: number;
  altura: number;
}

/** O carro como o dono vê (GET /api/vehicles/{id}): inclui os dados privados. */
export interface VeiculoDono extends Veiculo {
  placa: string | null;
  precoCompra: number | null;
  fipeCodigo: string | null;
  fipeMarcaId: string | null;
  fipeModeloId: string | null;
  fipeAnoId: string | null;
  visivel: boolean;
  ordem: number;
}

/** Corpo do POST/PUT /api/vehicles */
export interface VeiculoEntrada {
  marca: string;
  modelo: string;
  versao: string | null;
  anoFabricacao: number;
  anoModelo: number;
  motor: string | null;
  cambio: string | null;
  combustivel: string | null;
  cor: string | null;
  corHex: string | null;
  carroceria: Carroceria | null;
  quilometragem: number;
  placa: string | null;
  status: StatusVeiculo;
  descricao: string | null;
  precoCompra: number | null;
  valorFipe: number | null;
  dataConsultaFipe: string | null;
  fipeCodigo: string | null;
  fipeMarcaId: string | null;
  fipeModeloId: string | null;
  fipeAnoId: string | null;
  visivel: boolean;
}

export const CARROCERIAS: { id: Carroceria; label: string }[] = [
  { id: 'hatch', label: 'Hatch' },
  { id: 'sedan', label: 'Sedã' },
  { id: 'suv', label: 'SUV' },
  { id: 'picape', label: 'Picape' },
  { id: 'cupe', label: 'Cupê' },
  { id: 'conversivel', label: 'Conversível' },
  { id: 'perua', label: 'Perua' },
  { id: 'van', label: 'Van' },
  { id: 'classico', label: 'Clássico' },
];

export interface Perfil {
  username: string;
  nome: string;
  cidade: string | null;
  bio: string | null;
  iniciais: string;
  avatarUrl: string | null;
  veiculos: Veiculo[];
  seguidores: number;
  seguindo: number;
  euSigo: boolean;
}

export const STATUS_LABEL: Record<StatusVeiculo, string> = {
  garagem: 'Na garagem',
  projeto: 'Projeto',
  vendido: 'Já tive',
};
