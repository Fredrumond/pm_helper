# PM Helper

Assistente de discovery para Product Managers. Conduz uma entrevista guiada pelo framework do time e gera um card estruturado ao final.

**Versão atual:** 0.6.6

---

## Fluxo completo — do início ao card

> 💾 persiste no banco · 🤖 chama a LLM · 📂 lê `/docs` (MCP)

```mermaid
flowchart TD
    START([PM faz login]) --> PROJ[Seleciona projeto]
    PROJ --> NEW["💾 Conversation criada — status: in_progress"]
    NEW --> SEND[PM digita e envia mensagem]
    SEND --> UMSG["💾 Message — role: user"]
    UMSG --> ROUTE{Tipo de\nmensagem?}

    %% ── Caminho A: mensagem de entrevista ────────────────────
    ROUTE -->|mensagem de entrevista| LLM1["🤖 LLM::chat()\nprompt interview / discovery"]
    LLM1 --> SAVE1["💾 Message — role: assistant\n💾 LlmUsage — model, tokens, custo"]
    SAVE1 --> SIG{Sinal\nna resposta?}

    SIG -->|nenhum — entrevista continua| LOOP1([↩ próxima mensagem])
    SIG -->|INTERVIEW_SCOPE_TOO_BROAD| BROAD["💾 Conversation\ncurrent_step: scope_too_broad"]
    SIG -->|INTERVIEW_SUMMARY detectado| IREADY["💾 Conversation\ninterview_summary\ncurrent_step: card_generation"]
    BROAD --> LOOP1
    IREADY --> DOCS["📂 Se houver projeto: lê /docs via MCP\nresultado na sessão, não no banco"]
    DOCS --> UNLOCK([Botão 'Gerar Card' habilitado])

    %% ── Caminho B: gerar card ────────────────────────────────
    ROUTE -->|'gerar card' ou botão| CHECK{interview_summary\npreenchido?}

    CHECK -->|Não — entrevista incompleta| WARN1["💾 Message — 'entrevista não fechada'"]
    CHECK -->|scope_too_broad| WARN2["💾 Message — 'escopo amplo demais'"]
    CHECK -->|Sim| LLM2["🤖 LLM::generateCard()\nresumo + /docs se a revisão ok"]
    WARN1 --> LOOP2([↩ próxima mensagem])
    WARN2 --> LOOP2

    LLM2 --> SAVE2["💾 Message — role: assistant\n💾 LlmUsage — step: card_generation"]
    SAVE2 --> PARSE[CardParserService\nextrai bloco CARD_JSON]
    PARSE --> CARD["💾 Card — status: draft\n💾 Conversation — status: completed"]
    CARD --> DONE([✅ Preview do card])

    style LLM1 fill:#fef9c3,stroke:#ca8a04
    style LLM2 fill:#fef9c3,stroke:#ca8a04
    style DOCS fill:#ede9fe,stroke:#7c3aed
    style CARD fill:#dcfce7,stroke:#16a34a
    style IREADY fill:#dcfce7,stroke:#16a34a
    style BROAD fill:#fee2e2,stroke:#dc2626
    style NEW fill:#dbeafe,stroke:#3b82f6
```

---

## Stack

PHP 8.3 · Laravel 13 · Livewire 4 · Tailwind CSS + Vite · MySQL 8 · Docker · Laravel Breeze

---

## Arquitetura LLM

As chamadas de IA passam pelo contrato `LlmGateway`. O `LlmRouter` escolhe o adapter pelo prefixo do modelo:

```mermaid
flowchart LR
    CC[ConversationChat] -->|injeta LlmGateway| LR[LlmRouter]
    LR -->|"gpt- / o1 / o3 / o4"| OAI[OpenAiAdapter]
    LR -->|demais modelos| OR[OpenRouterAdapter]
    OAI -->|HTTP| OAIAPI[api.openai.com]
    OR -->|HTTP| ORAPI[openrouter.ai]
```

- **OpenRouter** (padrão, obrigatório): `OPENROUTER_API_KEY` · modelo em `OPENROUTER_MODEL`
- Fallback automático em rate limit ou resposta vazia: `OPENROUTER_FALLBACK_MODELS`
- **OpenAI** (opcional): `OPENAI_API_KEY` · modelo em `OPENAI_MODEL` (padrão `gpt-4o-mini`)
- Sem `OPENAI_API_KEY`, ids nativos como `gpt-4o-mini` caem no OpenRouter e tendem a falhar (slug correto seria `openai/gpt-4o-mini`)
- Custo: a OpenRouter manda `usage.cost`; a OpenAI não — nesse caso o `LlmUsage` estima pela tabela em `config/llm.php`. Sem entrada na tabela o custo fica `0` e as métricas mentem

Decisões de arquitetura: `docs/adr/` (0001 ports & adapters, 0002 prefixo nativo OpenAI, 0003 tabela de preços).

---

## Modelos principais

O catálogo completo fica em `config/chat.php`. O PM escolhe o modelo no chat. Exemplos cadastrados:

| Tier | Modelo |
|------|--------|
| Free | `nvidia/nemotron-3-ultra-550b-a55b:free`, `google/gemini-2.0-flash-exp:free` |
| Pago | `anthropic/claude-sonnet-4-5`, `openai/gpt-4.1`, `openai/o4-mini` |

---

## Prompts e passos da entrevista

| Passo | Prompt | Framework coberto |
|-------|--------|-------------------|
| `interview` / `discovery` | `resources/prompts/interview/v*.md` | Objetivo · Como funciona hoje · Regras · Onde · Aceite · O que não fazer · Stakeholders · Como validar |
| `card_generation` | `resources/prompts/card_generation/v*.md` | Monta o JSON do card a partir do `interview_summary` |

Prompts são versionados (`v1`, `v2`, …). Novas versões nunca sobrescrevem as anteriores — registrar em `SystemPromptCatalog`.

---

## Modelos de dados relevantes

| Model | Tabela | Campos-chave |
|-------|--------|-------------|
| `Conversation` | `conversations` | `status`, `current_step`, `interview_summary`, `prompt_name`, `prompt_version` |
| `Message` | `messages` | `role` (user/assistant), `content` |
| `Card` | `cards` | `title`, `objetivo`, `regras`, `aceite`, `stakeholders`, `status` (draft/approved) |
| `LlmUsage` | `llm_usages` | `model`, `step`, `prompt_tokens`, `completion_tokens`, `cost`, `finish_reason` |
| `Project` | `projects` | `name`, `repository` (GitHub `owner/repo`), `branch` (ref da pasta `/docs`) |

---

## Setup

```bash
cp .env.example .env
# Preencha OPENROUTER_API_KEY (obrigatório para os modelos free)
# OPENAI_API_KEY é opcional — necessária apenas para ids gpt-*, o1*, o3*, o4*

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Acesse [http://localhost:8080](http://localhost:8080).

PHP, Artisan e Composer **sempre** dentro do container: `docker compose exec app …`

```bash
docker compose exec app composer test   # roda PHPUnit
docker compose logs -f app              # logs em tempo real
```

---

## Rotas e páginas

| URL | O que faz |
|-----|-----------|
| `/conversations` | Lista de conversas do usuário |
| `/conversations/{id}` | Chat da entrevista + preview do card |
| `/metrics` | Consumo de tokens e custo por modelo/prompt |
| `/versoes` | Histórico de entregas (`config/versoes.php`) |
| `/admin/projects` | CRUD de projetos (admin) |
