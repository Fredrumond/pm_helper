# AGENT.md — PM Helper

## Stack

- **PHP 8.3** + **Laravel 13** + **Livewire 4**
- **Tailwind CSS** + **Vite**
- **MySQL** via Docker (nginx + PHP-FPM + MySQL)
- **OpenRouter** como adapter padrão de LLM (via `LlmGateway` + `LlmRouter`; fallback automático via `OPENROUTER_FALLBACK_MODEL`)
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
  Services/        # LlmRouter, CardParserService, Adapters/OpenRouterAdapter

config/
  versoes.php      # Changelog do projeto — SEMPRE atualizar após cada entrega

resources/
  prompts/         # Prompts versionados em Markdown (interview/v*.md, card_generation/v*.md, discovery/v*.md)
  views/           # Blade + Livewire

tests/
  Feature/         # Testes de integração
  Unit/            # Testes unitários (parsers, prompts, serviços)
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

### Testes
- **Sempre rodar dentro do container Docker**, nunca localmente.
- Comando: `docker compose exec app composer test`
- Rode os testes antes de concluir qualquer tarefa.
- Testes de serviços em `tests/Unit/Services/`, de Livewire em `tests/Feature/Livewire/`.

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
