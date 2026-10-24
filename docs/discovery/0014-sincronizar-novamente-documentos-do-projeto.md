# Discovery: Sincronizar novamente os documentos do projeto

## 1. Resumo

Na lista de projetos do admin, o PM Helper hoje indexa `/docs` (com subpastas) e o `README.md` da raiz apenas no primeiro salvamento do cadastro. Edição não dispara nova leitura. Projetos sem arquivo encontrado ou com falha na leitura ficam com a label de que não há documento sincronizado, sem forma de tentar de novo. A demanda é oferecer um botão por projeto para repetir, sob demanda, a mesma leitura no repositório. Em sucesso, os documentos já sincronizados daquele projeto são apagados e a ingestão do RAG roda de novo só com arquivos `.md`, para não deixar duplicata nem artigo antigo. A restrição a `.md` vale também no primeiro salvamento. Se a leitura falhar, o sistema avisa e pede para tentar mais tarde.

## 2. Objetivo

Permitir que o administrador dispare de novo a sincronização dos arquivos do projeto com a base de documentos, sem precisar cadastrar o projeto outra vez, para cobrir falha na primeira leitura, repositório ainda vazio na época do cadastro, ou documentação que mudou no GitHub — sempre com índice só de `.md` e sem conteúdo antigo daquele projeto.

## 3. Escopo

### Dentro

- Botão na lista de projetos do admin (tela Gerenciar Projetos), por projeto ativo
- Ao acionar com sucesso, apagar todos os documentos já sincronizados daquele projeto e gerar de novo: ler `/docs` com subpastas e o `README.md` da raiz, e ingerir somente arquivos `.md` no fluxo do RAG (gravar nome e conteúdo, fatiar e indexar)
- No primeiro salvamento do cadastro, aplicar a mesma restrição: só arquivos `.md` entram na ingestão
- Feedback de espera na tela, no mesmo espírito do cadastro: a operação precisa terminar com o administrador ainda na tela
- Se a leitura falhar, no botão e no primeiro salvamento: avisar que falhou e que deve tentar novamente mais tarde
- Atualização da label “Sem documento sincronizado” quando a leitura concluir e não houver `.md` para indexar
- Ação restrita a administrador, como o restante da tela

### Fora

- Disparar a sincronização automaticamente ao editar ou ao salvar alterações do projeto
- Sincronização agendada ou periódica
- Leitura de outras pastas além de `/docs` (com subpastas) e do `README.md` da raiz
- Indexar arquivo que não seja `.md`
- Upload manual na tela de Documentos
- Uso desses documentos na entrevista ou na geração de card
- Escrita ou alteração de arquivos no repositório

## Fluxo

1. O administrador, na lista de projetos, aciona a sincronização de um projeto ativo — ou cadastra um projeto novo e confirma o primeiro salvamento.
2. O sistema lê no repositório e na branch já informados a pasta `/docs`, com subpastas, e o `README.md` da raiz, considerando só arquivos `.md`.
3. Se a leitura falhar, o sistema avisa que falhou e pede para tentar novamente mais tarde. Documentos já sincronizados daquele projeto permanecem. No cadastro novo, o projeto fica salvo.
4. Se a leitura concluir: no botão, os documentos já sincronizados daquele projeto são apagados; em seguida, cada `.md` encontrado entra no fluxo de ingestão do RAG (gravar, fatiar, indexar). No primeiro salvamento não há documentos anteriores.
5. Se houver `.md` indexado, a label de ausência some. Se a leitura concluir e não houver `.md`, a label de que não há documento sincronizado aparece ou permanece.
6. Enquanto a operação corre, o administrador permanece na tela até ela terminar.

## 4. Premissas

- A origem da leitura continua sendo o repositório e a branch já gravados no projeto, sem pedir esses dados de novo no clique
- O conjunto de caminhos lidos é o mesmo: `/docs` com subpastas e `README.md` da raiz; só extensão `.md` segue para a ingestão, no botão e no primeiro salvamento
- O acesso ao GitHub continua o do GitHub App já usado, sem autenticação individual do administrador
- A lista em que o botão aparece é a de projetos ativos; projeto desativado não entra nesta demanda
- O botão vale tanto para projeto com a label de ausência quanto para projeto que já tem documento sincronizado
- Sucesso no botão substitui o índice daquele projeto por completo: apaga o que já estava sincronizado e gera tudo de novo, para evitar duplicata e artigo antigo
- Falha da leitura não apaga documentos já sincronizados; o aviso pede nova tentativa mais tarde
- Ausência de `.md` depois de uma leitura que concluiu não é falha: o projeto permanece e a label de que não há documento sincronizado é o desfecho visível
- Falha e ausência de arquivo passam a ter desfechos diferentes: falha avisa e pede nova tentativa; ausência mostra a label

## 5. Considerações de segurança

- Dados sensíveis: o conteúdo dos `.md` de `/docs` e do `README.md` volta a ser gravado na base de documentos e enviado ao provedor de embeddings, como no primeiro salvamento. Documentação interna do repositório entra nesse tráfego. Documentos anteriores daquele projeto saem da base quando a sincronização pelo botão conclui com sucesso
- Autenticação/Autorização: a ação fica na tela já restrita a administrador. A leitura do repositório usa o GitHub App existente
- Exposição de APIs: a leitura ocorre no servidor, disparada pelo administrador. Não há endpoint novo exposto ao cliente
- Compliance: não há requisito novo declarado. Se a documentação do repositório tiver dado pessoal, ela volta a ser persistida e enviada ao provedor de embeddings
- Outras considerações: o token de instalação do GitHub App continua efêmero e não é gravado

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

Substituição completa no sucesso, preservação e aviso na falha, e restrição a `.md` no botão e no primeiro salvamento estão definidos.
