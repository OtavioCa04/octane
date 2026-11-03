# GarageHub API

API REST do GarageHub em PHP puro (8.1+), sem framework e sem dependências.
Banco: MySQL 8 (SQLite nos testes).

## Rodar localmente

### Jeito rápido, sem MySQL (só desenvolvimento)

```
dev-sqlite.cmd
```

Cria um `dev.sqlite`, aplica as migrations, recria as garagens de demonstração (@otavio, que já
segue @lari_jdm, e @opala_do_joao) e sobe a API em `http://localhost:8000`. Os logins de
demonstração estão no topo de `scripts/seed-dev.php`.
O Angular (`ng serve`) já repassa `/api` para essa porta.

### Com MySQL

1. Crie o banco e um usuário no MySQL:

   ```sql
   CREATE DATABASE garagehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'garagehub'@'localhost' IDENTIFIED BY 'troque-esta-senha';
   GRANT ALL PRIVILEGES ON garagehub.* TO 'garagehub'@'localhost';
   ```

2. Copie `.env.example` para `.env` e preencha `DB_PASSWORD`.

3. Crie as tabelas:

   ```
   php scripts/migrar.php
   php scripts/importar-cidades.php
   ```

   Opcional, dados de exemplo (garagem de demonstração e fotos padrão dos modelos):

   ```
   php scripts/seed-dev.php
   ```

4. Suba o servidor:

   ```
   php -S localhost:8000 -t public public/index.php
   ```

## Testes

Rodam num SQLite em memória, sem tocar no MySQL:

```
php -d extension=pdo_sqlite tests/run.php
```

O `-d extension=pdo_sqlite` ativa o SQLite só para esse comando, sem mudar o `php.ini`.

Os mesmos testes rodam num MySQL/MariaDB (o banco de produção). Use um banco vazio e descartável,
porque ele é apagado a cada teste:

```
TESTE_MYSQL=1 DB_HOST=127.0.0.1 DB_DATABASE=octane_testes DB_USERNAME=... DB_PASSWORD=... php tests/run.php
```

## E-mail

A redefinição de senha manda e-mail por SMTP (`MAIL_*` no `.env`). Sem `MAIL_DRIVER=smtp`, os e-mails
são gravados em `storage/mail/` como `.txt`, para abrir o link em desenvolvimento.

## Produção

Publicação, backup diário e conferência da configuração: veja `deploy/DEPLOY.md` na raiz do projeto.
Scripts que rodam no servidor: `migrar.php`, `importar-cidades.php`, `importar-fotos-modelos.php`,
`verificar-producao.php` e `backup.php`.

## Rotas

Respostas de sucesso vêm em `{"data": ...}`; erros em `{"error": {"message": "...", "fields": {...}}}`.
Rotas marcadas com 🔒 exigem `Authorization: Bearer <token>`.

| Método | Rota | O que faz |
| --- | --- | --- |
| POST | `/api/auth/register` | Cadastro (`nome`, `username`, `email`, `senha`, `cidade`?). Devolve usuário e token |
| POST | `/api/auth/login` | Login (`email`, `senha`). Devolve usuário e token. Bloqueia após 5 erros em 15 min |
| POST | `/api/auth/logout` 🔒 | Revoga o token usado |
| GET | `/api/auth/me` 🔒 | Usuário logado |
| PUT | `/api/auth/me` 🔒 | Edita o perfil (`nome`, `bio`, `cidadeId`, `perfilPrivado`) |
| GET | `/api/cities?q=camp` | Cidades do IBGE para o seletor (ignora acentos) |
| GET | `/api/search?q=golf&cidade=\|uf=` | Pessoas e carros públicos; o termo vale também para as peças das modificações |
| GET | `/api/vehicles` 🔒 | Carros do usuário logado |
| POST | `/api/vehicles` 🔒 | Adiciona carro |
| GET | `/api/vehicles/{id}` 🔒 | Um carro do usuário logado |
| PUT | `/api/vehicles/{id}` 🔒 | Edita carro (envia todos os campos) |
| DELETE | `/api/vehicles/{id}` 🔒 | Remove carro e suas modificações |
| POST | `/api/vehicles/{id}/modifications` 🔒 | Adiciona modificação |
| PUT | `/api/modifications/{id}` 🔒 | Edita modificação |
| DELETE | `/api/modifications/{id}` 🔒 | Remove modificação |
| POST | `/api/vehicles/{id}/photos` 🔒 | Envia uma foto (multipart, campo `foto`, JPEG). A primeira vira capa |
| PUT | `/api/photos/{id}/cover` 🔒 | Usa a foto como capa do carro |
| DELETE | `/api/photos/{id}` 🔒 | Exclui a foto (se era a capa, a próxima assume) |
| GET | `/api/catalog/brands` 🔒 | Marcas da Tabela FIPE |
| GET | `/api/catalog/brands/{marca}/models` 🔒 | Modelos de uma marca |
| GET | `/api/catalog/brands/{marca}/models/{modelo}/years` 🔒 | Anos e combustíveis de um modelo |
| GET | `/api/catalog/brands/{marca}/models/{modelo}/years/{ano}` 🔒 | Valor de referência, código FIPE e dados para o cadastro |
| GET | `/api/feed?aba=seguindo\|explorar&pagina=1&local=cidade\|estado` 🔒 | Novidades (carros novos, modificações, fotos) de quem você segue, ou de todos os perfis públicos (opcionalmente só da sua cidade ou estado). 20 por página |
| POST / DELETE | `/api/likes/{tipo}/{id}` 🔒 | Curtir / descurtir (`tipo` = `veiculo` ou `modificacao`) |
| GET / POST | `/api/comments/{tipo}/{id}` | Ler (sem login) / escrever (🔒) comentários. Até 10 por minuto por pessoa |
| DELETE | `/api/comments/{id}` 🔒 | Apagar: quem escreveu, ou o dono do carro |
| GET | `/api/users/suggestions` 🔒 | Garagens públicas que você ainda não segue |
| POST / DELETE | `/api/users/{username}/follow` 🔒 | Seguir / deixar de seguir uma pessoa |
| POST / DELETE | `/api/vehicles/{id}/follow` 🔒 | Seguir / deixar de seguir um carro |
| GET | `/api/users/{username}` | Perfil público com os carros visíveis |
| GET | `/api/users/{username}/vehicles/{slug}` | Página pública de um carro |

