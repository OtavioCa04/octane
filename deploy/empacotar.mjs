// Monta o pacote para subir na hospedagem: deploy/saida/public_html e deploy/saida/octane-api,
// e um octane-deploy.zip com as duas pastas.
//
// Uso (com o Node 22): node deploy/empacotar.mjs
// Passo a passo da publicação: deploy/DEPLOY.md

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const web = path.join(raiz, 'garagehub-web');
const api = path.join(raiz, 'garagehub-api');
const modelos = path.join(raiz, 'deploy', 'modelos');
const saida = path.join(raiz, 'deploy', 'saida');

const copiar = (de, para, filtro = () => true) => fs.cpSync(de, para, { recursive: true, filter: filtro });

console.log('1/4 Build de produção do Angular…');
execFileSync(process.execPath, [path.join(web, 'node_modules', '@angular', 'cli', 'bin', 'ng.js'), 'build', '--configuration', 'production'], {
  cwd: web,
  stdio: 'inherit',
});

console.log('2/4 Montando public_html…');
fs.rmSync(saida, { recursive: true, force: true });
const publico = path.join(saida, 'public_html');
copiar(path.join(web, 'dist', 'garagehub-web', 'browser'), publico);
copiar(path.join(modelos, 'public_html'), publico);
// O manifesto das fotos vai para a API (carregado no banco), não precisa ficar público
fs.rmSync(path.join(publico, 'modelos', 'manifesto.json'), { force: true });

console.log('3/4 Montando octane-api…');
const destinoApi = path.join(saida, 'octane-api');
// Fora do pacote: segredos, banco local, uploads e scripts que só servem para desenvolvimento
const ignorar = new Set(['.env', 'dev.sqlite', 'dev-sqlite.cmd', 'storage', 'uploads', 'tests', '.gitignore', '.htaccess', 'seed-dev.php', 'buscar-fotos-modelos.php', 'baixar-cidades-ibge.php', 'modelos.json']);
copiar(api, destinoApi, (origem) => !ignorar.has(path.basename(origem)) && !origem.endsWith('.sqlite'));
copiar(path.join(modelos, 'octane-api'), destinoApi);
fs.mkdirSync(path.join(destinoApi, 'storage'), { recursive: true });
fs.mkdirSync(path.join(destinoApi, 'database', 'seeds'), { recursive: true });
fs.copyFileSync(path.join(web, 'public', 'modelos', 'manifesto.json'), path.join(destinoApi, 'database', 'seeds', 'fotos-modelo.json'));
fs.copyFileSync(path.join(raiz, 'deploy', 'DEPLOY.md'), path.join(saida, 'LEIA-ME.md'));

console.log('4/4 Compactando…');
const zip = path.join(raiz, 'deploy', 'octane-deploy.zip');
fs.rmSync(zip, { force: true });
try {
  // O tar do Windows 10+ (System32, não o do Git) e o do macOS geram .zip com -a; no Linux, cai no zip
  const tar = process.platform === 'win32' ? path.join(process.env.SystemRoot ?? 'C:\\Windows', 'System32', 'tar.exe') : 'tar';
  execFileSync(tar, ['-a', '-c', '-f', zip, 'public_html', 'octane-api', 'LEIA-ME.md'], { cwd: saida, stdio: 'ignore' });
} catch {
  try {
    execFileSync('zip', ['-qr', zip, 'public_html', 'octane-api', 'LEIA-ME.md'], { cwd: saida });
  } catch {
    console.log('   (não achei tar nem zip: suba as pastas de deploy/saida direto)');
  }
}

console.log(`\nPronto:\n  ${saida}\n  ${fs.existsSync(zip) ? zip : ''}\nSiga deploy/DEPLOY.md para publicar.`);
