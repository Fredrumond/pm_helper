# PM Helper — Guia de Lançamento do MVP

> **Versão:** 0.7.4 · **Data:** 22/09/2026  
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
- Seletor de modelo no chat: modelos **free** (via OpenRouter) e **OpenAI** (GPT-4o Mini, GPT-4o, GPT-4.1, o4 Mini) em chamada direta
- Fallback automático em rate limit ou resposta vazia — só nos modelos OpenRouter

### Administração (só admin)
- Cadastro e desativação de projetos GitHub
- Página de métricas: consumo por step, comparação entre versões de prompt, custo por modelo
- Página de histórico de versões do produto (`/versoes`)

---

## Como usar (passo a passo)

**Pré-requisito:** ter um épico definido. O PM Helper trata um card por vez.

1. Faça login com as credenciais que o time vai compartilhar
2. Clique em **"Nova conversa"**
3. Se quiser, ao lado do modelo, vincule um projeto a esta conversa (opcional). Sem projeto, a entrevista segue. Depois da primeira mensagem, a escolha fica travada
4. Descreva o card em linguagem livre, ex: _"Preciso de um card para adicionar validação de CPF no checkout antes de finalizar a compra"_
5. Responda as perguntas do assistente — ele guia fase a fase, sem sobrecarregar
6. Quando o assistente fechar o resumo, leia o briefing de `/docs` (o que já existe e o que limita o card) e clique em **"Gerar Card"**
7. Revise o card gerado com todas as seções do framework

### Dicas
- Seja específico sobre **comportamento**, não sobre implementação técnica
- Se o assistente recusar por "escopo amplo", é sinal de épico: quebre em cards menores
- Modelos **Free** não geram custo; modelos **OpenAI** são pagos e exigem `OPENAI_API_KEY` no ambiente

---

## ⏳ Fora do escopo do MVP

Funcionalidades que **não estão no MVP** e serão priorizadas com base no feedback de uso:

| Item | Probabilidade de ser pedido |
|---|---|
| **Editar card** diretamente no PM Helper | Alta — card vai sair com lacunas a ajustar |
| **Exportar card** (Jira, Linear, Notion, CSV) | Alta — card precisa ir para onde o time gerencia o backlog |
| **Desmembrar épico em cards** | Alta — PM vai querer fatiar um épico direto na ferramenta |
| **Comentários e aprovação de card** | Alta — liderança quer aprovar antes do refinamento |
| **Notificações** (e-mail ou Slack) ao gerar card | Média — PM não vai ficar recarregando esperando |
| **Dashboard de cards por status** | Média — visibilidade do backlog gerado |
| **Retomar entrevista interrompida** | Média — hoje o contexto se perde ao fechar |
| **Histórico de versões do card** | Média — saber o que mudou entre gerações |
| **Busca semântica em cards antigos** | Baixa — evitar duplicar cards já escritos |
| **Relatório de custo por squad** | Baixa — relevante quando escalar para vários times |

---

## Perguntas frequentes

**"O card gerado está errado / incompleto. E agora?"**  
Copie o conteúdo e ajuste no Jira/Notion. Edição direta no PM Helper está na lista de próximas iterações.

**"Por que o assistente recusou minha demanda?"**  
Ela provavelmente cobre mais de um card. É feedback válido — anote o caso e traga para o time calibrar o critério.

**"Os dados ficam seguros?"**  
O conteúdo das conversas só sai do ambiente nas chamadas à LLM. Modelos Free vão para a [OpenRouter](https://openrouter.ai); GPT/o4 vão direto para a [OpenAI](https://platform.openai.com). Nenhum dos dois treina modelos com dados via API.

**"Por que o custo da OpenAI aparece como estimado?"**  
A OpenAI devolve só tokens, não o valor em dólar. O PM Helper calcula pela tabela interna (`config/llm.php`). A OpenRouter já manda o custo real — inclusive zero nos modelos grátis.

---

## Como reportar problemas

Canal do time no Slack com a tag `#pm-helper-feedback`, incluindo:
1. O que você tentou fazer
2. O que aconteceu (print se possível)
3. Se foi comportamento do assistente (prompt) ou da interface
