**Data:** 16/09/2026

# ADR 0005 — Filtrar `/docs` via prompt versionado em dois turns

## Contexto

O [ADR 0004](0004-ler-docs-via-mcp-github.md) definiu *como* ler `/docs` (MCP + port, token efêmero, payload na sessão). O dump recursivo da árvore é tudo ou nada: abaixo do teto entram ADRs, histórico e docs irrelevantes; acima, o card sai sem `/docs`. O [Discovery 0004](../discovery/0004-prompt-retrieval-docs-versionado.md) pede um step intermediário que escolha o que ler. O port continua o único ponto que fala com o MCP.

## Opções consideradas

1. **Dump completo** — simples; ruído no prompt e aborto em repositórios grandes.
2. **Agente com tools** (LLM chama o MCP) — o modelo escolhe arquivos na hora; mistura GitHub em `LlmGateway` e foi descartado no ADR 0004.
3. **Busca semântica / embeddings** — seleciona bem; infra nova, fora do escopo.
4. **Dois turns determinísticos** — o port lista só paths; um prompt versionado devolve JSON de paths; o port lê o subset. Se o retrieval falhar ou vier vazio, volta ao dump.

## Decisão

Adotar a opção 4.

- **Orquestração:** `DocsRetrievalService` (listar → `completePrompt` → ler subset → fallback).
- **Port:** `listDocsPaths` e `readDocsByPaths` em `ProjectDocsGateway`; teto de chars sobre o filtrado.
- **LLM:** `LlmGateway::completePrompt` — prompt avulso, sem histórico da conversa. Prompt `docs_retrieval@v1`; saída `{"paths": [...]}` com no máximo 10 arquivos. Modelo configurável, padrão o do card.
- **Fallback:** lista vazia, parse inválido, timeout ou erro de MCP → `readProjectDocs` (comportamento do ADR 0004).
- **Índice:** só caminhos relativos, sem conteúdo.

O briefing na conversa ([Discovery 0005](../discovery/0005-briefing-docs-na-conversa.md)) reusa `completePrompt`; não muda este contrato.

## Justificativa

Dois turns mantêm o MCP no port e a estratégia de relevância num prompt versionável, independente do `card_generation`. Custa uma chamada extra, mas corta tokens e o aborto por teto. Agente-com-tools quebraria o 0004; embeddings são infra demais para o MVP.

## Consequências

### Benefícios

- Iterar “o que é relevante” sem mexer no prompt do card.
- Chat e testes fakeiam o port; o retrieval não conhece GitHub.
- Fallback preserva o caminho anterior.

### Riscos

- JSON inválido ou paths inventados caem no dump e reintroduzem ruído.
- Índice só com paths pode escolher mal; primeira linha fica para um v2.
- Duas chamadas LLM (retrieval + card, e briefing se houver) aumentam custo e latência.

### Débitos técnicos

- Parser de JSON no serviço, não no adapter.
- Dump de fallback ainda pode estourar o teto em repos grandes.

### Próximos passos

- Medir qualidade do retrieval (`paths_selected` / `paths_discarded`) antes de enriquecer o índice.
- Briefing na conversa: Discovery 0005, mesmo `completePrompt`.
