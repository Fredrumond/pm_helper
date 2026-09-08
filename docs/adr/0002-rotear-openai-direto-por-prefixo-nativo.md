**Data:** 08/09/2026

# ADR 0002 — Rotear OpenAI direto por prefixo nativo

## Contexto

O [ADR 0001](0001-desacoplar-llm-com-ports-e-adapters.md) deixou o mapa de adapters vazio e pediu prefixos que não colidam com slugs da OpenRouter (`openai/gpt-4o`). O produto passou a chamar a API da OpenAI direto, no mesmo seletor de modelos da OpenRouter.

## Opções consideradas

1. **Só OpenRouter** — ids `openai/gpt-4o`; sem chave extra, mas o custo e o rate limit passam pelo intermediário.
2. **Prefixo namespaced** (`openai-direct/gpt-4o`) — sem colisão, mas o id do catálogo deixa de ser o id oficial da API.
3. **Prefixo nativo** (`gpt-`, `o1`, `o3`, `o4`) — o id do composer é o id da OpenAI; o `LlmRouter` despacha por prefixo.

## Decisão

Adotar a opção 3. `OpenAiAdapter` entra no `LlmRouter` quando `OPENAI_API_KEY` está definida. Modelos do catálogo usam ids nativos (`gpt-4o-mini`, `gpt-4o`, `gpt-4.1`, `o4-mini`). Sem a chave, o mapa não registra o adapter e o default OpenRouter permanece.

## Justificativa

O id nativo evita traduzir modelo na hora da request. A chave opcional não quebra quem só tem OpenRouter. A opção 2 seria mais segura contra colisão, mas nenhum modelo OpenRouter do catálogo atual usa `gpt-` sem o prefixo `openai/`.

## Consequências

### Benefícios

- Segundo provedor sem mudar Livewire, como previsto no 0001.
- Mesmo id no seletor e na API da OpenAI.

### Riscos

- Colisão se no futuro o catálogo misturar `gpt-4o` (direto) e um id OpenRouter que também comece com `gpt-`.
- Snapshots (`gpt-4o-mini-2024-07-18`) dependem do mesmo prefixo.

### Débitos técnicos

- O 0001 ainda descreve `adapters: []`; este ADR substitui esse débito.
- Resolução de prompt continua duplicada entre `OpenRouterAdapter` e `OpenAiAdapter`.

### Próximos passos

- Novos adapters: prefixo que não seja slug da OpenRouter, binding só se a chave existir, teste no `LlmRouter`.
