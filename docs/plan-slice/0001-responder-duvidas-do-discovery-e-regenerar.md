# Plano (fatias): Responder às dúvidas do Discovery e regenerar com as respostas

Discovery: docs/discovery/0011-responder-duvidas-do-discovery-e-regenerar.md

## 1. Objetivo

Fechar o ciclo do Discovery no card: o dono responde às dúvidas da seção de dúvidas do documento anexado e pede uma nova geração. O agente recebe o card, o Discovery anterior e cada resposta ligada à sua dúvida, e o documento atualizado substitui o anterior.

## 2. Estratégia

Reusar o fluxo da 0010 (`CardDiscovery`, `CardDiscoveryService`, `DiscoveryPromptBuilder`, `CardDiscoveryPanel`). Extrair as dúvidas do Markdown já salvo, persistir as respostas no mesmo registro do card enquanto a geração não concluir com sucesso, e montar um pedido novo (sem continuar sessão no provedor). A ação "Gerar de novo" sem respostas permanece a da 0010.

## 3. Caminho escolhido

Estender o registro único `card_discoveries` com um JSON de respostas pendentes, em vez de tabela nova ou histórico. Um parser lê só a seção `## 6. Dúvidas` do documento da skill (um item de lista por dúvida). O `DiscoveryPromptBuilder` ganha o bloco opcional (Discovery anterior + pares dúvida/resposta) sem afrouxar o OVERRIDE de somente leitura. A UI fica no `CardDiscoveryPanel` já existente: campo por dúvida na leitura do anexo e botão "Gerar de novo com respostas".

Referência de padrão: não encontrado.

## 4. Considerações de segurança

### Dados sensíveis
As respostas do PM vão no pedido do agente junto com o card e o Discovery anterior — o mesmo tipo de dado que a 0010 já envia. Logs desta atividade registram ids, status e motivo, não o corpo das respostas.

### Autenticação e autorização
Responder e disparar a regeneração com respostas ficam restritos ao dono do card (mesmo `authorizeOwner` / `user_id` da 0010). Admin que não é dono não responde nem dispara.

### Surface de ataque
Sem endpoint HTTP novo. Livewire no painel do card. Respostas são texto livre: entram só no bloco de contexto do prompt, depois do OVERRIDE de somente leitura, e não podem alterar regras do pedido (sem editar repositório, sem gravar no repo). Continuar exibindo o Markdown com HTML stripped (`DiscoveryMarkdown::toHtml`). Não injetar os campos de resposta no HTML renderizado do documento.

### Compliance
Não aplicável além do já registrado na 0010 (envio de conteúdo do card e do Discovery ao provedor).

### Logs e auditoria
Reusar os eventos da 0010 (`card_discovery.requested`, `forbidden`, `completed`, `failed`). No `requested` da regeneração com respostas, incluir um indicador de que o pedido levou respostas (quantidade, não o texto).

## 5. Estratégia de rollout

Sem feature flag. A ação nova só aparece quando o card já tem Discovery com documento legível e ao menos uma resposta preenchida. "Gerar de novo" sem respostas continua disponível como hoje. Enquanto a geração estiver em andamento, não é possível responder nem disparar outra.

## 6. Observabilidade

### Métricas de negócio
Regeneração com respostas solicitada; regeneração concluída; regeneração falhou (acompanhar pelos logs existentes + indicador de respostas).

### Métricas técnicas
As mesmas da 0010: timeout, resultado vazio, falha do provedor. Sem painel novo.

### Alertas de segurança
Tentativa de responder ou regenerar por quem não é dono: log `card_discovery.forbidden` (já existe).

### Logs
`card_discovery.requested` (com indicador de respostas), `completed`, `failed`, `forbidden`. Acompanhar em `storage/logs/laravel.log`.

### Dashboards
Não aplicável.

## 7. Fatias de execução

| Fatia | Depende de | Status | Commit |
|-------|-----------|--------|--------|
| 1 — Extrair dúvidas e persistir respostas | — | concluída | 9c76948 |
| 2 — Regenerar com respostas no pedido do agente | 1 | concluída | 6bf3ec5 |
| 3 — Campos de resposta e ação no painel do card | 2 | concluída | 04cf842 |

### Fatia 1 — Extrair dúvidas e persistir respostas

#### Contexto mínimo
O documento gerado pela skill tem a seção `## 6. Dúvidas` com um item de lista por dúvida (formato `- pergunta — por que importa`). O produto precisa ler essas dúvidas do Markdown já salvo e guardar as respostas no registro único do card até a geração concluir com sucesso.

#### Dentro
- Parser que devolve a lista de textos de dúvida da seção 6 (ignorar "Informações ausentes"; se a seção disser que não há dúvida em aberto, lista vazia)
- Coluna JSON no registro `card_discoveries` para as respostas pendentes (pares dúvida + resposta)
- Model/factory/casts atualizados para ler e gravar esses pares

