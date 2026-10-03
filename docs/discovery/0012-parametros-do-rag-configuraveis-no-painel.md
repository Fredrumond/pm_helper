# Discovery: Parâmetros do RAG configuráveis no painel

## 1. Resumo

O RAG indexa documentos e recupera trechos com três valores fixos: chunk de 500 caracteres, embedding `text-embedding-3-small` e os 3 trechos mais próximos (top-K). Não há tela para o administrador alterar esses parâmetros, ao contrário do que já existe para o modelo de cada prompt.

A demanda leva tamanho do chunk, modelo de embedding e top-K para configuração no painel, para que deixem de ser fixos.

## 2. Objetivo

Permitir que o administrador customize tamanho do chunk, modelo de embedding e quantidade de trechos recuperados, sem depender dos valores fixos atuais.

## 3. Escopo

### Dentro

- Configuração no painel administrativo, restrita a quem já é admin, dos três parâmetros: tamanho do chunk, modelo de embedding e top-K
- O modelo de embedding é escolhido na lista de modelos já cadastrados no catálogo do painel
- Até o admin salvar uma troca, permanecem os valores atuais: 500 caracteres, `text-embedding-3-small` e top-K 3
- A configuração é do sistema inteiro
- Tamanho do chunk e modelo de embedding passam a valer só quando o documento for indexado de novo. Salvar a troca não reindexa o que já está no índice
- Ao salvar troca de tamanho do chunk ou de modelo de embedding, o painel exibe um aviso de que os documentos já indexados precisam ser reindexados para a busca usar o valor novo
- O top-K salvo vale na próxima recuperação de trechos, na resposta do Explore com documentos e na busca da tela de documentos
- Atualizar `docs/rag.md` para registrar que esses três parâmetros deixam de ser fixos e passam a ser os definidos no painel

### Fora

- O modelo de resposta do Explore (`gpt-4o-mini` ou o que o admin definir para o Explore)
- O texto e a versão dos prompts
- Troca de vector store, chunk por parágrafo ou score mínimo de similaridade
- A referência do documento exibida na resposta
- Quem usa o Explore ou a tela de documentos alterar esses parâmetros
- Reindexar documentos automaticamente ao salvar a configuração
- Mínimo e máximo para tamanho do chunk e para o top-K
- O admin informar um identificador de modelo de embedding fora do catálogo do painel

## Fluxo

1. O admin abre a configuração dos parâmetros do RAG no painel.
2. Ajusta tamanho do chunk, escolhe o modelo de embedding na lista do catálogo e define o top-K.
3. Salva.
4. Se o tamanho do chunk ou o modelo de embedding mudou, o painel informa que os documentos já indexados precisam ser reindexados. Os trechos atuais permanecem como estão.
5. Na próxima indexação, chunk e embedding usam os valores salvos.
6. Na próxima busca, o top-K salvo limita quantos trechos voltam.

## 4. Premissas

- A configuração é única para o sistema, no mesmo espírito da configuração de modelo por prompt: o admin define, todo o produto usa
- Só quem já tem o papel de admin altera
- Até a primeira troca salva, os padrões continuam 500 caracteres, `text-embedding-3-small` e top-K 3
- O modelo de embedding sai do catálogo de modelos que o painel já administra
- O top-K configurado vale na recuperação de trechos que hoje usa esse limite: a resposta do Explore com documentos e a busca da tela de documentos
- Tamanho do chunk e modelo de embedding valem na indexação. Documentos já indexados só mudam quando forem embeddados de novo
- Salvar tamanho ou modelo de embedding não dispara reindexação. O aviso no painel é o que comunica essa pendência
- Por agora a tela aceita o número informado para tamanho do chunk e para o top-K, sem faixa mínima ou máxima

## 5. Considerações de segurança

- Dados sensíveis: a configuração trata de tamanho de trecho, identificador do modelo de embedding e quantidade de resultados. Chaves de API e o conteúdo dos documentos não fazem parte desta tela
- Autenticação/Autorização: só o admin altera os parâmetros. Quem consulta documentos ou o Explore não altera
- Exposição de APIs: não aplicável — não há endpoint novo para fora do produto
- Compliance: não aplicável
- Outras considerações: nenhuma identificada além do acesso já restrito ao admin

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Pronto para Planejamento?** Sim

O admin define no painel tamanho do chunk, modelo de embedding (da lista do catálogo) e top-K, com os padrões atuais até a primeira troca. Trocar tamanho ou modelo de embedding não reindexa na hora: o painel avisa que a reindexação fica pendente, e o valor novo entra só no próximo embedding. O top-K vale na busca seguinte. Sem mínimo nem máximo por agora.
