# Discovery: Modelo do Explore configurável no painel

## 1. Resumo

O administrador já define, no painel, qual modelo cada prompt da conversa usa. O Explore fica de fora: as respostas usam sempre `gpt-4o-mini`, tanto quando vêm dos documentos quanto quando caem no fluxo padrão do Explore.

A demanda coloca o Explore na mesma configuração do painel. O administrador escolhe o modelo; o valor deixa de ser fixo.

## 2. Objetivo

Permitir que o administrador defina o modelo das respostas do Explore, no mesmo tipo de configuração já usada para os modelos dos prompts da conversa.

## 3. Escopo

### Dentro

- Configuração do modelo do Explore no painel administrativo, restrita a quem já é admin
- A escolha usa a lista de modelos que o painel já oferece para os prompts da conversa
- Um modelo ativo para o Explore, válido para o sistema inteiro
- O modelo escolhido vale nas duas saídas de resposta do Explore: a que usa documentos e a do fluxo padrão
- Até o admin salvar uma troca, o Explore continua em `gpt-4o-mini`
- A configuração vigente vale na próxima resposta, inclusive em exploração já aberta
- Atualizar `docs/rag.md` para registrar que o modelo de resposta do Explore deixa de ser fixo e passa a ser o definido no painel

### Fora

- O usuário do Explore escolher o modelo na conversa
- Configurar modelo por projeto, por exploração ou por usuário
- Alterar o texto ou a versão do prompt do Explore e do prompt de RAG
- Incluir, remover ou editar modelos na lista disponível
- Os parâmetros de indexação e de busca do RAG (tamanho do chunk, modelo de embedding, top-K)
- A referência do documento exibida na resposta

## Fluxo

1. O admin abre a configuração de modelos no painel.
2. Escolhe o modelo do Explore na lista já disponível e salva.
3. Na próxima mensagem do Explore, a resposta usa esse modelo.
4. Se o admin ainda não trocou, a resposta segue em `gpt-4o-mini`.

## 4. Premissas

- A configuração é única para o sistema: um modelo ativo do Explore, para todas as explorações e todos os projetos
- O admin escolhe só entre os modelos que o painel já lista para os prompts da conversa
- Só quem já tem o papel de admin altera; quem usa o Explore não altera
- As duas saídas de resposta do Explore compartilham esse único modelo, como hoje compartilham o valor fixo
- Até a primeira troca salva pelo admin, o padrão permanece `gpt-4o-mini`
- A troca vale na próxima resposta, também em exploração já aberta

## 5. Considerações de segurança

- Dados sensíveis: a configuração trata do identificador do modelo já listado no produto. Chaves de API não fazem parte desta tela
- Autenticação/Autorização: só o admin altera o modelo do Explore. Quem usa o Explore continua autenticado na própria exploração, sem poder trocar o modelo
- Exposição de APIs: não aplicável — não há endpoint novo para fora do produto
- Compliance: não aplicável
- Outras considerações: o conteúdo enviado ao modelo não muda por esta demanda; muda apenas qual modelo o admin definiu para o Explore. Nenhuma outra identificada

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

O admin passa a escolher, no painel, um modelo da lista já existente para o Explore inteiro, no lugar do `gpt-4o-mini` fixo.
