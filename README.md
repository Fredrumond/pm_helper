# PM Helper

Assistente de discovery para Product Managers. Conduz uma entrevista guiada pelo framework do time e gera um card estruturado ao final.

A ideia do produto é absorver no aplicativo as etapas de **qualificação** (`problem-qualify`) e **discovery** da [metodologia em evolução](https://github.com/Fredrumond/skills-metodologia) — o restante do ciclo (plano, ADR, code review, handoff) permanece nas skills do Cursor.

**Versão atual:** 0.7.1

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
    IREADY --> DOCS["📂 Se houver projeto: retrieval + briefing de /docs\n(ver fluxo abaixo)"]
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

## Fluxo de `/docs` — retrieval e briefing

Disparado só no encerramento da entrevista, quando há projeto selecionado. Orquestrado pelo `DocsRetrievalService`. Sem projeto, este bloco não roda.

> 💾 persiste no banco · 🤖 chama a LLM · 📂 lê `/docs` (MCP)

```mermaid
flowchart TD
    START([Entrevista pronta + projeto]) --> LIST["📂 listDocsPaths — só paths, sem conteúdo"]
    LIST --> RET["🤖 LLM::completePrompt()\nprompt docs_retrieval\n💾 LlmUsage — step: docs_retrieval"]
    RET --> PARSE{JSON paths\nválidos?}

    PARSE -->|não / vazio / erro| DUMP["📂 readProjectDocs — árvore inteira"]
    PARSE -->|sim, máx. 10| READ["📂 readDocsByPaths — só o subset"]

    DUMP --> STATUS{status ok?}
    READ --> STATUS

    STATUS -->|não| MSG["💾 Message — frase de revisão"]
    STATUS -->|sim| BRIEF["🤖 LLM::completePrompt()\nprompt docs_briefing\n💾 LlmUsage — step: docs_briefing"]
    BRIEF --> SAVE["💾 Message — frase de revisão\n💾 Message — briefing se houver"]
    MSG --> SESS["sessão project_docs — não vai ao banco"]
    SAVE --> SESS
    SESS --> UNLOCK([Botão 'Gerar Card' habilitado])
    UNLOCK --> CARD["🤖 LLM::generateCard()\nresumo + /docs da sessão se ok"]

    style RET fill:#fef9c3,stroke:#ca8a04
    style BRIEF fill:#fef9c3,stroke:#ca8a04
    style CARD fill:#fef9c3,stroke:#ca8a04
    style LIST fill:#ede9fe,stroke:#7c3aed
    style READ fill:#ede9fe,stroke:#7c3aed
    style DUMP fill:#ede9fe,stroke:#7c3aed
    style SESS fill:#ede9fe,stroke:#7c3aed
    style UNLOCK fill:#dcfce7,stroke:#16a34a
    style SAVE fill:#dbeafe,stroke:#3b82f6
```

- **Retrieval** (`docs_retrieval@v1`): recebe o índice de paths + o `interview_summary`; devolve JSON `{"paths": [...]}`. Paths inventados ou fora de `/docs` são descartados. Falha, lista vazia ou parse inválido volta ao dump completo.
- **Briefing** (`docs_briefing@v1`): só com status `ok`. Cruza o conteúdo lido com o resumo e gera texto livre em português (sobreposição, conflito, vocabulário). Falha ou vazio: só a frase de revisão, sem erro na UI.
- O teto `GITHUB_MCP_MAX_CHARS` vale sobre o conteúdo **já filtrado**, não sobre a árvore inteira.
- `generateCard` não relê o GitHub: usa o payload da sessão.

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

Decisões de arquitetura: `docs/adr/` (0001 ports & adapters, 0002 prefixo nativo OpenAI, 0003 tabela de preços, 0004 MCP GitHub, 0005 retrieval versionado de `/docs`).

---

## Prompts e passos da entrevista

| Passo | Prompt | O que faz |
|-------|--------|-----------|
| `interview` / `discovery` | `resources/prompts/interview/v*.md` | Conduz a entrevista (Objetivo · Como funciona hoje · Regras · Onde · Aceite · O que não fazer · Stakeholders · Como validar) |
| `docs_retrieval` | `resources/prompts/docs_retrieval/v*.md` | Escolhe até 10 paths de `/docs` a partir do índice + resumo |
| `docs_briefing` | `resources/prompts/docs_briefing/v*.md` | Humaniza o cruzamento `/docs` × entrevista na conversa |
| `card_generation` | `resources/prompts/card_generation/v*.md` | Monta o JSON do card a partir do `interview_summary` (+ `/docs` se a revisão ok) |

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

## Stack

PHP 8.3 · Laravel 13 · Livewire 4 · Tailwind CSS + Vite · MySQL 8 · Docker · Laravel Breeze

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
