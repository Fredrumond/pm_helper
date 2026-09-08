**Data:** 08/09/2026

# ADR 0003 — Estimar custo de LLM pela tabela de preços

## Contexto

A OpenRouter devolve `usage.cost` e as métricas usam esse campo. A OpenAI (e a maioria das APIs diretas) devolve só tokens. Sem regra comum, chamadas pagas entram com `cost = 0` e aparecem como “Grátis”, o que invalida consumo por modelo, sessão e comparação de prompts. Todo adapter novo enfrenta o mesmo buraco.

## Opções consideradas

1. **Só gravar tokens** — métricas honestas no que a API manda; custo some da UI.
2. **Cada adapter calcula o próprio custo** — funciona, mas o padrão se perde e um adapter novo esquece.
3. **Consultar a API de billing do provedor** — valor de fatura, porém assíncrono, por conta, não por request.
4. **Tabela `config/llm.php` + `LlmUsage::recordFromResponse()`** — se a API mandar `cost`, prevalece; senão estima tokens × preço / 1M.

## Decisão

Adotar a opção 4. `LlmPricing` resolve o modelo (incluindo snapshot pelo prefixo mais longo). `LlmUsage` estima na gravação e, nas métricas, usa `effectiveCost()` para registros antigos com `cost = 0`. Modelos sem entrada na tabela continuam “Grátis”. A UI aponta para `config/llm.php`.

## Justificativa

O ponto único de gravação obriga o próximo adapter a cadastrar preço, em vez de espalhar regra. A fatura real (opção 3) não fecha por conversa. A OpenRouter paga continua usando o valor da API, inclusive `0` nos modelos free.

## Consequências

### Benefícios

- Métricas de custo funcionam para OpenAI e para qualquer adapter futuro.
- OpenRouter não muda de comportamento.

### Riscos

- Estimativa diverge da fatura se a tabela atrasar um reajuste de preço.
- `effectiveCost()` em linhas antigas não regrava o banco; só a exibição.

### Débitos técnicos

- Preços OpenAI estão manuais em `config/llm.php`.
- Não há alerta na UI quando um modelo pago novo não foi cadastrado — só warning no log.

### Próximos passos

- Ao adicionar adapter: cadastrar `input` / `cached` / `output` (USD / 1M) em `config/llm.php`.
- Revisar a tabela quando o provedor mudar preço.
