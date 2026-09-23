# Discovery: Modelo de cada prompt definido pelo admin

## 1. Resumo

Hoje o PM escolhe o modelo no compositor do chat. Essa escolha vale para a entrevista e para a geração do card na sessão. Os passos `docs_retrieval` e `docs_briefing` podem usar outro modelo, definido por variável de ambiente; se não houver, caem no modelo que o PM escolheu.

A demanda tira essa escolha do PM. O administrador passa a definir, para cada um dos quatro prompts, qual modelo da lista já disponível o sistema usa. A configuração é do sistema inteiro e entra no MVP.

## 2. Objetivo

Garantir que entrevista, retrieval de docs, briefing e geração de card usem o modelo que o admin escolheu, sem o PM decidir o modelo no chat.

## 3. Escopo

### Dentro

- Configuração pelo administrador, restrita a quem já é admin, com os quatro prompts: `interview`, `docs_retrieval`, `docs_briefing` e `card_generation`
- Em cada prompt, a lista dos modelos que o sistema já oferece e um modelo ativo escolhido nessa lista
- Cada prompt pode usar um modelo diferente; o mesmo modelo pode valer para mais de um prompt
- O PM deixa de escolher modelo: o seletor sai do compositor do chat
- As próximas chamadas de cada prompt usam o modelo ativo definido pelo admin, inclusive em conversas já abertas
- Entra no MVP

### Fora

- O PM continuar escolhendo o modelo, mesmo só para leitura do nome
- Incluir, remover ou editar modelos na lista disponível
- Configurar modelo por projeto, por conversa ou por PM
- O prompt legado `discovery`
- A escolha de projeto no chat
- O texto e a versão de cada prompt
- A reserva de modelo quando o provedor limita ou devolve resposta vazia
- A mensagem que o PM vê quando a chamada ao modelo falha

## Fluxo

1. O admin abre a configuração dos prompts.
2. Para cada prompt, vê os modelos disponíveis e marca o que deve ser usado.
3. Salva.
4. O PM conversa e gera o card sem escolher modelo.
5. Cada passo usa o modelo que o admin deixou ativo naquele prompt.

## 4. Premissas

- A configuração é única para o sistema: um modelo ativo por prompt, para todas as conversas e todos os projetos
- A lista de modelos continua sendo a que o produto já oferece no compositor; o admin só escolhe dentro dela
- Só quem já tem o papel de admin altera; o PM não altera
- Até o admin salvar uma troca, cada prompt segue com o modelo que o sistema já usa hoje como padrão daquele passo
- A configuração vigente vale na próxima chamada, também em conversa já aberta; a escolha antiga do PM na sessão deixa de valer
- Dois prompts podem apontar para o mesmo modelo
- Entra no MVP, junto com o que já está previsto para o lançamento
- A escolha de projeto no compositor permanece

## 5. Considerações de segurança

- Dados sensíveis: a configuração trata do identificador do modelo já listado no produto. Chaves de API não fazem parte desta tela
- Autenticação/Autorização: só o admin altera o modelo de cada prompt. O PM autenticado continua usando o chat, sem poder trocar o modelo
- Exposição de APIs: não aplicável — não há endpoint novo para fora do produto
- Compliance: não aplicável
- Outras considerações: o conteúdo enviado ao modelo não muda; muda apenas quem escolhe o destino. Nenhuma outra identificada

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

O admin define um modelo da lista atual para cada um dos quatro prompts, o PM deixa de escolher, e a regra vale para o sistema inteiro no MVP.
