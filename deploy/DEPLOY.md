# Publicar a Octane (beta fechado)

Feito para hospedagem PHP comum com Apache e MySQL (Hostinger, Locaweb, KingHost, HostGator…).
Tudo fica num domínio só: o site em `https://seu-dominio` e a API em `https://seu-dominio/api`.

## O que a hospedagem precisa ter

- PHP 8.1 ou mais novo, com `pdo_mysql`, `mbstring`, `openssl`, `curl` e `zlib`
- MySQL 8 ou MariaDB 10.4+
- SSL (HTTPS). Quase todas dão de graça (Let's Encrypt); ative no painel.
- Acesso a terminal (SSH) **ou** ao agendador de tarefas (cron) para rodar os scripts

## 1. Gerar o pacote (no seu computador)

```
node deploy/empacotar.mjs
```

Use o Node 22. Sai em `deploy/saida/` (e `deploy/octane-deploy.zip`), com duas pastas:

| Pasta | Vai para | O que é |
|---|---|---|
| `public_html/` | a pasta pública do domínio | site, `api/index.php`, `uploads/`, fotos dos modelos |
| `octane-api/` | **ao lado** da pasta pública, nunca dentro | código da API, migrations, scripts, `.env` |

Na Hostinger fica assim: `domains/seu-dominio/public_html` e `domains/seu-dominio/octane-api`.
Se a pasta pública tiver outro nome, tudo bem: o que importa é a `octane-api` ficar ao lado dela.

## 2. Banco

No painel, crie um banco MySQL e um usuário com acesso total a ele. Anote nome, usuário e senha.

## 3. Subir os arquivos

Pelo gerenciador de arquivos (suba o .zip e extraia) ou por FTP. Apague antes o `index.html`/`default.php`
que a hospedagem coloca na pasta pública.

## 4. Configurar

Em `octane-api/`, renomeie `.env.example` para `.env` e preencha:

- `APP_URL` e `CORS_ORIGINS`: `https://seu-dominio`
- `DB_*`: os dados do passo 2
- `UPLOADS_DIR`: caminho completo da pasta `public_html/uploads` (o gerenciador de arquivos mostra)
- `MAIL_*`: SMTP para os e-mails de senha. O mais simples é criar uma conta `nao-responda@seu-dominio`
  no painel de e-mail da hospedagem e usar o SMTP dela.

Dê permissão de escrita (755) para `public_html/uploads` e `octane-api/storage`.

## 5. Criar as tabelas e conferir

Pelo terminal (SSH), dentro de `octane-api`:

```
php scripts/migrar.php
php scripts/importar-cidades.php
php scripts/importar-fotos-modelos.php
php scripts/verificar-producao.php seu@email.com
```

O último confere a configuração inteira e manda um e-mail de teste. Só siga quando ele disser "Tudo certo".

Sem SSH: cadastre cada comando no agendador de tarefas (cron) da hospedagem para rodar uma vez,
com o caminho completo (`php /home/usuario/…/octane-api/scripts/migrar.php`), e veja a saída no e-mail do cron.

## 6. Backup diário

No agendador de tarefas (cron), todo dia às 4h:

```
0 4 * * * php /caminho/completo/octane-api/scripts/backup.php
```

Os backups do banco ficam em `octane-api/storage/backups` (14 dias). As fotos em `uploads` entram
no backup de arquivos da própria hospedagem; confira se ele está ligado.

## 7. Testar antes de mandar o link

1. Abra `https://seu-dominio`: deve aparecer o login, com cadeado no navegador.
2. Crie uma conta, adicione um carro com foto e uma modificação.
3. Saia, clique em "Esqueci a senha" e confira se o e-mail chega (olhe o spam).
4. Abra no celular.

## Atualizar depois

1. Gere o pacote de novo.
2. **Primeiro** suba a `octane-api` (sem apagar o `.env` nem a `storage`) e rode `php scripts/migrar.php`.
3. **Depois** suba a `public_html` (sem apagar a pasta `uploads`).

A ordem importa: código novo com banco velho dá erro 500.

## Antes de abrir para todo mundo

- Créditos das fotos dos modelos (Wikipedia): autor e licença precisam aparecer.
- Revisar termos e privacidade com alguém da área jurídica.
- Trocar `contato@octane.app` e o link de feedback em `garagehub-web/src/app/core/config.ts`.
