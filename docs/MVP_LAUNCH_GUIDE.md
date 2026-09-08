# PM Helper — Guia de Lançamento do MVP

> **Versão de referência:** 0.6.4  
> **Data:** 08/09/2026  
> **Público-alvo deste documento:** Liderança de produto e PMs que vão testar o PM Helper

---

## O que é o PM Helper?

PM Helper é uma ferramenta interna que usa IA para conduzir entrevistas de discovery com PMs e gerar cards de produto alinhados ao framework do time. O PM conversa com o assistente no chat, responde perguntas sobre a funcionalidade que precisa detalhar, e ao final a ferramenta gera automaticamente um card estruturado pronto para refinamento.

O objetivo central: **reduzir o tempo e o atrito entre "ideia de produto" e "card estimável pelo time técnico"**.

---

## ✅ O que já está pronto (escopo do MVP)

### 1. Autenticação e acesso seguro
- Login, registro, recuperação de senha e verificação de e-mail (via Laravel Breeze)
- Cada PM tem seu próprio espaço isolado — nenhuma conversa ou card aparece para outros usuários

### 2. Entrevistas de discovery guiadas por IA

O fluxo central do produto:

1. **PM cria uma nova conversa** e descreve o que precisa
2. **Assistente conduz a entrevista em 5 fases** cobrindo os campos do framework do time:
   - **Objetivo** — o que muda para o cliente ou negócio
   - **Como funciona hoje** — contexto do fluxo atual (opcional se for algo novo)
   - **Regras** — comportamentos que o sistema deve ter
   - **Onde** — sistemas e canais afetados (Checkout, Admin, LP…)
   - **Aceite** — cenários e comportamentos esperados
   - **O que não fazer** — limites explícitos (opcional)
   - **Stakeholders** — nomes de pessoas que decidem e aprovam
   - **Como validar** — passo a passo para QA ou PM validar em staging
3. **Trava de escopo amplo:** se o PM descrever um épico inteiro (ex.: "fluxo completo de onboarding"), o assistente recusa e pede que o PM volte com um card mais definido — evitando cards inutilizáveis
4. **Resumo automático da entrevista** extraído e salvo na conversa ao final

### 3. Geração automática de card

Quando a entrevista encerra:
- O botão **"Gerar Card"** aparece no chat
- A IA gera o card em JSON estruturado com todos os campos do framework
- Card salvo no banco com `status = draft`
- PM é redirecionado para a visualização do card pronto

### 4. Visualização e histórico de cards

- Lista de todos os cards gerados
- Visualização detalhada de cada card com todas as seções preenchidas
- Preview do card ainda dentro da conversa

### 5. Rastreio de consumo de tokens e custo

- Cada chamada à LLM registra tokens consumidos, cache e custo em dólar
- Widget de consumo exibido na sessão de chat
- Seletor de modelo de IA (o PM pode escolher o modelo para a conversa)
- Dois provedores no mesmo seletor: modelos **free** via OpenRouter e modelos **OpenAI** (GPT-4o Mini, GPT-4o, GPT-4.1, o4 Mini) em chamada direta
- Custo da OpenAI é **estimado** pela tabela interna de preços (a API não devolve o valor gasto). Custo da OpenRouter vem da própria API — inclusive `0` nos modelos grátis
- Fallback automático para modelo secundário quando há rate limit — **só nos modelos OpenRouter**. Em rate limit da OpenAI, o chat pede para tentar de novo em instantes

### 6. Página de métricas

- Consumo agregado por step (`interview` vs `card_generation`)
- Comparação de desempenho entre versões de prompt
- Visibilidade de custo por modelo

### 7. Histórico de versões do produto

- Página `/versoes` com linha do tempo de todas as entregas
- Busca por versão, módulo, feature ou commit
- Filtro por maturidade (estável, development, test, bug)

---

## 🚦 Estado atual de maturidade

