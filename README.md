# PM Card Assistant

Assistente de Discovery para Product Managers. Transforma ideias em cards de desenvolvimento estruturados via conversa guiada com LLM (via OpenRouter).

## Stack

- **Laravel 13** + PHP 8.3
- **MySQL 8**
- **Blade + Livewire 3**
- **Laravel Authentication** (Breeze)
- **OpenRouter** (LLM)
- **Docker** (ambiente local)

---

## Setup inicial

### Pré-requisitos

- Docker Desktop rodando
- Chave de API do [OpenRouter](https://openrouter.ai/keys)

### 1. Clone e configure variáveis

```bash
cp .env.example .env
# Edite .env e preencha OPENROUTER_API_KEY
```

### 2. Suba os containers

```bash
docker-compose up -d --build
```

### 3. Bootstrap do Laravel (apenas na primeira vez)

```bash
bash scripts/bootstrap.sh
```

> Isso instala o Laravel 13, Livewire, Laravel Breeze e roda as migrations.

### 4. Acesse

```
http://localhost:8080
```

---

## Estrutura do Projeto

```
app/
├── Http/Controllers/
│   ├── ConversationController.php
│   └── CardController.php
├── Jobs/
│   └── ProcessConversationMessage.php   ← Chama a LLM via Queue
├── Livewire/
│   ├── ConversationChat.php             ← Interface do chat
│   └── CardPreview.php                  ← Visualização do card
├── Models/
│   ├── Conversation.php
│   ├── Message.php
│   └── Card.php
└── Services/
    ├── OpenRouterService.php            ← Integração com OpenRouter
    └── CardParserService.php            ← Parser do JSON gerado pela LLM
```

---

## Fluxo do Assistente

1. PM inicia uma conversa e descreve sua necessidade
2. O agente **nunca gera o card imediatamente** — conduz um processo de discovery
3. O agente faz perguntas sobre: problema, usuário afetado, impacto, critérios de aceite, restrições
4. Após ~3-5 trocas de qualidade, o agente gera o card em formato JSON estruturado
5. O card aparece em painel lateral e pode ser aprovado pelo PM

---

## Comandos úteis

```bash
# Logs da aplicação
docker-compose logs -f app

# Logs do worker de filas
docker-compose logs -f queue

# Artisan
docker-compose exec app php artisan <comando>

# Migrations
docker-compose exec app php artisan migrate

# Tinker
docker-compose exec app php artisan tinker
```

---

## Modelos LLM suportados (OpenRouter)

Configure via `OPENROUTER_MODEL` no `.env`:

| Modelo | Custo | Qualidade |
|--------|-------|-----------|
| `anthropic/claude-3.5-sonnet` | $$ | ⭐⭐⭐⭐⭐ |
| `anthropic/claude-3-haiku` | $ | ⭐⭐⭐⭐ |
| `openai/gpt-4o` | $$ | ⭐⭐⭐⭐⭐ |
| `openai/gpt-4o-mini` | $ | ⭐⭐⭐⭐ |
| `google/gemini-flash-1.5` | $ | ⭐⭐⭐⭐ |
