# Discovery: Higiene de conversas e reativação de projeto

## 1. Resumo

No fechamento do MVP, três atritos da lista “em aberto” do guia de lançamento atrapalham o uso diário: cada clique em **Nova conversa** cria outro registro vazio na lista; **excluir** a conversa apaga de verdade o card e o histórico de custo; e **desativar** um projeto por engano impede cadastrar de novo o mesmo repositório, sem ação na tela para reativar o registro já soft-deleted.

Esta demanda cobre os três ajustes juntos: reaproveitar conversa de entrevista vazia do próprio PM, exclusão lógica da conversa (some da UI junto com o card; só o custo fica nas métricas) e reativação de projeto desativado pelo admin na mesma lista.

## 2. Objetivo

Reduzir lixo na lista de conversas, fazer a exclusão sumir conversa e card de toda a UI sem desfazer (mantendo só o custo nas métricas), e permitir ao admin reativar projeto desativado na mesma lista sem burlar a unicidade do repositório.

## 3. Escopo

### Dentro

- **Reusar conversa vazia (entrevista):** ao pedir **Nova conversa**, se o PM já tiver uma conversa de entrevista sem mensagens, abrir essa em vez de criar outra
- **Exclusão lógica de conversa (entrevista):** excluir some a conversa de **toda** a UI (lista, show, links a partir do card); não há desfazer
- Mensagens, card e registros de custo permanecem no banco; **só as métricas de custo** seguem visíveis nas telas de métricas/uso
- O card da conversa excluída **não** aparece mais na lista nem no preview de cards
- **Reativar projeto desativado:** na **mesma lista** de projetos do admin, o desativado aparece com indicação visual e botão **Reativar**; ao reativar, o repositório volta a poder ser escolhido nas conversas
- Autorização atual se mantém: só o dono exclui/reusa a própria conversa; só admin desativa/reativa projeto

### Fora

- Exclusão lógica ou reuso automático de conversas **Explorar**
- Restaurar (desfazer exclusão) de conversa — em nenhuma tela
- Manter card ou conversa excluída visíveis em qualquer lista/preview do produto (exceto custo agregado em métricas)
- Exclusão física definitiva (purge) de conversa, card ou custo
- Exclusão em massa, arquivamento com outro nome, ou pastas/tags de conversa
- Cadastro de um segundo projeto com o mesmo `owner/repo` (a unicidade, inclusive com desativado, permanece)
- Seção ou filtro separado só para projetos desativados (fica na mesma lista)
- Alterar o fluxo de sync de documentos além do necessário para o projeto voltar a ativo
- Editar card, exportar, ou outros itens “em aberto” do guia de lançamento

## Fluxo

### Nova conversa

1. PM clica em **Nova conversa**.
2. Se existir conversa de entrevista dele sem mensagens → abre essa.
3. Senão → cria uma nova e abre.

### Excluir conversa

1. PM dono confirma exclusão da conversa de entrevista.
2. Conversa e card somem de toda a UI (lista, show, preview, links); não há desfazer.
3. No banco, mensagens, card e custo permanecem; nas telas de métricas o custo continua contando.

### Reativar projeto

1. Admin vê ativos e desativados na mesma lista; o desativado tem indicação visual e botão **Reativar**.
2. Admin usa **Reativar**.
3. O projeto volta a ativo e pode ser vinculado em novas conversas; o `owner/repo` continua único.

## 4. Premissas

- “Conversa vazia” = conversa de entrevista do usuário autenticado **sem nenhuma mensagem** (projeto já selecionado ou não não importa)
- Se houver mais de uma vazia (legado), reusa a mais recente
- Projeto já usa exclusão lógica (`deleted_at`); “desativar” = soft delete e “reativar” = restore desse registro
- Conversa hoje é hard delete; a demanda introduz exclusão lógica equivalente para conversa de entrevista
- Conversas e cards excluídos logicamente **não aparecem em nada** na UI do produto (lista, show, preview, links); não há ação de desfazer
- O vínculo conversa ↔ card ↔ custo permanece no banco só para retenção e para as **métricas** continuarem corretas
- Projetos desativados ficam na **mesma lista** dos ativos, com indicação visual e botão **Reativar**
- A mensagem atual de conflito de repositório (“edite o registro existente”) passa a fazer sentido com a reativação na mesma tela

## 5. Considerações de segurança

- Dados sensíveis: conversa, card e uso LLM ficam no banco após exclusão lógica, mas **não** são expostos de novo na UI de conversas/cards; o que permanece visível é o custo nas métricas
- Autenticação/Autorização: reuso e exclusão só para o dono da conversa; reativação só para admin (mesmo gate da desativação)
- Exposição de APIs: não aplicável — ações ficam nas rotas/telas já autenticadas do produto
- Compliance: exclusão lógica não é “direito ao esquecimento”; hard delete/purge fica fora desta demanda
- Outras considerações: nenhuma inventada além do acima

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

Escopo fechado: exclusão some conversa e card de toda a UI (sem desfazer; métricas de custo preservadas) e projetos desativados na mesma lista com botão Reativar.
