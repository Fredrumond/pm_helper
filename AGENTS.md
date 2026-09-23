# AGENT.md — PM Helper

## Stack

- **PHP 8.3** + **Laravel 13** + **Livewire 4**
- **Tailwind CSS** + **Vite**
- **MySQL** via Docker (nginx + PHP-FPM + MySQL)
- **OpenRouter** como adapter padrão de LLM (`LlmGateway` + `LlmRouter`; fallback automático via `OPENROUTER_FALLBACK_MODELS`)
- **OpenAI** como segundo adapter (`OpenAiAdapter`), ativo só com `OPENAI_API_KEY`; o `LlmRouter` despacha pelo `provider` do catálogo (`llm_models`), não pelo prefixo do id
- Autenticação via **Laravel Breeze**
- Testes com **PHPUnit** (`composer test`)

---

## Estrutura relevante

```
app/
  Http/            # Controllers e Livewire components
  Livewire/        # Componentes Livewire (ConversationChat, CardPreview…)
  Models/          # Eloquent (Conversation, Message, Card, LlmUsage, Project, LlmModel, PromptModel)
  Prompts/         # Catálogo e métricas de prompts (SystemPromptCatalog)
  Contracts/       # Ports (LlmGateway)
  Services/        # LlmRouter, DocsRetrievalService, CardParserService, Adapters/OpenRouterAdapter, Adapters/OpenAiAdapter
  Support/         # LlmPricing (estima USD quando a API não manda usage.cost)

config/
  versoes.php      # Changelog do projeto — atualizar somente ao criar um commit
  chat.php         # Versões de prompt (a lista de modelos é legado; a vigente é llm_models)
  llm.php          # Preços legado; a vigente é price_* em llm_models
  services.php     # Chaves OpenRouter e OpenAI

docs/
  adr/             # Decisões de arquitetura (0001–0007; catálogo no banco é o 0007)
  MVP_LAUNCH_GUIDE.md
  POS_MVP_ROADMAP.md

resources/
  prompts/         # Prompts versionados (interview, card_generation, docs_retrieval, docs_briefing, discovery)
  views/           # Blade + Livewire

tests/
  Feature/         # Testes de integração (Livewire, adapters, métricas)
  Unit/            # Testes unitários (parsers, prompts, LlmPricing, LlmRouter)
```

---

## Convenções

### Prompts
- Prompts vivem em `resources/prompts/<step>/v<n>.md`.
- Ao alterar comportamento de um prompt, crie uma **nova versão** (`v2.md`, `v3.md`…), nunca edite a versão anterior.
- O `SystemPromptCatalog` em `app/Prompts/` deve registrar a nova versão.

### Migrações
- Crie sempre via `php artisan make:migration`.
- Nunca edite uma migration já executada; crie uma nova.

### Adapters LLM
- Todo adapter novo precisa gravar `LlmUsage` via `LlmUsage::recordFromResponse()`.
- Registrar o `provider` em `LlmModel::PROVIDERS` e o adapter no `AppServiceProvider` **só se a chave do provedor existir**. O `LlmRouter` despacha pelo `provider` do catálogo, não pelo prefixo do id (ADR 0007).
- Sem a chave, o modelo cai no `OpenRouterAdapter`. Ids nativos da OpenAI (`gpt-4o-mini`) não são slugs da OpenRouter (`openai/gpt-4o-mini`) — a chamada tende a falhar.
- Cadastrar o modelo no catálogo (`llm_models`, tela admin). Se a API não devolver `usage.cost`, o admin grava `price_input` / `price_cached` / `price_output` (USD / 1M tokens) no mesmo registro. Sem isso o custo fica 0 e as métricas mentem.
- A OpenRouter já manda `cost`; esse valor prevalece sobre o catálogo — inclusive `0` nos modelos free.
- Fallback de rate limit **e de resposta vazia** é responsabilidade do `OpenRouterAdapter` (`OPENROUTER_FALLBACK_MODELS`). O `OpenAiAdapter` só relança a mensagem de “tente de novo”.
- Decisões: `docs/adr/0001` a `0007`.

### Testes
- **Sempre rodar dentro do container Docker**, nunca localmente.
- Comando: `docker compose exec app composer test`
- Rode os testes antes de concluir qualquer tarefa.
- **A execução de testes não precisa de aprovação do usuário.** Rode `docker compose exec app composer test` (e filtros PHPUnit equivalentes) imediatamente, sem pedir confirmação no chat e sem esperar autorização do script. Peça as permissões de sandbox/Docker necessárias no próprio comando. Isso vale **somente para testes** — qualquer outro script continua sujeito a aprovação.
- Testes de serviços/adapters em `tests/Feature/Services/` ou `tests/Unit/Services/`; de Livewire em `tests/Feature/Livewire/`.

### Docker
- O ambiente de desenvolvimento roda via `docker-compose up`.
- **Todos** os comandos PHP/Artisan/Composer devem ser executados dentro do container:
  - `docker compose exec app php artisan …`
  - `docker compose exec app composer …`

---

## ⚠️ Versão do projeto (`config/versoes.php`)

> **Não atualize `config/versoes.php` ao concluir uma tarefa.** Nem toda mudança é uma nova versão. Atualize o changelog **somente quando for criar um commit**.

- Ao criar o commit, adicione um novo bloco no **topo** do array `releases` com:
  - `versao`, `data`, `estado` (`stable` | `development` | `test` | `bug`)
  - `titulo`, `resumo`, `commits` (hash + data + mensagem do git log)
  - `modulos` com os itens entregues e seus estados
- Nunca edite blocos de versões anteriores.
- Use `git log --oneline` para listar os commits do intervalo.
- Trabalho em andamento, correções intermediárias e entregas ainda sem commit **não** geram versão nova.
