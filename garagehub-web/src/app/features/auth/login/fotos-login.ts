import { Carroceria } from '../../../core/models/veiculo';

export interface FotoLogin {
  legenda: string;
  // Caminho da imagem em public/login/. Sem url, a tela mostra a silhueta.
  url?: string;
  // Usados enquanto não há foto real.
  carroceria: Carroceria;
  cor: string;
  // Crédito obrigatório para fotos do Wikimedia antes de publicar.
  credito?: string;
}

// Fotos do Wikimedia baixadas pelo script garagehub-api/scripts/buscar-fotos-modelos.php.
// Autor e licença de cada uma estão em public/modelos/manifesto.json: preencher "credito" antes de publicar.
export const FOTOS_LOGIN: FotoLogin[] = [
  { legenda: 'Toyota Supra MK4', url: '/modelos/toyota-supra-mk4.jpg', carroceria: 'cupe', cor: '#E8692A' },
  { legenda: 'Nissan Skyline R34', url: '/modelos/nissan-skyline-r34.jpg', carroceria: 'cupe', cor: '#3C6FB6' },
  { legenda: 'Dodge Charger 1970', url: '/modelos/dodge-charger-1970.jpg', carroceria: 'classico', cor: '#26282B' },
  { legenda: 'Mazda RX-7', url: '/modelos/mazda-rx-7.jpg', carroceria: 'cupe', cor: '#D8D2C4' },
  { legenda: 'Volkswagen Golf GTI', url: '/modelos/volkswagen-golf-gti.jpg', carroceria: 'hatch', cor: '#E9E9E4' },
  { legenda: 'Chevrolet Opala', url: '/modelos/chevrolet-opala.jpg', carroceria: 'sedan', cor: '#B3262E' },
];

const CHAVE_ULTIMA = 'gh.ultimaFotoLogin';

// Sorteia uma foto diferente da última exibida neste navegador.
export function sortearFotoLogin(): FotoLogin {
  let ultima = -1;
  try {
    ultima = Number(localStorage.getItem(CHAVE_ULTIMA) ?? -1);
  } catch {
    // localStorage indisponível (modo privado): sorteia sem restrição
  }
  const opcoes = FOTOS_LOGIN.map((_, i) => i).filter((i) => i !== ultima);
  const escolhida = opcoes[Math.floor(Math.random() * opcoes.length)];
  try {
    localStorage.setItem(CHAVE_ULTIMA, String(escolhida));
  } catch {
    // ignora
  }
  return FOTOS_LOGIN[escolhida];
}
