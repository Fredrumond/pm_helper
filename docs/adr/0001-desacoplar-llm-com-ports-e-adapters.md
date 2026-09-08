**Data:** 08/09/2026

# ADR 0001 — Desacoplar a LLM com Ports & Adapters

## Contexto

O chat dependia diretamente de `OpenRouterService`. Qualquer outro provedor (Anthropic direto, Ollama local, OpenAI) exigiria mudar o Livewire. O produto já usa ids no formato `provedor/modelo` da OpenRouter e precisa de um ponto único para escolher a rota de saída.

## Opções consideradas

1. **Manter o serviço único** — simples, mas o UI continua acoplado ao OpenRouter.
2. **Injetar o adapter via config/env** (`LLM_DRIVER=openrouter`) — troca o provedor inteiro, sem escolher por modelo.
3. **Port `LlmGateway` + `LlmRouter` por prefixo do modelo** — o chat depende do contrato; o router escolhe o adapter pelo início do id (`ollama/`, `anthropic/`, …) e cai no default se não houver match.

## Decisão

Adotar Ports & Adapters:

- **Port:** `App\Contracts\LlmGateway` (`chat`, `generateCard`).
- **Router:** `App\Services\LlmRouter` implementa o port e resolve o adapter pelo prefixo do modelo.
- **Adapter padrão:** `App\Services\Adapters\OpenRouterAdapter`.
- **Binding:** `AppServiceProvider` injeta `LlmGateway` → `LlmRouter` com `OpenRouterAdapter` como default e `adapters: []` até existir outro provedor.

O catálogo de modelos (`config/chat.php`) e o fallback de rate limit (`OPENROUTER_FALLBACK_MODELS`) continuam responsabilidade do adapter OpenRouter, não do router.

## Justificativa

O prefixo do id já distingue o provedor. O Livewire não precisa saber quem atende a chamada. Novos adapters entram no mapa do provider, sem alterar `ConversationChat`. A opção 2 seria insuficiente quando dois provedores coexistirem no mesmo seletor.

## Consequências

### Benefícios

- UI e testes de fluxo dependem só do contrato.
- Abrir um segundo provedor é adicionar adapter + prefixo no binding.
- OpenRouter permanece o caminho único hoje; o roteamento por prefixo não muda o comportamento atual.

### Riscos

- Prefixos ambíguos (`anthropic/` na OpenRouter vs API direta) exigem convenção explícita no mapa.
- Sem adapter registrado, todo modelo — inclusive `openai/` e `anthropic/` — segue para a OpenRouter.

### Débitos técnicos

- O mapa `adapters` ainda está vazio; o router só exerce o default.
- Resolução de prompt, usage e fallback de rate limit continuam dentro do `OpenRouterAdapter`.

### Próximos passos

- Ao adicionar um adapter, registrar o prefixo em `AppServiceProvider` e um teste no `LlmRouterTest`.
- Definir prefixos que não colidam com slugs da OpenRouter (ex.: `ollama/`, `anthropic-direct/`).
