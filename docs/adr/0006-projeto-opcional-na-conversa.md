**Data:** 22/09/2026

# ADR 0006 — Projeto opcional gravado na conversa

## Contexto

O projeto da entrevista vinha da sessão (`CurrentProject`): uma tela **Projetos** escolhia um repositório para todas as conversas da sessão, e o [ADR 0004](0004-ler-docs-via-mcp-github.md) lia `/docs` a partir dessa seleção. O card gerado não dizia qual projeto tinha sido usado. O [Discovery 0006](../discovery/0006-escolha-opcional-de-projeto-no-chat.md) troca a sessão por uma escolha opcional, por conversa, travada no primeiro envio. O *como* ler `/docs` (port MCP, payload na sessão, teto) continua o do ADR 0004.

## Opções consideradas

1. **Manter `CurrentProject` na sessão** — uma escolha vale para toda a sessão; conversa nova herda o projeto; o card não tem de onde mostrar o nome.
2. **Copiar `project_id` para o card** — a tag fica no card, mas a escolha existe antes do card e a entrevista já usa o projeto.
3. **Projeto obrigatório** — toda entrevista teria repositório; o discovery deixa seguir sem projeto.
4. **`conversations.project_id` anulável, só na conversa** — a entrevista e a tag leem a mesma relação; o card não guarda cópia.

## Decisão

Adotar a opção 4.

- **Onde:** `conversations.project_id` (FK anulável, `nullOnDelete`). A relação `project()` usa `withTrashed()` para a tag continuar com o nome amigável depois da desativação.
- **Quando:** o PM escolhe no compositor, antes da primeira mensagem. Conversa nova começa sem projeto. Com mensagens, a coluna não muda.
- **Quem pode ser escolhido:** só projeto ativo. Id ausente ou desativado não grava. Se o projeto deixa de estar ativo antes da primeira mensagem, a referência é limpa.
- **Consumo de `/docs`:** `ConversationChat` lê `conversation.project`. Sem projeto, a entrevista segue e não chama o gateway. O port e o payload `project_docs.{conversationId}` do ADR 0004 permanecem.
- **UI:** nome amigável na tag do card (listagem, cabeçalho, preview). `owner/repo` não aparece para o PM.

## Justificativa

A escolha é da conversa, não da sessão: cada entrevista tem no máximo um projeto e um card. Gravar na conversa cobre a entrevista e a tag sem duplicar a referência. Tornar obrigatório ou copiar para o card aumentaria o modelo sem mudar o fluxo.

## Consequências

### Benefícios

- Conversa nova não herda projeto da anterior.
- A tag e a leitura de `/docs` usam a mesma fonte.
- O ADR 0004 segue válido para o transporte; muda só de onde sai o projeto.

### Riscos

- Quem ler só o ADR 0004 ainda vê `CurrentProject` como origem. A origem passa a ser esta decisão.
- Projeto desativado no meio da entrevista continua valendo, porque a trava já ocorreu.

### Débitos técnicos

- O parágrafo de contexto do ADR 0004 cita `CurrentProject`. Não reescrever aquele ADR; este o substitui nesse ponto.

### Próximos passos

Nenhum. Novo código que precise do projeto da entrevista lê `conversation.project`, não a sessão.