#### Fora
- Disparo do agente, mudança de prompt, UI, limpeza no sucesso/falha da geração

#### Dependências
- Nenhuma. Assume a 0010 entregue: `card_discoveries` existe, o Markdown vive no disco `discoveries` e é lido pelo painel.

#### Fronteira
- Pode alterar: `app/Support/` (parser), `app/Models/CardDiscovery.php`, `database/factories/CardDiscoveryFactory.php`, nova migration, testes unitários do parser e do model
- Não alterar: `CardDiscoveryService`, `DiscoveryPromptBuilder`, `CardDiscoveryPanel`, job, adapters de agente, views

#### Contrato de saída
- Dado o Markdown de um Discovery da skill, a lista de dúvidas da seção 6 é obtida sem renderizar HTML
- `CardDiscovery` persiste e relê um array de pares `{question, answer}` em coluna JSON (nome a cargo desta fatia; sugestão: `pending_answers`)
- Documento no disco e estados da 0010 permanecem iguais

#### Ordem de implementação
1. Migration com a coluna JSON nullable em `card_discoveries`
2. Cast/fillable no model e factory
3. Parser da seção `## 6. Dúvidas` (itens `- `; descartar item único do tipo "Nenhuma dúvida em aberto")
4. Testes do parser e da persistência no model

#### Testes desta fatia
- Seção 6 com dois itens → duas dúvidas; texto da pergunta extraído (antes do `—`, se houver)
- Seção 6 = "Nenhuma dúvida em aberto" → lista vazia
- Seção 7 (Informações ausentes) não entra na lista
- Markdown sem seção 6 → lista vazia
- Model grava e relê os pares no JSON

#### Restrições globais a respeitar
- Segurança: parser só lê texto; não passa o Markdown por HTML
- Rollout/flag: nenhuma
- Observabilidade desta fatia: nenhuma

#### Pronto quando
- Migration aplicável no ambiente local
- Testes do parser e do model passando
- Nenhum pedido ao agente e nenhuma view alterados

### Fatia 2 — Regenerar com respostas no pedido do agente

#### Contexto mínimo
A 0010 já dispara geração nova sem respostas. Esta fatia adiciona o caminho em que o pedido leva o card, o Markdown anterior e os pares dúvida/resposta. Cada geração continua sendo um start novo no provedor. As respostas ficam no registro até o sucesso; na falha elas permanecem.

#### Dentro
- `DiscoveryPromptBuilder` inclui, quando houver, o Discovery anterior e os pares dúvida/resposta depois do OVERRIDE de somente leitura
- `CardDiscoveryService` aceita as respostas no disparo: exige dono, card aprovado, projeto, ao menos uma resposta não vazia, documento anterior legível, e nenhuma geração em andamento
- Gravar as respostas no JSON da Fatia 1 antes de despachar o job
- No sucesso (`store`), limpar o JSON; na falha (`markFailed`), manter o JSON e o documento anterior

#### Fora
- UI, parser novo, segundo provedor, histórico de versões, continuar a mesma sessão no Cursor

#### Dependências
- Fatia 1 deve ter deixado: parser da seção 6; coluna JSON de pares `{question, answer}` no `CardDiscovery`

#### Fronteira
- Pode alterar: `app/Services/CardDiscoveryService.php`, `app/Services/Agents/DiscoveryPromptBuilder.php`, testes de serviço/prompt já existentes nesses paths
- Não alterar: `CardDiscoveryPanel`, views Livewire, adapters, job além do que o serviço já despacha, schema além do campo já criado

#### Contrato de saída
- `request` (ou método irmão no mesmo serviço) com respostas válidas: persiste os pares, status `requested`, job `AdvanceCardDiscovery` despachado
- Prompt da regeneração contém título do card, Markdown anterior, cada dúvida com sua resposta, skill e OVERRIDE de somente leitura (o OVERRIDE aparece antes do texto das respostas)
- Sem respostas / lista vazia / só whitespace → não dispara por este caminho (a 0010 `request` sem respostas continua igual)
- Sucesso → JSON de respostas vazio/nulo; falha → JSON intacto e `document_path` anterior intacto
- Não-dono → 403 e log `card_discovery.forbidden`
- Em andamento → `CardDiscoveryNotAllowed`, sem segundo job

#### Ordem de implementação
1. Estender o builder: `build(Card $card, ?string $previousMarkdown = null, array $answers = [])` (ou equivalente)
2. Estender o serviço para persistir respostas, montar o prompt com o Markdown do disco e disparar
3. Limpar respostas só em `store`; não limpar em `markFailed`
4. Testes de prompt e de serviço

