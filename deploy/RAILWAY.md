# Publicar a Octane no Railway

O Railway constrói o projeto a partir do GitHub usando o `Dockerfile` da raiz: um container só, com o site
(Angular) e a API (PHP) no mesmo endereço. A cada deploy, antes de colocar a versão nova no ar, ele roda
`scripts/preparar-deploy.php` (migrations, cidades do IBGE e fotos padrão dos modelos). Se isso falhar,
a versão anterior continua no ar.

Plano: o **Hobby** basta para o beta. Ele bloqueia e-mail por SMTP, por isso o e-mail vai pela API do **Resend**.

## 1. Resend (e-mail de "esqueci a senha")

1. Crie a conta em resend.com.
2. **Domains → Add Domain** com o seu domínio e cadastre os registros DNS que ele mostrar.
   Sem domínio próprio, o Resend só entrega para o seu próprio e-mail (serve para testar, não para a turma).
3. **API Keys → Create API Key** (permissão "Sending access"). Guarde a chave (`re_...`).

## 2. Projeto no Railway

1. **New Project → Deploy from GitHub repo** → `OtavioCa04/octane`. Ele acha o `railway.json` e usa o Dockerfile.
   O primeiro deploy vai falhar porque ainda não há banco; é normal.
2. No mesmo projeto: **+ Create → Database → MySQL**.
3. No serviço do app, aba **Variables → Raw Editor**, cole e troque o que está entre `< >`:

```
DB_DRIVER=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

APP_DEBUG=false
APP_URL=https://<seu-endereco>
CORS_ORIGINS=https://<seu-endereco>

MAIL_DRIVER=resend
RESEND_API_KEY=<re_...>
MAIL_FROM_ADDRESS=nao-responda@<seu-dominio>
MAIL_FROM_NAME=Octane
```

   `${{MySQL.…}}` liga ao banco automaticamente (se o serviço do banco tiver outro nome, troque `MySQL`).
   `APP_ENV`, `TRUST_PROXY` e o caminho das fotos já vêm do Dockerfile.

4. **Volume para as fotos:** clique com o botão direito no serviço do app → **Attach Volume**, caminho
   **`/data/uploads`**. Sem isso, as fotos enviadas somem a cada deploy.
5. **Settings → Networking → Generate Domain**. Vira algo como `octane-production.up.railway.app`.
   Coloque esse endereço (com `https://`) em `APP_URL` e `CORS_ORIGINS`.
   Domínio próprio: **Custom Domain** na mesma tela, e depois atualize as duas variáveis.
6. **Deploy** de novo (ou ele refaz sozinho ao salvar as variáveis).

## 3. Conferir

- Nos logs do deploy, a etapa de pré-deploy deve mostrar `migrar.php`, `5571 cidades importadas` e as fotos dos modelos.
- Abra o endereço: login com cadeado, criar conta, carro com foto, foto de perfil.
- Saia e teste **Esqueci a senha**. Se aparecer "Não conseguimos enviar o e-mail", o motivo está nos logs
  (procure `Falha ao enviar e-mail`): normalmente a chave ou o domínio do Resend.

## Atualizar

É só dar `git push` na `main`: o Railway constrói, roda as migrations e troca a versão sozinho.

## Backup

No serviço do MySQL e no volume das fotos, aba **Backups**, ligue os backups automáticos do Railway.
O `scripts/backup.php` continua disponível para quem publicar em hospedagem comum (`DEPLOY.md`).

## Custo

O Hobby custa US$ 5/mês com US$ 5 de uso incluso. App + MySQL com uma turma testando devem ficar nesse valor.
O volume de fotos conta no limite de 5 GB do plano; com fotos de ~400 KB, cabem milhares.
