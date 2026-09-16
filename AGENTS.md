# AGENT.md — PM Helper

## Stack

- **PHP 8.3** + **Laravel 13** + **Livewire 4**
- **Tailwind CSS** + **Vite**
- **MySQL** via Docker (nginx + PHP-FPM + MySQL)
- **OpenRouter** como adapter padrão de LLM (`LlmGateway` + `LlmRouter`; fallback automático via `OPENROUTER_FALLBACK_MODELS`)
- **OpenAI** como segundo adapter (`OpenAiAdapter`), ativo só com `OPENAI_API_KEY`; ids `gpt-*`, `o1*`, `o3*`, `o4*` vão direto para `api.openai.com`
- Autenticação via **Laravel Breeze**
- Testes com **PHPUnit** (`composer test`)

---

## Estrutura relevante

```
app/
  Http/            # Controllers e Livewire components
  Livewire/        # Componentes Livewire (ConversationChat, CardPreview…)
  Models/          # Eloquent (Conversation, Message, Card, LlmUsage…)
  Prompts/         # Catálogo e métricas de prompts (SystemPromptCatalog)
  Contracts/       # Ports (LlmGateway)
  Services/        # LlmRouter, DocsRetrievalService, CardParserService, Adapters/OpenRouterAdapter, Adapters/OpenAiAdapter
  Support/         # LlmPricing (estima USD quando a API não manda usage.cost)

config/
  versoes.php      # Changelog do projeto — SEMPRE atualizar após cada entrega
  chat.php         # Catálogo de modelos do composer + versões de prompt
  llm.php          # Tabela de preços (USD / 1M tokens) para adapters sem usage.cost
  services.php     # Chaves OpenRouter e OpenAI

docs/
  adr/             # Decisões de arquitetura (0001 ports & adapters, 0002 OpenAI, 0003 preços, 0004 MCP, 0005 retrieval /docs)
  MVP_LAUNCH_GUIDE.md

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
- Registrar o prefixo em `AppServiceProvider` **só se a chave do provedor existir**. O prefixo não pode colidir com slug da OpenRouter (hoje: `gpt-`, `o1`, `o3`, `o4`).
- Sem a chave, o modelo cai no `OpenRouterAdapter`. Ids nativos da OpenAI (`gpt-4o-mini`) não são slugs da OpenRouter (`openai/gpt-4o-mini`) — a chamada tende a falhar.
- Colocar o modelo no catálogo (`config/chat.php`) e, se a API não devolver `usage.cost`, cadastrar preço em `config/llm.php` (`input` / `cached` / `output` em USD por 1M tokens). Sem isso o custo fica 0 e as métricas mentem.
- A OpenRouter já manda `cost`; esse valor prevalece sobre a tabela — inclusive `0` nos modelos free.
- Fallback de rate limit **e de resposta vazia** é responsabilidade do `OpenRouterAdapter` (`OPENROUTER_FALLBACK_MODELS`). O `OpenAiAdapter` só relança a mensagem de “tente de novo”.
- Decisões: `docs/adr/0001`, `0002`, `0003`, `0004`, `0005`.

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

## ⚠️ Regra obrigatória após cada tarefa

> **Sempre atualizar `config/versoes.php` ao concluir uma entrega.**

- Adicione um novo bloco no **topo** do array `releases` com:
  - `versao`, `data`, `estado` (`stable` | `development` | `test` | `bug`)
  - `titulo`, `resumo`, `commits` (hash + data + mensagem do git log)
  - `modulos` com os itens entregues e seus estados
- Nunca edite blocos de versões anteriores.
- Use `git log --oneline` para listar os commits do intervalo.
