# PM Helper — Guia de Lançamento do MVP

> **Versão:** produto atual (baseline 0.10.0 + Discovery no card) · **Data:** 24/10/2026
> **Público:** admin e PMs no teste e2e de fechamento do MVP
> **Baseline anterior deste guia:** 0.10.0 (19/10/2026) · Explorar/RAG
> **Plano de teste:** [`E2E_MANUAL_TEST_PLAN.md`](./E2E_MANUAL_TEST_PLAN.md)

---

## O que é

Ferramenta interna que usa IA para o PM qualificar uma demanda e gerar um card de produto alinhado ao framework do time. O PM conversa com o assistente, responde perguntas sobre a funcionalidade que precisa detalhar, e a ferramenta gera um card estruturado pronto para refinamento.

Há um segundo chat, o **Explorar**: pergunta livre sobre a documentação já indexada, sem gerar card. Em card **aprovado** com projeto, o PM dispara o **Discovery por agente** (Cursor) e pode responder às dúvidas para regenerar.

**Objetivo:** reduzir o tempo e o atrito entre "ideia de produto" e "card estimável pelo time técnico", com um primeiro Discovery embasado no código.

### Onde se encaixa na metodologia do time

O PM Helper entra no começo do [ciclo de desenvolvimento](https://github.com/Fredrumond/skills-metodologia). No MVP ele cobre a **qualificação** (`problem-qualify`), a **consulta à documentação indexada** e o **Discovery** a partir do card aprovado.

Plano, ADR, code review e handoff continuam nas skills do Cursor.

| Etapa | Onde acontece |
|---|---|
| **Qualificação** (`problem-qualify`) | ✅ PM Helper |
| **Consulta à documentação indexada** | ✅ PM Helper (Explorar) |
| **Discovery** | ✅ PM Helper (card aprovado → agente) |
| Plano de implementação | Skills do Cursor |
| ADR e decisões de arquitetura | Skills do Cursor |
| Code review e handoff | Skills do Cursor |

O PM Helper não substitui o ciclo inteiro. Fecha qualificação → card → Discovery. O restante segue nas skills do Cursor.

---

## ✅ O que está pronto

### Autenticação e papéis
- Login, registro, recuperação de senha e verificação de e-mail (Laravel Breeze)
- Dois papéis: **admin** (gerencia projetos, vê métricas e versões) e **product_manager** (usa o chat, o Explorar, documentos e cards)
- Cada PM vê só suas próprias conversas e cards

### Entrevistas de qualificação guiadas por IA
- PM descreve o card → assistente conduz em 5 fases: **Objetivo · Como funciona hoje · Regras · Onde · Aceite · O que não fazer · Stakeholders · Como validar**
- Trava de épico: se o PM descrever demanda de vários cards, o assistente recusa e pede foco em um card definido
- Resumo automático da entrevista extraído e salvo na conversa
- A conversa fica gravada. Reabrir a conversa devolve o histórico e a entrevista continua de onde parou

### Contexto do projeto na entrevista
- No chat, antes da primeira mensagem, o PM pode vincular um projeto ativo (opcional). A escolha vale só para aquela conversa e não muda depois que ela começa
- Ao fechar a entrevista, o PM Helper lê a pasta `/docs` do repositório na hora: escolhe os arquivos mais relevantes via prompt e gera um briefing do que já está implementado — incluindo alerta se a demanda repetir algo existente — antes de gerar o card
- O card é gerado com esse conteúdo lido na sessão, quando a leitura termina bem
- Essa leitura da entrevista **não** usa o índice do Explorar. São dois caminhos

### Explorar e documentos indexados
- **Explorar:** chat livre, separado da entrevista. Não gera card. O projeto é opcional e trava depois da primeira mensagem
- Com projeto, a resposta usa os documentos daquele projeto. Sem projeto, usa só os documentos enviados na tela **Documentos** (upload manual, sem projeto)
- A documentação do repositório entra no índice no primeiro salvamento do projeto: pasta `/docs` e `README.md` da raiz, só arquivos `.md`. O admin pode sincronizar de novo; em sucesso, o índice daquele projeto é substituído
- Cada arquivo vira trechos com embedding. A busca fica com os trechos mais próximos, acima do score mínimo definido pelo admin
- A resposta que veio de documento mostra, no tooltip, o nome do arquivo de origem
- Documento com `status: deprecated`, `draft` ou outro valor que não seja `current` ou `stable` fica fora da busca depois da próxima sincronização. Sem status no frontmatter, o documento continua entrando

### Geração e visualização de cards
- Botão **"Gerar Card"** aparece ao fim da entrevista
- Card gerado em JSON estruturado com todos os campos do framework, salvo com `status = draft`
- Lista de cards e visualização detalhada de cada um
- Quando a conversa teve projeto, o card mostra o nome amigável junto da prioridade
- Botão **Aprovar** (`draft` → `approved`)

### Discovery por agente (card aprovado)
- Só o **dono** do card, com status `approved` e conversa com projeto (repositório + branch)
- **"Gerar Discovery"** dispara o agente (Cursor); a tela mostra solicitado / em andamento / concluído / falhou
- Ao concluir, o Markdown fica nos **anexos** do card, renderizado no produto
- Dúvidas da seção 6 do documento ganham campo de resposta; **"Gerar de novo com respostas"** regenera e substitui o anexo
- **"Gerar de novo"** sem respostas também substitui o anterior; em falha, o documento anterior e as respostas preenchidas permanecem
- Fora do MVP: editar o Discovery à mão, histórico de versões, gravar no repo/PR, notificação fora da tela, custo do agente nas métricas LLM

### Rastreio de consumo e modelos
- Tokens, cache e custo por chamada gravados e exibidos na sessão, inclusive embedding e consulta do Explorar
- O admin define, para cada prompt (`interview`, `docs_retrieval`, `docs_briefing`, `card_generation`, `explore`), qual modelo ativo o sistema usa. O PM não escolhe modelo no chat
- Catálogo de modelos administrável: cadastro, ativação, inativação e preço de modelo pago, separados por provedor (OpenRouter e OpenAI)
- Fallback automático em rate limit ou resposta vazia — só nos modelos OpenRouter

### Administração (só admin)
- Cadastro e desativação de projetos GitHub, com botão para sincronizar de novo os `.md` do projeto
- Modelo ativo de cada prompt, inclusive o Explorar, e catálogo de modelos LLM
- Parâmetros do índice: tamanho do trecho, estratégia de corte (seção, parágrafo ou tamanho), modelo de embedding, quantidade de trechos, score mínimo e quais pastas do repositório entram na sincronização (`README.md`, `docs/`, `docs/adr/`, `docs/discovery/`, `docs/references/`, `docs/rules/`)
- Auditoria automática das alterações nas telas administrativas (quem fez, quando fez, o que mudou) — só no banco, sem tela de consulta nesta versão
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
7. Revise o card gerado com todas as seções do framework e clique em **Aprovar**
8. Com projeto na conversa: **Gerar Discovery**, leia o anexo; se houver dúvidas, responda e use **Gerar de novo com respostas**
9. Para perguntar o que a documentação já diz, sem gerar card, abra **Explorar**, vincule o projeto se quiser, e pergunte em linguagem livre. O tooltip da resposta lista o arquivo usado

### Dicas
- Seja específico sobre **comportamento**, não sobre implementação técnica
- Se o assistente recusar por "escopo amplo", é sinal de épico: quebre em cards menores
- O modelo de cada passo é o que o admin deixou ativo. Modelos **free** não geram custo; modelos **OpenAI** são pagos e exigem `OPENAI_API_KEY` no ambiente
- O Explorar responde com o índice. O card da entrevista usa a leitura de `/docs` feita na hora do fechamento
- Discovery exige `CURSOR_API_KEY`, fila (`QUEUE_CONNECTION=database` + worker) e acesso da conta Cursor ao repositório do projeto

---

## O que ficou para depois do MVP

Explorar/RAG e Discovery no card já saíram do escopo pendente do MVP. O detalhe do que ainda é trilha está no [`POS_MVP_ROADMAP.md`](./POS_MVP_ROADMAP.md). Use o [`E2E_MANUAL_TEST_PLAN.md`](./E2E_MANUAL_TEST_PLAN.md) para achar o que dói de verdade antes de priorizar.

### Em aberto

| Item | Por que segue em aberto |
|---|---|
| **Reusar conversa vazia** | Cada **"Nova conversa"** sem mensagem entra na lista |
| **Exclusão lógica de conversa** | Excluir a conversa apaga o card e o custo |
| **Reativar projeto desativado** | Desativar por engano trava o mesmo repositório; a tela só desativa |
| **Editar card** rascunho ou aprovado | O preview mostra e aprova; o texto do card fica congelado |
| **Exportar card** (Jira, Linear, Notion, CSV) | O card gerado não chega sozinho ao backlog do time |
| **Desmembrar épico em cards** | Escopo amplo é recusado; falta o caminho de fatiar |
| **Mencionar um card no chat** | A entrevista não reusa um card já salvo como contexto |
| **Comentários no card** | Aprovar existe; faltam comentários de discussão (responder dúvida do Discovery não é comentário) |
| **Notificações** (e-mail ou Slack) | Card e Discovery: o PM acompanha na tela (há poll no painel do Discovery) |
| **Dashboard de cards por status** | Não há visão do backlog gerado |
| **Histórico de versões do card / do Discovery** | Cada regeneração do Discovery substitui o anterior; o card não versiona |
| **Busca em cards antigos** | O alerta de demanda já coberta olha `/docs` na entrevista, não os cards do PM Helper |
| **Relatório de custo por squad** | As métricas não separam consumo por time; custo do agente Cursor não entra em `LlmUsage` |
| **Índice na geração do card** | Briefing e card continuam na leitura ao vivo de `/docs`, com duas chamadas de modelo por entrevista |
| **Selo de card sem contexto verificado** | Card gerado sem `/docs` não avisa isso na lista nem no preview |
| **Nota de contexto do projeto** | Não há um texto de produto reaproveitado em toda entrevista daquele projeto |

### Parcial

| Item | O que já cobre | O que falta |
|---|---|---|
| **Retomar entrevista** | Reabrir a conversa devolve as mensagens e a entrevista segue | O conteúdo bruto de `/docs` usado no **Gerar Card** mora na sessão. Fechar o navegador antes de gerar o card perde esse bloco |
| **README quando falta `/docs`** | `README.md` entra no índice do Explorar, junto com `/docs` | A entrevista não consulta esse índice. `ARCHITECTURE.md` e `AGENTS.md` não são lidos |
| **Contexto colado pelo PM** | A tela **Documentos** aceita `.md` sem projeto, e o Explorar sem projeto busca esses arquivos | Não há campo na entrevista, e o upload não entra na geração do card |
| **Trechos da documentação** | O Explorar corta o arquivo (por seção, parágrafo ou tamanho), guarda a origem e busca por significado | A entrevista ainda escolhe o arquivo inteiro. Não há busca por palavra nem combinação das duas |
| **Atualizar a documentação indexada** | O admin sincroniza de novo o projeto; em sucesso o índice daquele projeto é trocado | Não há atualização automática, fila, agenda nem webhook. Arquivo igual é reprocessado |
| **Fontes da documentação** | O admin escolhe quais caminhos do GitHub entram no índice | Notion, Confluence, PDF e busca no código do repositório seguem de fora |

### Concluído desde a 0.8.0 (não reabrir no pós-MVP)

| Entrega | Quando |
|---|---|
| Chat **Explorar**, tela **Documentos**, índice `/docs` + `README.md` | 0.9.0 |
| Embedding, métricas, modelo do Explorar, parâmetros do índice, sync e pastas | 0.9.0 → produto atual |
| Busca do Explorar ignora `deprecated` / `draft` | 0.10.0 |
| **Gerar Discovery** no card aprovado (fila, estados, anexo renderizado) | discovery 0010 |
| Responder dúvidas do Discovery e regenerar com respostas | discovery 0011 |

---

## Perguntas frequentes

**"O card gerado está errado / incompleto. E agora?"**
Copie o conteúdo e ajuste no Jira/Notion. Editar o rascunho ou o aprovado no PM Helper continua em aberto.

**"Por que o assistente recusou minha demanda?"**
Ela provavelmente cobre mais de um card. É feedback válido — anote o caso e traga para o time calibrar o critério.

**"Os dados ficam seguros?"**
O conteúdo das conversas sai do ambiente nas chamadas à LLM. Modelos Free vão para a [OpenRouter](https://openrouter.ai); GPT/o4 vão direto para a [OpenAI](https://platform.openai.com). O texto indexado para o Explorar fica no banco e também segue para o provedor de embeddings. Nenhum dos provedores treina modelos com dados via API.

**"Por que o custo da OpenAI aparece como estimado?"**
A OpenAI devolve só tokens, não o valor em dólar. O PM Helper calcula pelo preço que o admin gravou no catálogo de modelos. A OpenRouter já manda o custo real — inclusive zero nos modelos grátis.

**"O Explorar e o card enxergam a mesma documentação?"**
O Explorar consulta o índice (trechos já gravados). O card usa a leitura de `/docs` feita ao fechar a entrevista. Sincronizar o projeto atualiza o Explorar. Não muda, sozinho, o briefing da entrevista.

**"Como gera o Discovery no card?"**
Aprove o card (conversa com projeto). Em **Gerar Discovery**, o agente roda no repositório cadastrado. Quando concluir, o Markdown aparece nos anexos. Dúvidas da seção 6 podem ser respondidas e regeneradas. Exige `CURSOR_API_KEY` e worker da fila.

---

## Como reportar problemas

Canal do time no Slack com a tag `#pm-helper-feedback`, incluindo:
1. O que você tentou fazer
2. O que aconteceu (print se possível)
3. Se foi comportamento do assistente (prompt) ou da interface
