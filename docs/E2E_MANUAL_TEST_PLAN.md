# Plano de teste e2e manual — fechamento do MVP

> **Objetivo:** percorrer o produto como **admin** e como **PM**, achar fricção e decidir o que entra no pós-MVP.
> **Referência:** [`MVP_LAUNCH_GUIDE.md`](./MVP_LAUNCH_GUIDE.md) · [`POS_MVP_ROADMAP.md`](./POS_MVP_ROADMAP.md)
> **Como anotar:** para cada passo, marque OK / Falha / Melhoria. No fim, copie a seção [Registro](#registro-do-teste) para o Slack `#pm-helper-feedback`.

---

## 0. Antes de começar

### Integração das branches (bloqueante)

Hoje o código está partido:

| Branch | O que tem |
|---|---|
| `main` | Explorar, Documentos, índice/RAG, sync, painel do índice |
| `develop` | Discovery no card aprovado + responder dúvidas |

**Antes do e2e completo:** juntar as duas linhas (ex.: merge `main` → `develop` ou release com os dois) e subir um ambiente com tudo.

Sem o merge: rode só a parte que a branch cobre e marque o resto como *bloqueado*.

### Ambiente

- [ ] `docker compose up` (app + db + nginx)
- [ ] Worker da fila rodando (`QUEUE_CONNECTION=database`):  
      `docker compose exec app php artisan queue:work`
- [ ] Chaves no `.env`: OpenRouter (e/ou OpenAI), GitHub App, `CURSOR_API_KEY`
- [ ] Conta Cursor da chave com acesso ao repositório de teste
- [ ] Dois usuários: um **admin**, um **product_manager** (e, se possível, um 2º PM para teste de isolamento)

### Projeto de teste

Use um repo real com `/docs` (ex.: o próprio `pm_helper` ou `rapidtask`). Anote: nome amigável, `owner/repo`, branch.

---

## 1. Visão Admin

Tempo estimado: 20–30 min.

| # | Passo | Esperado | OK / Falha / Melhoria |
|---|---|---|---|
| A1 | Login como admin | Entra; vê Conversas, Cards e menus admin | |
| A2 | Cadastrar projeto (nome, repo, branch) | Salva; se Explorar/RAG estiver no ar, indexa `/docs` + README | |
| A3 | Sincronizar de novo o projeto | Índice substituído; mensagem clara de sucesso/erro | |
| A4 | Abrir painel do índice (chunk, corte, embedding, top-K, score, pastas) | Valores editáveis; troca de embedding avisa reindex | |
| A5 | Modelos dos prompts (`interview`, `docs_*`, `card_generation`, `explore`) | Só modelos ativos; PM não escolhe modelo no chat | |
| A6 | Catálogo LLM: ativar/inativar e preço de modelo pago | Salva; métricas usam preço quando a API não manda `cost` | |
| A7 | Desativar um projeto | Some da lista ativa; PM não consegue vincular em conversa nova | |
| A8 | Tentar cadastrar de novo o mesmo `repository` desativado | Comportamento atual (trava ou erro) — anotar se dói | |
| A9 | Métricas | Consumo por step, prompts, modelos; embedding/Explore se houver | |
| A10 | `/versoes` | Changelog legível | |
| A11 | Login como PM e tentar URLs admin | Bloqueado / redirect | |

**O que observar (melhoria / rever depois)**  
Lista inchada de conversas vazias do seu próprio uso? Desativar projeto sem reativar? Sync lento ou sem feedback? Preço estimado vs real? Admin sem ver cards/Discovery de outros?

---

## 2. Visão PM — fluxo feliz (qualificação → card → Discovery)

Tempo estimado: 40–60 min (+ tempo do agente no Discovery).

| # | Passo | Esperado | OK / Falha / Melhoria |
|---|---|---|---|
| P1 | Login como PM | Sem menus admin; vê Conversas e Cards | |
| P2 | Nova conversa + vincular projeto **antes** da 1ª mensagem | Projeto trava depois da 1ª msg | |
| P3 | Descrever **um** card claro (comportamento, não implementação) | Entrevista guia as fases | |
| P4 | Completar até o resumo | `INTERVIEW_SUMMARY`; briefing de `/docs` se o projeto tiver docs | |
| P5 | Gerar Card | Draft com seções do framework; projeto visível no card | |
| P6 | Revisar lista de cards e abrir o card | Conteúdo coerente com a entrevista | |
| P7 | Aprovar | Status `approved` | |
| P8 | Gerar Discovery | Estado solicitado → em andamento; pode sair da tela | |
| P9 | Aguardar conclusão (worker + agente) | Anexo renderizado; sem HTML/script cru | |
| P10 | Responder ≥1 dúvida da seção 6 | Campos editáveis | |
| P11 | Gerar de novo com respostas | Novo ciclo; documento atualizado; respostas limpas no sucesso | |
| P12 | Explorar: pergunta sobre o projeto | Resposta com tooltip do arquivo (se RAG no ar) | |

**Demanda sugerida (exemplo)**  
_"Preciso de um card para avisar o usuário quando ele criar uma tarefa muito parecida com outra já existente."_  
(Ajuste ao domínio do repo de teste.)

---

## 3. Visão PM — casos de borda (curto)

Tempo estimado: 20–30 min.

| # | Passo | Esperado | Anotar |
|---|---|---|---|
| B1 | Nova conversa sem enviar mensagem; voltar à lista | Conversa vazia na lista? | |
| B2 | Excluir conversa que já tem card | Hard delete? some card e custo? | |
| B3 | Demanda de **vários** cards (épico) | Recusa / escopo amplo; sem Gerar Card | |
| B4 | Entrevista **sem** projeto | Segue; sem briefing de repo; sem Gerar Discovery depois | |
| B5 | Fechar o browser após o briefing, **antes** de Gerar Card; reabrir | Mensagens voltam; bloco `/docs` da sessão pode ter sumido | |
| B6 | Card draft: tentar Discovery | Botão ausente / bloqueado | |
| B7 | Outro PM tenta abrir/gerar Discovery do seu card | Negado | |
| B8 | Documentos: upload `.md` sem projeto + Explorar sem projeto | Resposta usa esse doc (se RAG no ar) | |
| B9 | Gerar Discovery de novo sem respostas | Substitui anexo; estados corretos | |
| B10 | Forçar falha (repo sem acesso / chave inválida temporária) | Status falhou; mensagem útil; anterior preservado se houver | |

---

## 4. O que procurar (checklist de feedback)

Marque o que doeu de verdade. Isso alimenta o pós-MVP — não invente prioridade antes do teste.

### Fricção de produto (Trilha A)

- [ ] Conversa vazia polui a lista
- [ ] Excluir conversa apaga histórico/custo demais (falta soft delete)
- [ ] Projeto desativado sem reativar
- [ ] Não dá para editar o card
- [ ] Não dá para exportar (Jira/Linear/Notion/CSV)
- [ ] Épico recusado sem caminho de desmembrar
- [ ] Falta comentário / discussão no card
- [ ] Falta notificação quando o Discovery termina (fora da tela)
- [ ] Falta dashboard por status

### Qualidade do conteúdo

- [ ] Entrevista longa / perguntas ruins / fases repetidas
- [ ] Trava de épico falsa (card único recusado) ou frouxa
- [ ] Briefing de `/docs` irrelevante ou vazio
- [ ] Card genérico, técnico demais ou incompleto
- [ ] Discovery raso, inventa código, ou dúvidas ruins
- [ ] Regenerar com respostas não incorpora a resposta

### Contexto / Explorar (Trilha B)

- [ ] Explorar e entrevista “não batem” (índice vs leitura ao vivo)
- [ ] Sync esquecido → resposta velha
- [ ] Sem selo quando card saiu sem contexto
- [ ] Latência ou custo alto no fechamento da entrevista

### Operação / admin

- [ ] Worker/fila: Discovery fica preso em “em andamento”
- [ ] Métricas confusas ou custo 0 em modelo pago
- [ ] Papéis: PM vê o que não deveria / admin não acha o que precisa

---

## 5. Critério para fechar o MVP e “começar a testar de verdade”

Considere o MVP **fechado para uso** quando:

1. Admin consegue cadastrar projeto, (se aplicável) sincronizar índice e deixar prompts/modelos ok.
2. PM completa **uma** demanda ponta a ponta: entrevista → card → aprovar → Discovery concluído (e, se houver dúvida, regenerar com resposta).
3. Explorar responde algo útil no projeto de teste (após merge com RAG).
4. Os itens da seção 4 estão anotados — mesmo que a resposta seja “não doeu”.
5. Bugs **bloqueantes** (não gera card, Discovery nunca conclui, vazamento entre usuários) estão corrigidos ou explicitamente aceitos com workaround.

Depois disso: uso real com PMs + `#pm-helper-feedback`. Código novo só com dor confirmada — ver ordem em [`POS_MVP_ROADMAP.md`](./POS_MVP_ROADMAP.md#6-próximos-passos-imediatos).

---

## Registro do teste

```
Data:
Branch / commit:
Ambiente (URL):
Quem testou (admin / PM):
Projeto usado:

Fluxo feliz: OK / parcial / falhou
Discovery: OK / parcial / falhou / bloqueado
Explorar: OK / parcial / falhou / bloqueado

Top 3 melhorias (agora):
1.
2.
3.

Top 3 para rever depois:
1.
2.
3.

Bugs bloqueantes:
-
```