#### Testes desta fatia
- Prompt com respostas: contém cada par, o Markdown anterior, o card e o OVERRIDE; OVERRIDE ocorre antes das respostas
- Prompt sem respostas: igual ao da 0010 (sem regressão)
- Respostas no prompt não removem "Somente leitura" / "NÃO faça commit"
- Disparo com uma resposta → `requested` + JSON gravado + 1 job
- Disparo sem resposta / só vazia → não grava nem despacha por este caminho
- Não-dono → 403
- Já em andamento → `CardDiscoveryNotAllowed`
- Sucesso do `advance` → documento novo no mesmo path e JSON limpo
- Falha/timeout/vazio → JSON e documento anterior permanecem

#### Restrições globais a respeitar
- Segurança: respostas só no bloco de contexto; OVERRIDE intacto; logs sem corpo das respostas; dono-only
- Rollout/flag: nenhuma; "Gerar de novo" sem respostas não envia o JSON
- Observabilidade desta fatia: `card_discovery.requested` com indicador de quantidade de respostas

#### Pronto quando
- Testes acima passando (`docker compose exec app composer test` nos arquivos desta fatia)
- Regeneração com respostas exercitável pelo serviço, sem UI
- `request` sem respostas da 0010 inalterado

### Fatia 3 — Campos de resposta e ação no painel do card

#### Contexto mínimo
O dono já lê o Discovery renderizado no `CardDiscoveryPanel`. Falta um campo por dúvida extraída e o botão "Gerar de novo com respostas", usando o contrato da Fatia 2. Em andamento, campos e disparos ficam bloqueados.

#### Dentro
- Na leitura aberta do anexo, cada dúvida da seção 6 com um campo de resposta (texto)
- Prefill a partir do JSON persistido (caso de falha anterior)
- Botão "Gerar de novo com respostas" só para o dono, só com documento visível e ao menos uma resposta preenchida
- Chamar o serviço da Fatia 2; em andamento, não responder nem disparar
- Manter "Gerar de novo" / "Gerar Discovery" da 0010

#### Fora
- Responder Informações ausentes, conversa livre, edição do Markdown, notificação fora do card, campos para quem não é dono

#### Dependências
- Fatia 2 deve ter deixado: serviço que persiste pares, dispara regeneração e limpa no sucesso; prompt com card + documento anterior + respostas; 0010 `generate` sem respostas intacto

#### Fronteira
- Pode alterar: `app/Livewire/CardDiscoveryPanel.php`, `resources/views/livewire/card-discovery-panel.blade.php`, `tests/Feature/Livewire/CardDiscoveryPanelTest.php`
- Não alterar: serviço/prompt além de chamá-los, schema, adapters, parser (só usar)

#### Contrato de saída
- Documento aberto + dúvidas na seção 6 → um campo por dúvida
- Sem dúvidas → sem campos e sem o botão novo
- Uma ou mais respostas não vazias → botão "Gerar de novo com respostas" visível
- Clique no botão novo → mesmo ciclo visual da 0010 (solicitado/em andamento → concluído ou falhou)
- Falha → documento anterior e respostas ainda no formulário
- Em andamento → campos desabilitados; nenhum dos dois botões de gerar
- "Gerar de novo" sem respostas continua o `generate` atual

#### Ordem de implementação
1. Carregar dúvidas via parser e respostas via JSON no `render`
2. Estado Livewire dos campos; prefill do JSON
3. Ação que dispara o serviço da Fatia 2 quando houver ao menos uma resposta
4. Desabilitar campos e botões enquanto `isInProgress()`
5. Testes Livewire

#### Testes desta fatia
- Dono + documento concluído com duas dúvidas → vê dois campos; sem resposta → não vê o botão novo
- Uma resposta preenchida → vê o botão novo; clique → 1 job e status `requested`
- Falha com JSON preenchido → campos voltam com o texto
- Em andamento → não dispara (nem o botão novo nem o da 0010)
- Não-dono não monta o painel (403 já coberto na 0010; não regressar)
- "Gerar de novo" sem preencher campos → `request` da 0010, sem enviar respostas

#### Restrições globais a respeitar
- Segurança: dono-only; campos fora do `{!! $documentHtml !!}`; HTML do documento continua stripped
- Rollout/flag: botão novo só com resposta preenchida
- Observabilidade desta fatia: nenhuma extra (o serviço já loga)

#### Pronto quando
- Testes Livewire acima passando
- Fluxo manual no card: abrir anexo, responder uma dúvida, gerar de novo, ver andamento; em falha, respostas ainda lá
- Ação da 0010 sem respostas inalterada

## 8. Pendências

- nenhuma

## Como executar

1. Abra o arquivo deste plano em `docs/plan-slice/`.
2. Na tabela da seção 7, a **próxima fatia pendente** é a primeira com Status `pendente` cujas dependências (coluna Depende de) já estão `concluída` (ou `—`).
3. Execute **somente** essa fatia (contexto = bloco da fatia + contrato da anterior em Dependências).
4. Só avance quando o **Pronto quando** da fatia atual for verdadeiro.
5. Ao terminar, o **executor** atualiza a tabela: Status → `concluída` e Commit → hash (ou referência) do commit da fatia.
6. Não antecipar o trabalho das fatias seguintes.
