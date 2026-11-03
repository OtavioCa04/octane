// Formatos devolvidos pela API (garagehub-api/src/Social)

import { Carroceria, Foto, Modificacao, StatusVeiculo } from './veiculo';

export interface Autor {
  username: string;
  nome: string;
  iniciais: string;
  avatarUrl: string | null;
}

/** O mínimo de um carro para desenhar no feed e nas sugestões. */
export interface VeiculoResumo {
  id: number;
  slug: string;
  marca: string;
  modelo: string;
  versao: string | null;
  anoModelo: number;
  status: StatusVeiculo;
  carroceria: Carroceria | null;
  corHex: string | null;
  fotoUrl: string | null;
  fotoModeloUrl: string | null;
}

/** O que se curte e comenta (um carro ou uma modificação) e as contagens. */
export interface Interacao {
  tipo: 'veiculo' | 'modificacao';
  id: number;
  curtidas: number;
  curtido: boolean;
  comentarios: number;
}

export interface Comentario {
  id: number;
  texto: string;
  data: string;
  autor: Autor;
  podeApagar: boolean;
}

interface ItemFeedBase {
  id: string;
  data: string; // UTC, "2026-11-03 10:19:49"
  autor: Autor;
  veiculo: VeiculoResumo;
  interacao: Interacao;
}

export type ItemFeed =
  | (ItemFeedBase & { tipo: 'carro' })
  | (ItemFeedBase & { tipo: 'modificacao'; modificacao: Modificacao })
  | (ItemFeedBase & { tipo: 'fotos'; quantidade: number; fotos: Foto[] });

export interface PaginaFeed {
  itens: ItemFeed[];
  temMais: boolean;
  pagina: number;
}

export type AbaFeed = 'seguindo' | 'explorar';

export interface Sugestao extends Autor {
  cidade: string | null;
  mesmaCidade: boolean;
  carros: number;
  destaque: VeiculoResumo | null;
}

/** Resposta de seguir / deixar de seguir */
export interface EstadoSeguir {
  euSigo: boolean;
  seguidores: number;
  seguindo?: number;
}