| Área | Estado | Observação |
|---|---|---|
| Autenticação | ✅ Estável | Login, registro, recuperação de senha |
| Chat / Conversa | 🔧 Development | Funcional, sendo ajustado com feedback real |
| Prompt de entrevista | 🔧 Development | v4 ativa — framework do time, trava de épico |
| Geração de card | 🔧 Development | v2 ativa — campos do framework |
| Parser de card | ✅ Estável | Normaliza JSON da LLM confiável |
| Rastreio de tokens | ✅ Estável | Custo e consumo por chamada |
| Métricas | ✅ Estável (UI) / 🔧 Dev (comparação) | Página acessível, filtros em refinamento |
| Histórico de versões | 🔧 Development | Funcional, base para controle de evolução |
| Fallback de LLM | ✅ Estável | Troca automática em rate limit (só OpenRouter) |
| Adapter OpenAI | ✅ Estável | Ativo só com `OPENAI_API_KEY`; ids `gpt-*` / `o4*` |

**Leitura:** "Development" aqui significa que a feature está funcionando, mas ainda vai passar por iterações com base no uso real — não que está quebrada.

---

## Como usar (passo a passo para PMs testadores)

### Pré-requisito
Ter um **épico definido**. O PM Helper trata de um card de cada vez — chegue sabendo em qual épico o card se encaixa.

### Fluxo no produto

1. **Acesse** o PM Helper e faça login com as credenciais que o time vai compartilhar
2. **Clique em "Nova conversa"** (ou "Assistente de Interview")
3. **Descreva o card** que você quer detalhar em linguagem livre, ex:  
   _"Preciso de um card para adicionar validação de CPF no checkout antes de o usuário finalizar a compra"_
4. **Responda as perguntas** do assistente — ele vai guiar fase a fase, nunca sobrecarregando com muitas perguntas de uma vez
5. Quando o assistente fechar o resumo, **clique em "Gerar Card"** (ou peça "gere o card" no chat)
6. **Revise o card gerado** — está estruturado com todas as seções do framework

### Dicas para o teste

- **Seja específico sobre comportamento**, não sobre implementação técnica — o assistente vai te puxar para isso se você escorregar
- Se o assistente rejeitar sua demanda por "escopo amplo", é sinal de que você descreveu um épico: quebre em cards e teste um por vez
- Experimente pedir para pular etapas ("já tenho contexto suficiente, encerra") — o assistente vai encerrar se já tiver o mínimo, ou explicar o que ainda precisa
- O seletor de modelo no chat permite testar com diferentes LLMs — vale experimentar
- Modelos **Free** (Nemotron, Laguna, Ling…) passam pela OpenRouter e não geram custo
- Modelos **OpenAI** são pagos e só funcionam se o ambiente tiver `OPENAI_API_KEY`. Sem a chave, a escolha desses modelos tende a falhar — use um modelo Free nesse caso

---

## ⏳ O que pode esperar — expansões planejadas pós-uso

Estas áreas **não estão no escopo do MVP** e só serão priorizadas com base no que o time sentir falta depois de usar:

### Alta probabilidade de ser pedido

| Item | Por quê vai ser pedido |
|---|---|
| **Editar card gerado** no próprio PM Helper | O card vai sair com lacunas ou termos que o PM vai querer ajustar sem sair da ferramenta |
| **Exportar card** (Jira, Linear, Notion, CSV) | O card precisa ir para onde o time gerencia o backlog |
| **Múltiplos cards por épico** | Hoje o assistente recusa épico — o PM vai querer desmembrar um épico em cards no próprio PM Helper |
| **Comentários e aprovação de card** | Liderança vai querer aprovar ou sinalizar ajustes antes do refinamento |
| **Notificações** (e-mail ou Slack) quando card está pronto | PMs não vão ficar recarregando a ferramenta esperando a geração |

### Média probabilidade

| Item | Por quê |
|---|---|
| **Dashboard de cards por status** | Visibilidade do backlog gerado pelo PM Helper |
| **Retomar entrevista interrompida** | PM sai no meio, conversa fecha — hoje perderia o contexto |
| **Modo dupla** (dois PMs na mesma conversa) | Times maiores onde PM e tech lead fazem discovery juntos |
| **Histórico de versões do card** | Saber o que mudou entre gerações de um mesmo card |
| **Prompt customizável por time** | Squads com framework diferente do padrão |

