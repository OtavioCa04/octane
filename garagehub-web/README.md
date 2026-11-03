# GarageHub Web

Front-end do GarageHub em Angular 22. Consome a API PHP de `../garagehub-api`.

## Rodar localmente

1. Suba a API (veja `../garagehub-api/README.md`). O jeito mais rápido é `garagehub-api/dev-sqlite.cmd`,
   que sobe em `http://localhost:8000` com uma garagem de demonstração.
2. Suba o front:

   ```
   serve.cmd
   ```

   Abre em `http://localhost:4200`. O `serve.cmd` usa o Node 22 instalado pelo nvm sem trocar o Node
   padrão da máquina. Com o Node 20+ como padrão, `npx ng serve` também funciona.

O `ng serve` repassa tudo que começa com `/api` e `/uploads` (as fotos) para `http://localhost:8000` (`proxy.conf.json`),
então não há configuração de CORS em desenvolvimento.

## Telas

| Rota | Tela |
| --- | --- |
| `/entrar` | Login |
| `/cadastro` | Criar conta |
| `/feed` | Novidades de quem você segue (aba Seguindo) e de todos (aba Explorar), com sugestões de garagens. É a tela inicial depois do login |
| `/buscar?q=` | Busca de pessoas e carros (também por peça), com filtro Brasil / estado / cidade |
| `/conta/perfil` | Editar perfil: nome, bio, cidade (lista do IBGE) e perfil privado |
| `/garagem/novo` | Adicionar carro, com seletor da Tabela FIPE (precisa de login) |
| `/garagem/:id/editar` | Editar ou excluir um carro seu |
| `/garagem/:id/fotos` | Enviar fotos (várias de uma vez, arrastando ou escolhendo), escolher a capa e excluir |
| `/garagem/:id/modificacoes/nova` | Registrar uma modificação na linha do tempo do carro |
| `/garagem/:id/modificacoes/:modId/editar` | Editar ou excluir uma modificação |
| `/:username` | Garagem pública da pessoa |
| `/:username/:slug` | Página pública de um carro |

## Organização

```
src/app/core/          API, sessão (AuthService + interceptor do token), modelos e serviços
src/app/shared/        componentes reutilizáveis (logo, foto do carro, selo de status, cabeçalho)
src/app/features/      telas: auth (login, cadastro), perfil, veiculo
public/modelos/        fotos padrão dos modelos (geradas por garagehub-api/scripts/buscar-fotos-modelos.php)
```

A sessão fica no `localStorage` com "Manter conectado" marcado, e no `sessionStorage` sem ele.
Se a API responder 401 (token expirado ou revogado), o app limpa a sessão e volta para o login.

## Build

```
npx ng build
```
