# Case Poder360 — CRUD de Tarefas em Laravel

CRUD de tarefas (`Task`) com autenticação, isolamento por usuário e clean architecture, feito para o teste de aptidão técnica da Poder360 (Parte 1).

## Stack

- Laravel 13, PHP 8.3
- SQLite (dev e testes)
- Redis (cache, rate limiting e fila)
- Laravel Horizon (dashboard de monitoramento da fila Redis)
- Mailpit (captura e visualização de e-mails em dev, sem enviar de verdade)
- Laravel Breeze (autenticação web, Blade)
- Laravel Sanctum (autenticação de API, com refresh token custom e rotação)
- Pest (testes)
- Docker Compose

## Arquitetura (Clean Architecture)

O código separa regra de negócio de HTTP/Eloquent em camadas:

- **`app/Http/Controllers`** (web) **e `app/Http/Controllers/Api`** (API): finos por design — só recebem o Form Request já validado, chamam a Action correspondente e formatam a resposta (`view()`/`redirect()` na web, `Resource`/`JsonResponse` na API). Nenhuma regra de negócio nem acesso a dados aqui; é por isso que o mesmo par Action/Contract é reaproveitado pelas duas.
- **`app/Http/Requests`**: valida a entrada e converte pra DTO via `toDto()`. Controllers e Actions nunca lidam com `Request`/array cru.
- **`app/DataTransferObjects`**: objetos imutáveis (`readonly`) que carregam dados já validados entre as camadas (`LoginData`, `CreateTaskData`, etc.).
- **`app/Actions`**: um caso de uso por classe (`CreateTask`, `LoginUser`, `RefreshAccessToken`...). Controllers só chamam a Action e formatam a resposta — não têm regra de negócio.
- **`app/Contracts`**: interfaces do padrão Repository (`TaskRepositoryInterface`, `RefreshTokenRepositoryInterface`), bindadas às implementações em `AppServiceProvider::register()`. As Actions dependem da interface, nunca do Eloquent diretamente — trocar a implementação ou mockar em teste não exige tocar em regra de negócio.
- **`app/Repositories`**: implementação concreta dos Contracts, isolando query builder/Eloquent do resto da aplicação.
- **`app/Models`, `app/Policies`, `app/Enums`**: modelagem de domínio (`Task`, `User`, `TaskStatus`) e autorização (`TaskPolicy`, reaproveitada pela web e pela API).
- **`app/Http/Resources`** (`TaskResource`, `UserResource`): só usado pela API — molda o Model pra JSON de forma explícita (`toArray()`), em vez de serializar o Eloquent direto. Evita vazar coluna nova por acidente (ex.: se alguém adicionar um campo sensível ao model, ele só aparece na resposta se for explicitamente listado aqui) e desacopla o formato da resposta da estrutura da tabela. A web não usa Resource — a Blade view já recebe o Model/DTO direto.

### Web vs. API

Duas superfícies HTTP sobre a mesma camada de domínio — trocam apenas apresentação e autenticação, reaproveitando as mesmas Actions/Contracts/Models:

| | Web (`app/Http/Controllers/*.php`) | API (`app/Http/Controllers/Api/*.php`) |
|---|---|---|
| Saída | Views Blade (Breeze) — `resources/views/tasks`, `resources/views/auth` | JSON via `app/Http/Resources` (`TaskResource`, `UserResource`) |
| Autenticação | Sessão (cookie, guard `web`) | Bearer token (Sanctum) + refresh token custom em cookie httpOnly |
| Prefixo de rota | `/` | `/api/v1` |
| Middleware | `auth`, `guest` (Breeze) | `auth:sanctum`, `throttle` |

## Rodando com Docker

Único jeito suportado de rodar o projeto — o `.env.example` já vem configurado
para os hostnames `redis`/`mailpit` da rede do Compose (`REDIS_HOST=redis`,
`MAIL_HOST=mailpit`), que não resolvem fora dele.

```bash
cp .env.example .env
docker compose up --build
```

Sobem 4 serviços:

| Serviço | URL | Papel |
|---|---|---|
| `app` | `http://localhost:8000` | Aplicação Laravel |
| `scribe` | `http://localhost:8000/docs` | Documentação da API |
| `redis` | `localhost:6379` | Cache, rate limiting e fila |
| `horizon` | `http://localhost:8000/horizon` | Worker que processa a fila Redis + dashboard de monitoramento |
| `mailpit` | `http://localhost:8025` | UI pra ver os e-mails "enviados" (SMTP capturado na porta `1025`) |

O container `app` roda migrations, seed (idempotente — pula se o usuário demo já existir) e `scribe:generate` automaticamente antes de subir o servidor (`docker-entrypoint.sh`), então não precisa de nenhum passo manual após o `up`.

Acesse `http://localhost:8000`. Usuário demo: `demo@example.com` / `password` (20 tarefas, pra ver a paginação). O seeder também cria `outro@example.com` / `password` com 3 tarefas — útil pra testar manualmente que um usuário não vê/edita as tarefas do outro.