Campos do carro: `marca`, `modelo`, `anoFabricacao`, `anoModelo`, `quilometragem`, `status`
(`garagem`, `projeto` ou `vendido`) são obrigatórios. Opcionais: `versao`, `motor`, `cambio`,
`combustivel`, `cor`, `corHex`, `carroceria`, `placa`, `descricao`, `precoCompra`, `valorFipe`,
`dataConsultaFipe`, `fipeCodigo`, `fipeMarcaId`, `fipeModeloId`, `fipeAnoId`, `visivel`, `ordem`.

### Catálogo FIPE

Vem da FIPE API do Parallelum, sempre pela API do GarageHub (o front nunca chama o provedor direto).
As respostas ficam em cache em `storage/cache`: listas por 7 dias, valores por 1 dia. Se o provedor
cair ou atingir o limite, a API devolve a última resposta guardada; sem nada guardado, responde 503
com uma mensagem para o usuário preencher os dados manualmente. Sem `FIPE_TOKEN`, o limite é de
1000 consultas por dia.

Campos da modificação: `nome` e `categoria` obrigatórios; `descricao`, `data`, `quilometragem`,
`valor`, `marcaPeca`, `oficina` opcionais.

### Fotos

Os arquivos ficam em `public/uploads/veiculos/<id do carro>/` (ou em `UPLOADS_DIR`), com nomes
aleatórios; o banco guarda só o nome e as medidas. Até 30 fotos por carro, 10 MB cada, só JPEG.

- O front reduz cada foto para no máximo 2000 px e regrava como JPEG antes de enviar.
- A API confere que é JPEG de verdade (`getimagesize`) e remove os metadados (EXIF com GPS, XMP,
  IPTC, comentários) lendo o arquivo, sem depender da extensão GD.
- Excluir uma foto ou um carro apaga os arquivos do disco.
- Em produção, ajuste no `php.ini`: `upload_max_filesize = 10M` e `post_max_size = 12M`
  (o padrão do PHP é 2 MB). O `dev-sqlite.cmd` já sobe com esses limites.

### Feed e seguidores

O feed é calculado a partir do que já está no banco (carros, modificações e fotos), sem tabela de
eventos: o que é apagado ou escondido some do feed sozinho. Fotos enviadas no mesmo dia para o
mesmo carro viram um só item. Só entram carros visíveis de perfis públicos; perfil privado não pode
ser seguido e sai do feed de quem já seguia.

### Cidades

A cidade do usuário vem da lista de municípios do IBGE (`database/seeds/cidades.json`, 5.571 cidades).
Depois das migrations, carregue a lista com `php scripts/importar-cidades.php` (pode rodar de novo à
vontade). Para atualizar o arquivo com dados novos do IBGE: `php scripts/baixar-cidades-ibge.php`.
A localização para no nível da cidade: nada de bairro, rua ou coordenadas.

## Publicar uma nova versão

Rode `php scripts/migrar.php` **antes** de colocar o código novo no ar. Código novo com banco antigo
quebra as rotas que dependem das tabelas novas.

## Segurança

- Senhas com `password_hash` (bcrypt). O banco guarda só o hash SHA-256 dos tokens.
- Toda consulta da garagem filtra pelo dono. Carro de outra pessoa responde 404.
- Páginas públicas nunca mostram placa, preço de compra nem valores das modificações.
- Consultas SQL sempre preparadas (PDO).
- CORS liberado só para os endereços de `CORS_ORIGINS`.
- Erros internos vão para o log; o cliente recebe uma mensagem genérica (a não ser com `APP_DEBUG=true`).

## Estrutura

```
public/index.php        ponto de entrada (e .htaccess para Apache)
src/Core/               roteador, requisição, resposta, banco, validação, migrações
src/Auth/               cadastro, login, tokens, limite de tentativas
src/Garagem/            carros, modificações e páginas públicas
src/App.php             rotas e tratamento de erros
database/migrations/    tabelas, em ordem
database/seeds/         dados iniciais (fotos padrão dos modelos)
scripts/                migrar.php e buscar-fotos-modelos.php
tests/run.php           testes
```