### Baixa prioridade (mas vai aparecer)

| Item | Por quê |
|---|---|
| **Mobile / PWA** | PMs em reunião presencial querendo registrar no celular |
| **Integração com calendário** para agendar sprint review | Fluxo de refinamento completo dentro da ferramenta |
| **Busca semântica em cards antigos** | Evitar duplicar cards já escritos |
| **Relatório de custo por time/squad** | Quando a ferramenta escalar para vários squads |

---

## Perguntas que o time de produto vai fazer (e as respostas)

**"O card gerado está errado / incompleto. E agora?"**  
Por enquanto, copie o conteúdo e ajuste manualmente no Jira/Notion. A edição direta no PM Helper vem na próxima iteração.

**"Posso usar com mais de um card de uma vez?"**  
Não. Uma conversa = um card. Abra uma nova conversa para cada card. O assistente vai te lembrar disso se você tentar colocar dois cards no mesmo chat.

**"O assistente disse que meu escopo é amplo demais, mas eu acho que cabe em um card."**  
Isso é debate saudável! Anote o caso e traga para o time — pode ser que o critério do assistente precise de calibragem. É exatamente o tipo de feedback que vai melhorar o produto.

**"Posso escolher qual modelo de IA usar?"**  
Sim — o seletor de modelo aparece no canto inferior do chat. Os modelos Free passam pela OpenRouter; GPT-4o Mini, GPT-4o, GPT-4.1 e o4 Mini vão direto para a OpenAI. Os trade-offs de velocidade e custo aparecem nas métricas (`/metrics`).

**"Os dados das entrevistas ficam seguros?"**  
O produto roda na infraestrutura Docker do time, com banco MySQL isolado. O conteúdo da conversa só sai do ambiente nas chamadas à LLM: modelos Free vão para a [OpenRouter](https://openrouter.ai) e modelos GPT/o4 vão direto para a [OpenAI](https://platform.openai.com). Nenhum dos dois treina modelos com esses dados no uso via API. Escolha o provedor no seletor de modelo.

**"Por que o custo da OpenAI aparece como estimado?"**  
A OpenAI devolve só tokens, não o valor em dólar. O PM Helper calcula o custo pela tabela interna (`config/llm.php`). A OpenRouter já manda o custo real — inclusive zero nos modelos grátis. A fatura do provedor pode divergir um pouco se o preço oficial mudar e a tabela atrasar.

---

## Como reportar problemas durante o teste

Por enquanto, use o canal do time no Slack com a tag `#pm-helper-feedback` e inclua:

1. O que você tentou fazer
2. O que aconteceu (com print se possível)
3. Se foi comportamento do assistente (prompt) ou da interface

Isso alimenta diretamente as próximas iterações.

---

## Linha do tempo resumida do produto

```
04/09 — v0.1.0  Primeira versão: chat, cards, Docker, OpenRouter
04/09 — v0.2.0  Rastreio de tokens e custo
05/09 — v0.3.0  Prompts versionados, pipeline interview→card, métricas, fallback LLM
05/09 — v0.4.0  Página de histórico de versões
06/09 — v0.5.0  Framework de cards do time (objetivo, regras, onde, aceite…)
07/09 — v0.5.1  Empty state com orientação de escopo
07/09 — v0.5.2  Prompt v4: recusa épicos com INTERVIEW_SCOPE_TOO_BROAD
07/09 — v0.5.3  Trava de geração quando escopo é amplo
07/09 — v0.5.4  Parser tolerante, selo de escopo atualiza em tempo real
08/09 — v0.6.0  Modelos OpenAI no seletor (chamada direta à API)
08/09 — v0.6.3  Custo estimado nas métricas para chamadas OpenAI
08/09 — v0.6.4  ← atual: docs alinhadas ao adapter OpenAI (README e guia de launch)
```

---

*Documento gerado em 08/09/2026 com base no código e changelog do PM Helper v0.6.4.*