## Testes

```bash
docker compose exec app ./vendor/bin/pest  # rodando dentro do container, se preferir não instalar PHP local
```

- **`tests/Feature`**: fim a fim via HTTP (`$this->get/post/put/delete`), cobrindo web e API em paralelo — CRUD de tarefas, autenticação (registro/login/logout/refresh/reset de senha), autorização (403 ao mexer em tarefa de outro usuário, inclusive no form de edição), rate limiting (5/min em rotas de auth, 60/min em tarefas, testado tanto na web quanto na API) e o formato de erro consistente da API (404/403/422 sempre em JSON).
- **`tests/Unit`**: peças isoladas sem passar pela camada HTTP — `Actions` de tarefa, `TaskPolicy`, `Repositories` (`TaskRepository`, `RefreshTokenRepository`), o `Task` model (casts/relationships), e o `SendPasswordResetEmail` Job (retry/backoff, e principalmente a distinção entre falha transitória de SMTP — que deve tentar de novo — e mensagem malformada — que deve falhar direto, sem retry).
- **Factories** (`database/factories/{User,Task}Factory.php`): todo teste monta seus próprios dados via `User::factory()->create()`/`Task::factory()->for($user)->create()` em vez de fixtures fixas ou seed do banco. Cada teste fica isolado e explícito sobre o que precisa (ex.: `Task::factory()->for($owner)->create()` vs. `Task::factory()->create()` pra simular a tarefa de "outro usuário"), sem depender de estado deixado por outro teste — e o `RefreshDatabase` (`tests/Pest.php`) garante um banco limpo a cada teste.

## Autenticação

- **Web**: `/register`, `/login` (Breeze, sessão via cookie). `login` usa o lockout nativo do Laravel (5 tentativas por e-mail+IP, com backoff); `register`, `forgot-password` e `reset-password` têm `throttle:5,1` de rota. O cookie "lembrar-me" (`remember_token`) tem validade reduzida de ~400 dias (padrão do Laravel) pra `REFRESH_TOKEN_TTL_DAYS` (7 dias, mesma janela do refresh token da API) — configurado em `AppServiceProvider::boot()`, já que, ao contrário do refresh token da API, esse cookie não rotaciona a cada uso.
- **API**: `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/refresh`, `POST /api/v1/auth/logout`, `POST /api/v1/auth/forgot-password`, `POST /api/v1/auth/reset-password`, `PUT /api/v1/user/password`, `DELETE /api/v1/user`. Login/registro retornam `access_token` no corpo da resposta (expira em 15 min) e definem um cookie `refresh_token` httpOnly (expira em 7 dias por padrão, configurável via `REFRESH_TOKEN_TTL_DAYS`, rotacionado a cada uso em `/api/v1/auth/refresh`). O cookie tem `path=/api/v1/auth`, então só é enviado nessas rotas de auth. O endpoint `refresh` autentica via o cookie `refresh_token` (não via header). `reset-password` (com o token do e-mail) e `PUT /user/password` (autenticado, com a senha atual) são fluxos diferentes — o primeiro é pra quem esqueceu a senha e não consegue logar, o segundo é pra quem já está logado e quer trocar. Qualquer um dos dois, ou deletar a conta, revoga **todos** os tokens de acesso e refresh tokens do usuário, incluindo o token usado na própria requisição — é preciso logar de novo depois.

## Rotas

### Web

| Método | Rota | Descrição | Rate limit |
|---|---|---|---|
| GET | `/tasks` | Lista as tarefas do usuário logado (Blade), aceita `?status=` | 60/min |
| GET | `/tasks/create` | Formulário de criação | 60/min |
| POST | `/tasks` | Cria uma tarefa | 60/min |
| GET | `/tasks/{task}` | Detalhes de uma tarefa | 60/min |
| GET | `/tasks/{task}/edit` | Formulário de edição | 60/min |
| PUT | `/tasks/{task}` | Atualiza uma tarefa | 60/min |
| DELETE | `/tasks/{task}` | Remove uma tarefa | 60/min |

