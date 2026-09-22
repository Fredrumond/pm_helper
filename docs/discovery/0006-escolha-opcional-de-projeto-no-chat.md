# Discovery: Escolha opcional de projeto no chat

## 1. Resumo

Hoje o PM escolhe o projeto numa tela própria, acessada pelo item **Projetos** do menu. A escolha vale para a sessão. A entrevista e o card podem seguir sem projeto; quando há projeto, a conversa usa o contexto daquele repositório. O card gerado não indica qual projeto foi usado.

A demanda extingue essa tela e tira o item **Projetos** do menu. **Gerenciar Projetos** permanece. A escolha, quando o PM quiser, passa a ser feita no chat, na barra do compositor, ao lado do seletor de modelo, e fica gravada na conversa. Vale só para a conversa aberta. Cada conversa nova começa sem projeto. Depois que a conversa começa, o projeto não muda. Sem projeto, a conversa continua. Com projeto, o card exibe uma tag com o nome amigável da conversa, no mesmo lugar da tag de prioridade.

## 2. Objetivo

Permitir que o PM trabalhe na conversa sem passar por uma tela de projeto e, quando escolher um, deixar visível no card qual projeto aquela conversa usou.

## 3. Escopo

### Dentro

- Remover o item **Projetos** do menu superior, no desktop e no menu recolhido
- Extinguir a tela de escolha de projeto da sessão e qualquer caminho que ainda leve a ela
- Manter o item **Gerenciar Projetos** para o administrador
- Escolha do projeto no chat da conversa, na barra do compositor, ao lado do seletor de modelo, antes de a conversa começar
- A escolha vale só para a conversa aberta. Uma conversa nova começa sem projeto e pede a escolha de novo, se o PM quiser
- Depois que a conversa começa, o projeto fica travado
- A referência do projeto fica gravada na conversa. O card não guarda uma cópia própria
- Projeto opcional: a entrevista e a geração do card seguem sem projeto
- Com projeto, o card exibe uma tag com o nome amigável da conversa, no mesmo lugar da tag de prioridade, na listagem e no cabeçalho do card
- Sem projeto, o card não exibe essa tag

### Fora

- Tornar o projeto obrigatório
- Remover **Gerenciar Projetos**
- Trocar ou limpar o projeto depois que a conversa começou
- Manter a escolha por sessão ou a tela antiga de projetos
- Mudança na regra de uso do contexto do repositório: com projeto, a conversa usa esse contexto; sem projeto, segue sem ele
- Exibir ao PM o identificador técnico do repositório (`owner/repo`)
- Mencionar o projeto no texto gerado do card
- Gravar a referência do projeto no card, além da conversa

## Fluxo

1. O PM abre uma conversa nova. Nenhum projeto vem selecionado.
2. Se quiser, escolhe um projeto na barra do compositor, ao lado do modelo, antes de enviar a primeira mensagem. Se não quiser, segue sem seleção.
3. A conversa começa no primeiro envio. A partir daí o projeto não muda.
4. A entrevista continua. Com projeto, a conversa usa o contexto daquele repositório. Sem projeto, segue sem ele.
5. Ao gerar o card, uma tag com o nome amigável aparece junto da prioridade. Sem projeto, essa tag não aparece.

## 4. Premissas

- **Gerenciar Projetos** permanece no menu. A demanda remove só a escolha feita pelo PM na tela **Projetos**.
- A tela **Projetos** e a regra “a seleção vale só para esta sessão” deixam de existir. Não fica endereço antigo escolhendo projeto para a sessão.
- A lista oferecida no chat é a de projetos ativos, pelo nome amigável, a mesma que o PM já vê hoje.
- A conversa começa quando o PM envia a primeira mensagem. Até lá a escolha pode ser feita ou deixada em branco.
- A referência do projeto fica na conversa, porque a escolha é feita e travada antes de existir card, e a entrevista já usa esse projeto. Cada conversa tem um único card. A tag só exibe o nome amigável da conversa.
- Sem nenhum projeto ativo, o PM ainda conduz a conversa e gera o card sem a tag.

## 5. Considerações de segurança

- Dados sensíveis: o seletor e a tag mostram o nome amigável do projeto a usuário já autenticado, como a tela atual. A associação fica gravada na conversa. Não há dado pessoal novo.
- Autenticação/Autorização: a escolha continua restrita a usuário autenticado e a projetos ativos já cadastrados. O cadastro de projetos segue restrito ao administrador.
- Exposição de APIs: Não aplicável
- Compliance: Não aplicável
- Outras considerações: Nenhuma identificada

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

Menu, extinção da tela antiga, escolha por conversa, trava após o início, tag no card e referência gravada na conversa estão definidos.
