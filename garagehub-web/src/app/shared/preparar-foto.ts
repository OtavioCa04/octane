const LADO_MAXIMO = 2000;
const QUALIDADE = 0.85;

/**
 * Prepara a foto antes do envio: aplica a rotação da câmera, reduz para no máximo 2000 px
 * no maior lado e regrava como JPEG.
 *
 * Regravar pelo canvas descarta os metadados (EXIF com GPS, modelo da câmera, data),
 * então a foto pública não revela onde o carro fica. A API também remove metadados, por garantia.
 */
export async function prepararFoto(arquivo: File, opcoes: { quadrado?: number } = {}): Promise<Blob> {
  if (!arquivo.type.startsWith('image/')) {
    throw new Error('Esse arquivo não é uma imagem.');
  }

  let imagem: ImageBitmap;
  try {
    imagem = await createImageBitmap(arquivo, { imageOrientation: 'from-image' });
  } catch {
    throw new Error('Não consegui abrir essa imagem. Tente uma foto em JPG ou PNG.');
  }

  // Foto de perfil: recorta o quadrado do centro e reduz para o lado pedido
  const lado = Math.min(imagem.width, imagem.height);
  const recorte = opcoes.quadrado
    ? { x: (imagem.width - lado) / 2, y: (imagem.height - lado) / 2, l: lado, a: lado }
    : { x: 0, y: 0, l: imagem.width, a: imagem.height };
  const maximo = opcoes.quadrado ?? LADO_MAXIMO;
  const escala = Math.min(1, maximo / Math.max(recorte.l, recorte.a));
  const largura = Math.round(recorte.l * escala);
  const altura = Math.round(recorte.a * escala);

  const canvas = document.createElement('canvas');
  canvas.width = largura;
  canvas.height = altura;
  const ctx = canvas.getContext('2d');
  if (!ctx) {
    throw new Error('Seu navegador não conseguiu processar a foto.');
  }
  // Fundo escuro para PNGs com transparência (o JPEG não tem canal alfa)
  ctx.fillStyle = '#0b0d11';
  ctx.fillRect(0, 0, largura, altura);
  ctx.drawImage(imagem, recorte.x, recorte.y, recorte.l, recorte.a, 0, 0, largura, altura);
  imagem.close();

  return new Promise((resolver, rejeitar) =>
    canvas.toBlob(
      (blob) => (blob ? resolver(blob) : rejeitar(new Error('Não foi possível preparar a foto.'))),
      'image/jpeg',
      QUALIDADE,
    ),
  );
}