Registro/login/logout/reset de senha ficam nas rotas padrão do Breeze (`/register`, `/login`, `/forgot-password`, `/reset-password/{token}`, etc.) — rate limit de cada uma detalhado na seção [Autenticação](#autenticação).

### API (`/api/v1`)

| Método | Rota | Descrição | Autenticado | Rate limit |
|---|---|---|---|---|
| POST | `/api/v1/auth/register` | Cria uma conta | Não | 5/min |
| POST | `/api/v1/auth/login` | Autentica com e-mail/senha | Não | 5/min |
| POST | `/api/v1/auth/refresh` | Rotaciona o par de tokens via cookie `refresh_token` | Não (cookie) | 5/min |
| POST | `/api/v1/auth/logout` | Revoga o access token atual | Sim | 5/min |
| POST | `/api/v1/auth/forgot-password` | Envia e-mail com link/token de reset | Não | 5/min |
| POST | `/api/v1/auth/reset-password` | Reseta a senha com o token do e-mail (revoga todos os tokens) | Não | 5/min |
| GET | `/api/v1/user` | Retorna o usuário autenticado | Sim | — |
| DELETE | `/api/v1/user` | Remove a conta autenticada (exige senha atual, cascateia tarefas e tokens) | Sim | — |
| PUT | `/api/v1/user/password` | Troca a senha (exige senha atual, revoga todos os tokens incluindo o atual) | Sim | — |
| GET | `/api/v1/tasks` | Lista as tarefas do usuário, aceita `?status=` | Sim | 60/min |
| POST | `/api/v1/tasks` | Cria uma tarefa | Sim | 60/min |
| GET | `/api/v1/tasks/{task}` | Detalhes de uma tarefa | Sim | 60/min |
| PUT | `/api/v1/tasks/{task}` | Atualiza uma tarefa | Sim | 60/min |
| DELETE | `/api/v1/tasks/{task}` | Remove uma tarefa | Sim | 60/min |

Documentação interativa completa (parâmetros, exemplos, try-it-out) em [`/docs`](#documentação-da-api).

## Documentação da API

A documentação interativa da API (gerada com [Scribe](https://scribe.knuckles.wtf/), tema [Scalar](https://github.com/scalar/scalar)) fica em `http://localhost:8000/docs`, disponível só em ambiente local (mesma lógica do `/horizon`). O tema carrega via CDN (`cdn.jsdelivr.net`), então precisa de internet no navegador de quem acessa. Depois de mudar uma rota, request ou resource da API, regenere com:

```bash
docker compose exec app php artisan scribe:generate
```

## Segurança

- Cada tarefa só pode ser vista/editada/excluída pelo seu dono (`TaskPolicy`, retorna 403 caso contrário).
- Rate limiting: 5 req/min em rotas de auth (web e API — login usa lockout por e-mail+IP do próprio Laravel, as demais usam `throttle` de rota), 60 req/min nas demais rotas autenticadas (`/tasks` na web, `/api/v1/tasks` na API).

## Funcionalidades adicionadas

Além do escopo mínimo do teste, foram adicionados:

- **IDs em ULID**: `User` e `Task` usam `ulid()` como chave primária (`HasUlids`) em vez de inteiro auto-incremento, evitando enumeração sequencial de registros por quem só tem acesso à URL (`/tasks/{id}`, `/api/v1/tasks/{id}`). As demais tabelas (`refresh_tokens`, `personal_access_tokens`) mantêm ID inteiro — nunca são expostas por ID em rota nenhuma, só são localizadas pelo hash/token secreto.
- **Refresh token em cookie httpOnly**: ver seção [Autenticação](#autenticação).
- **Fila via Redis + Laravel Horizon**: `QUEUE_CONNECTION=redis`. O envio do e-mail de reset de senha é processado de forma assíncrona por um worker do Horizon (`App\Jobs\SendPasswordResetEmail`), em vez de bloquear a resposta HTTP esperando o envio. Dashboard de monitoramento em `/horizon` (liberado automaticamente em ambiente local).
- **Retry com backoff no envio de e-mail**: `SendPasswordResetEmail` tenta até 3 vezes (`10s, 30s, 60s` de backoff) só em falha transitória de transporte (SMTP fora do ar, timeout — o equivalente a um "500"). Um erro de mensagem malformada (endereço inválido, ex. um "422" — determinístico, sempre vai falhar do mesmo jeito) falha na primeira tentativa (`$this->fail($e)`), sem gastar retry à toa. Essa checagem é feita manualmente dentro do `handle()` do Job, e não via `Handler::dontRetry()` (`bootstrap/app.php`), porque `nunomaduro/collision` substitui o `ExceptionHandler` em qualquer comando de console — incluindo `artisan horizon` — por um que não implementa `shouldStopRetries()`, o que faria o Worker ignorar essa regra silenciosamente.
- **Mailpit**: captura os e-mails enviados em dev/Docker sem entregar de verdade, com uma UI (`localhost:8025`) pra inspecionar o conteúdo — usado pra testar visualmente o fluxo de reset de senha.
- **Paginação (offset, não cursor)**: a listagem de tarefas (`tasks.index` e `GET /api/v1/tasks`) usa `paginate()` (offset/limit), não `cursorPaginate()`. Optamos por offset porque a lista é sempre pequena e escopada por usuário (não há caso real de deep pagination aqui), e offset permite navegação por número de página, que cursor não oferece. Conhecemos a alternativa e o trade-off — offset degrada em tabelas muito grandes (custo de `OFFSET` cresce com a página, resultados podem "pular" com escritas concorrentes), mas nenhum desses problemas se aplica a este cenário. O usuário demo (`demo@example.com`) é semeado com 20 tarefas (> 15/página) para a paginação ficar visível (2 páginas) ao rodar o seeder.
