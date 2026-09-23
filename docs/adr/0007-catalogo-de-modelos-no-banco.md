**Data:** 23/09/2026

# ADR 0007 — Catálogo de modelos no banco, com provedor explícito

## Contexto

Os ADRs [0001](0001-desacoplar-llm-com-ports-e-adapters.md), [0002](0002-rotear-openai-direto-por-prefixo-nativo.md) e [0003](0003-estimar-custo-llm-pela-tabela-de-precos.md) deixaram o catálogo em `config/chat.php`, o roteamento por prefixo do id (`gpt-`, `o1`…) e o preço em `config/llm.php`. Incluir modelo, corrigir preço ou evitar colisão de prefixo exigia deploy. O [Discovery 0009](../discovery/0009-catalogo-de-modelos-de-llm-administravel-por-provedor.md) pede um catálogo administrável. Sem uma fonte única, lista, rota e preço continuariam em três lugares.

## Opções consideradas

1. **Manter config + prefixo** — alinhado aos ADRs atuais; cada modelo ou reajuste de preço exige deploy; o risco de colisão do 0002 permanece.
2. **Catálogo no banco, roteamento e preço iguais** — o admin cadastra o modelo, mas o router ainda infere o adapter pelo id e o preço continua em `config/llm.php`.
3. **Um registro no banco como fonte da verdade** — `llm_models` guarda id, provedor, ativo e preço; `LlmModelCatalog` é a lista oferecida; `LlmRouter` despacha pelo `provider` do registro; `LlmPricing` lê o preço do mesmo registro.

## Decisão

Adotar a opção 3.

- **Fonte:** tabela `llm_models`, acessada por `LlmModelCatalog`. `config/chat.php` (`models`) e `config/llm.php` (`pricing`) deixam de ser lidos em produção.
- **Roteamento:** o mapa do `LlmRouter` passa a ser `provider => adapter` (`openai`, default OpenRouter). O prefixo do id (ADR 0002) deixa de decidir a rota. Modelo inativo ou ausente cai no default.
- **Preço:** `LlmPricing` estima a partir das colunas `price_*` do modelo pago no catálogo. A regra do 0003 (API `cost` prevalece; senão tokens × preço / 1M) permanece.
- **Carga inicial:** comando `llm-models:import-from-config` com o snapshot que vivia em config.
- **Provedor novo:** entra em `LlmModel::PROVIDERS`, ganha adapter no `AppServiceProvider` (só se a chave existir) e só então pode ser cadastrado na tela.

Os ADRs 0001 (ports & adapters), 0002 (OpenAI direto, chave opcional) e 0003 (`LlmUsage` + `effectiveCost`) continuam válidos no restante. Este ADR substitui só a origem do catálogo, o critério de roteamento e a origem do preço.

## Justificativa

Lista, rota e preço descrevem o mesmo modelo. Separá-los (opção 2) manteria o deploy para preço e o risco de colisão do prefixo. O campo `provider` elimina a convenção frágil do 0002: um id `gpt-*` na OpenRouter não é mais despachado para a OpenAI por engano. A opção 1 não atende o discovery.

## Consequências

### Benefícios

- Incluir, inativar e reajustar preço sem deploy.
- Colisão de prefixo do 0002 deixa de ser o mecanismo de roteamento.
- Métricas de custo acompanham o preço que o admin acabou de gravar.

### Riscos

- Catálogo vazio (migrate sem o import) deixa o produto sem modelo ativo.
- Admin pode cadastrar um id inválido para o provedor; a falha só aparece na chamada.
- Quem ler só o 0001–0003 ainda vê config e prefixo como fonte.

### Débitos técnicos

- `config/chat.php` e `config/llm.php` ficam como chaves vazias/legado.
- O fallback de `docs_retrieval` / `docs_briefing` ainda lê `config('chat.prompts.*.model')` quando não há linha em `prompt_models`.

### Próximos passos

- Novo adapter: constante em `LlmModel::PROVIDERS`, binding no provider, teste no `LlmRouter`. Não cadastrar prefixo.
- Não reescrever 0001–0003; este ADR os substitui nos pontos acima.
