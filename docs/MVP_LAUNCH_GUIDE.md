# PM Helper — Guia de Lançamento do MVP

> **Versão:** 0.8.0 · **Data:** 23/09/2026  
> **Público:** PMs que vão testar o PM Helper

---

## O que é

Ferramenta interna que usa IA para o PM qualificar uma demanda e gerar um card de produto alinhado ao framework do time. O PM conversa com o assistente, responde perguntas sobre a funcionalidade que precisa detalhar, e a ferramenta gera um card estruturado pronto para refinamento.

**Objetivo:** reduzir o tempo e o atrito entre "ideia de produto" e "card estimável pelo time técnico".

### Onde se encaixa na metodologia do time

O PM Helper entra no começo do [ciclo de desenvolvimento](https://github.com/Fredrumond/skills-metodologia). No MVP, ele cobre a **qualificação** (`problem-qualify`): a entrevista que organiza a demanda e o card que sai dela.

A etapa de **discovery** da metodologia continua na skill `discovery` do Cursor. Ela será desenvolvida no PM Helper numa etapa futura. Plano, ADR, code review e handoff também permanecem nas skills do Cursor.

| Etapa | Onde acontece |
|---|---|
| **Qualificação** (`problem-qualify`) | ✅ PM Helper |
| **Discovery** | Skills do Cursor — prevista para o PM Helper |
| Plano de implementação | Skills do Cursor |
| ADR e decisões de arquitetura | Skills do Cursor |
| Code review e handoff | Skills do Cursor |

O PM Helper não substitui o ciclo inteiro. Hoje ele qualifica a demanda e entrega o card; o discovery e o restante do fluxo seguem nas skills do Cursor até entrarem no aplicativo.

---

## ✅ O que está pronto

### Autenticação e papéis
- Login, registro, recuperação de senha e verificação de e-mail (Laravel Breeze)
- Dois papéis: **admin** (gerencia projetos, vê métricas e versões) e **product_manager** (usa o chat e cards)
- Cada PM vê só suas próprias conversas e cards

### Entrevistas de discovery guiadas por IA
- PM descreve o card → assistente conduz em 5 fases: **Objetivo · Como funciona hoje · Regras · Onde · Aceite · O que não fazer · Stakeholders · Como validar**
- Trava de épico: se o PM descrever demanda de vários cards, o assistente recusa e pede foco em um card definido
- Resumo automático da entrevista extraído e salvo na conversa

### Contexto do projeto via `/docs`
- No chat, antes da primeira mensagem, o PM pode vincular um projeto ativo (opcional). A escolha vale só para aquela conversa e não muda depois que ela começa
- Ao fechar a entrevista, o PM Helper lê a pasta `/docs` do repositório: escolhe os arquivos mais relevantes via prompt e gera um briefing do que já está implementado — incluindo alerta se a demanda repetir algo existente — antes de gerar o card
- O card é gerado com esse contexto quando disponível

### Geração e visualização de cards
- Botão **"Gerar Card"** aparece ao fim da entrevista
- Card gerado em JSON estruturado com todos os campos do framework, salvo com `status = draft`
- Lista de cards e visualização detalhada de cada um
- Quando a conversa teve projeto, o card mostra o nome amigável junto da prioridade

### Rastreio de consumo e modelos
- Tokens, cache e custo por chamada gravados e exibidos na sessão
- O admin define, para cada prompt (`interview`, `docs_retrieval`, `docs_briefing`, `card_generation`), qual modelo ativo o sistema usa. O PM não escolhe modelo no chat
- Catálogo de modelos administrável: cadastro, ativação, inativação e preço de modelo pago, separados por provedor (OpenRouter e OpenAI)
- Fallback automático em rate limit ou resposta vazia — só nos modelos OpenRouter

### Administração (só admin)
- Cadastro e desativação de projetos GitHub
- Modelo ativo de cada prompt e catálogo de modelos LLM
- Auditoria automática das alterações nessas telas (quem fez, quando fez, o que mudou) — só no banco, sem tela de consulta nesta versão
- Página de métricas: consumo por step, comparação entre versões de prompt, custo por modelo
- Página de histórico de versões do produto (`/versoes`)

---

## Como usar (passo a passo)

**Pré-requisito:** ter um épico definido. O PM Helper trata um card por vez.

1. Faça login com as credenciais que o time vai compartilhar
2. Clique em **"Nova conversa"**
3. Se quiser, vincule um projeto a esta conversa (opcional). Sem projeto, a entrevista segue. Depois da primeira mensagem, a escolha fica travada
4. Descreva o card em linguagem livre, ex: _"Preciso de um card para adicionar validação de CPF no checkout antes de finalizar a compra"_
5. Responda as perguntas do assistente — ele guia fase a fase, sem sobrecarregar
6. Quando o assistente fechar o resumo, leia o briefing de `/docs` (o que já existe e o que limita o card) e clique em **"Gerar Card"**
7. Revise o card gerado com todas as seções do framework

### Dicas
- Seja específico sobre **comportamento**, não sobre implementação técnica
- Se o assistente recusar por "escopo amplo", é sinal de épico: quebre em cards menores
- O modelo de cada passo é o que o admin deixou ativo. Modelos **free** não geram custo; modelos **OpenAI** são pagos e exigem `OPENAI_API_KEY` no ambiente

---

## 🔧 Ainda no MVP (antes do lançamento)

Correções do que este guia já promete. Sem elas, a lista de conversas, as métricas ou o cadastro de projetos mentem ou travam. Detalhe no [`POS_MVP_ROADMAP.md`](./POS_MVP_ROADMAP.md#11-pendências-do-mvp).

| Item | Por quê ainda no MVP | Solução prevista |
|---|---|---|
| **"Nova conversa" sem uso** | Cada clique grava uma linha vazia na lista do fluxo principal | Reusar a conversa vazia do PM, em vez de criar outra |
| **Exclusão lógica de conversa** | Hard delete apaga card e `LlmUsage` (`cascadeOnDelete`); as métricas do MVP passam a mentir | `SoftDeletes` na conversa; card e custo permanecem |
| **Reativar projeto desativado** | O repositório continua único depois do soft delete, e a tela só lista ativos — desativar por engano trava o cadastro | Bloco "Desativados" + **Reativar** |

## ⏳ Fora do escopo do MVP

Capacidade nova, não correção do que já está no produto. Prioridade no [`POS_MVP_ROADMAP.md`](./POS_MVP_ROADMAP.md) com base no feedback de uso:

| Item | Probabilidade de ser pedido | Onde no roteiro |
|---|---|---|
| **Editar card** rascunho **ou** aprovado | Alta — card vai sair com lacunas a ajustar | Trilha A, Onda 1 |
| **Mencionar um card no chat** | Média — reusar um card existente como contexto da entrevista | Trilha B, Fase 2a |
| **Exportar card** (Jira, Linear, Notion, CSV) | Alta — card precisa ir para onde o time gerencia o backlog | Trilha A, Onda 1 |
| **Desmembrar épico em cards** | Alta — PM vai querer fatiar um épico direto na ferramenta | Trilha A, Onda 1 |
| **Comentários no card** | Alta — liderança quer discutir antes do refinamento. O botão **Aprovar** (`draft` → `approved`) já existe; faltam comentários e o fluxo em volta | Trilha A, Onda 2 |
| **Notificações** (e-mail ou Slack) ao gerar card | Média — PM não vai ficar recarregando esperando | Trilha A, Onda 2 |
| **Dashboard de cards por status** | Média — visibilidade do backlog gerado | Trilha A, Onda 2 |
| **Retomar entrevista interrompida** | Média — hoje o contexto se perde ao fechar | Trilha A, Onda 2 |
| **Histórico de versões do card** | Média — saber o que mudou entre gerações | Trilha A, Onda 3 |
| **Busca semântica em cards antigos** | Baixa — evitar duplicar cards já escritos | Trilha B, Fase 2 |
| **Relatório de custo por squad** | Baixa — relevante quando escalar para vários times | Trilha A, Onda 3 |

---

## Perguntas frequentes

**"O card gerado está errado / incompleto. E agora?"**  
Copie o conteúdo e ajuste no Jira/Notion. Editar o rascunho ou o aprovado no PM Helper fica para depois do lançamento (Onda 1 do roteiro pós-MVP).

**"Por que o assistente recusou minha demanda?"**  
Ela provavelmente cobre mais de um card. É feedback válido — anote o caso e traga para o time calibrar o critério.

**"Os dados ficam seguros?"**  
O conteúdo das conversas só sai do ambiente nas chamadas à LLM. Modelos Free vão para a [OpenRouter](https://openrouter.ai); GPT/o4 vão direto para a [OpenAI](https://platform.openai.com). Nenhum dos dois treina modelos com dados via API.

**"Por que o custo da OpenAI aparece como estimado?"**  
A OpenAI devolve só tokens, não o valor em dólar. O PM Helper calcula pelo preço que o admin gravou no catálogo de modelos. A OpenRouter já manda o custo real — inclusive zero nos modelos grátis.

---

## Como reportar problemas

Canal do time no Slack com a tag `#pm-helper-feedback`, incluindo:
1. O que você tentou fazer
2. O que aconteceu (print se possível)
3. Se foi comportamento do assistente (prompt) ou da interface
