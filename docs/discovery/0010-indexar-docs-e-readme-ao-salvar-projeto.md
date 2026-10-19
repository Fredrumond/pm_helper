# Discovery: Indexar docs e README do repositório ao salvar o projeto

## 1. Resumo

Ao cadastrar um projeto e confirmar com Salvar pela primeira vez, o PM Helper lê no repositório — pelo MCP do GitHub — a pasta `/docs`, com todas as subpastas, e o `README.md` da raiz. Qualquer arquivo encontrado vira um documento do projeto e segue o mesmo fluxo do upload: conteúdo gravado, fatiado e indexado. Se nada for encontrado, ou se a leitura falhar, o projeto é salvo mesmo assim e exibe uma label de que não há documento sincronizado. Editar o projeto depois não dispara nova indexação. No Explore, o projeto é opcional, como na conversa: com projeto, entram os documentos daquele projeto; sem projeto, a busca usa só documentos que não têm projeto.

## 2. Objetivo

Trazer a documentação do repositório para a base de documentos no primeiro salvamento do projeto, vinculada a esse projeto, e fazer o Explore buscar os documentos do projeto quando houver um, ou os documentos sem projeto quando não houver.

## 3. Escopo

### Dentro

- Disparo somente no primeiro salvamento do cadastro do projeto
- Leitura da pasta `/docs`, incluindo todas as subpastas, e do arquivo `README.md` na raiz, via MCP do GitHub
- Qualquer arquivo encontrado nessa leitura vira um documento e segue o fluxo já existente de upload: gravar nome e conteúdo, fatiar e indexar
- A tabela de documentos referencia o projeto de origem desses arquivos
- Se nenhum arquivo for encontrado, ou se a leitura no GitHub falhar, o projeto é salvo normalmente e passa a exibir uma label informando que não há documento sincronizado
- No Explore, referência opcional de projeto, no mesmo espírito da conversa: pode haver projeto ou não
- Com projeto no Explore, a busca usa os documentos daquele projeto
- Sem projeto no Explore, a busca usa os documentos que não têm projeto

### Fora

- Indexação ao editar um projeto já cadastrado
- Upload manual de documento pela tela de Documentos (permanece sem projeto)
- Leitura de outras pastas ou arquivos além de `/docs` (com subpastas) e do `README.md` da raiz
- Escrita ou alteração de arquivos no repositório
- Uso desses documentos na entrevista ou na geração de card
- A leitura de `/docs` já feita no encerramento da entrevista, que não grava documento
- Tornar o projeto obrigatório no Explore

## Fluxo

1. O admin salva um projeto novo.
2. O sistema busca no repositório a pasta `/docs`, com todas as subpastas, e o `README.md` da raiz.
3. Cada arquivo encontrado é gravado como documento daquele projeto e indexado como no upload.
4. Se nenhum arquivo for encontrado, ou se a leitura falhar, o projeto fica salvo e exibe a label de que não há documento sincronizado.
5. Um salvamento posterior, de edição, não busca nem indexa de novo.
6. No Explore, o PM pode referenciar um projeto ou seguir sem um. Com projeto, a busca usa os documentos daquele projeto. Sem projeto, a busca usa os documentos que não têm projeto.

## 4. Premissas

- O repositório e o branch já informados no cadastro do projeto são a origem da leitura
- O acesso ao GitHub continua o do GitHub App já usado pelo MCP, sem autenticação individual do PM
- Um arquivo lido corresponde a um documento, no mesmo formato do upload (nome do arquivo e conteúdo)
- A indexação ocorre uma vez, no primeiro salvamento. Edição não atualiza, não substitui e não recria esses documentos
- Documento criado pelo upload manual não recebe projeto
- A label de ausência vale tanto quando a leitura não encontra arquivo quanto quando a leitura falha. Ela não aparece quando há documento sincronizado
- A escolha de projeto no Explore é opcional. Com projeto, a busca fica nos documentos desse projeto. Sem projeto, a busca fica nos documentos sem projeto, como o upload manual
- A leitura no encerramento da entrevista não muda nesta demanda

## 5. Considerações de segurança

- Dados sensíveis: o conteúdo de `/docs` e do `README.md` passa a ficar gravado na base de documentos e segue para o provedor de embeddings, como no upload. No Explore com projeto, esse conteúdo também segue para o LLM, restrito aos documentos do projeto referenciado. Documentação interna do repositório entra nesse tráfego
- Autenticação/Autorização: o salvamento de projeto já é restrito ao admin. A leitura do repositório usa o GitHub App existente. A referência de projeto no Explore continua restrita a usuário autenticado e a projetos ativos
- Exposição de APIs: a leitura ocorre no servidor, no primeiro salvamento do projeto. Não há endpoint novo exposto ao cliente
- Compliance: não há requisito novo declarado. Se a documentação do repositório tiver dado pessoal, ela passa a ser persistida e enviada ao provedor de embeddings e, no Explore com projeto, ao LLM
- Outras considerações: o token de instalação do GitHub App continua efêmero e não é gravado

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

Falha técnica e ausência de arquivo têm o mesmo desfecho, e o Explore sem projeto busca só documentos sem projeto. Escopo, vínculo e momento da indexação estão definidos.
