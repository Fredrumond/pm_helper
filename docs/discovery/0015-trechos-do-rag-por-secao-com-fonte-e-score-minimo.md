# Discovery: Trechos do RAG por seção do documento, com fonte e score mínimo

## 1. Resumo

O RAG divide cada documento em fatias de tamanho fixo. O corte ignora frases, parágrafos e seções, e pode partir caracteres acentuados ao meio. Os documentos do produto seguem os templates das skills (Discovery, ADR, Plan…), com seções numeradas em Markdown, mas um trecho recuperado não diz de qual documento nem de qual seção veio. O contexto enviado ao modelo do Explore junta os trechos sem identificar a origem, e qualquer trecho entra na resposta, mesmo com similaridade baixa.

A demanda faz o corte respeitar a estrutura do documento, identifica a origem de cada trecho na indexação e na resposta, e deixa o admin definir uma similaridade mínima para um trecho ser usado.

## 2. Objetivo

Melhorar a qualidade das respostas do Explore com documentos: trechos com sentido completo, que carregam a sua origem, e sem trechos irrelevantes no contexto.

## 3. Escopo

### Dentro

- Nova configuração no painel dos parâmetros do RAG: **estratégia de corte**, com três opções:
  - **Por seção do Markdown:** corta nos títulos de seção do documento. Seção maior que o tamanho do chunk é dividida por parágrafo. Seção curta, com menos de 20% do tamanho do chunk (200 caracteres num chunk de 1000), é juntada à seção seguinte; se for a última do documento, à anterior. A junção não passa do tamanho do chunk
  - **Por parágrafo:** agrupa parágrafos inteiros até o tamanho do chunk
  - **Por tamanho:** o corte atual, por quantidade de caracteres
- Nas estratégias por seção e por parágrafo, o tamanho do chunk passa a ser o limite máximo do trecho, não o ponto exato de corte
- Em qualquer estratégia, o corte não parte um caractere ao meio (acentos e cedilha permanecem íntegros)
- Cada trecho indexado carrega a sua origem: título do documento, nome do arquivo e, quando houver, o caminho de seções (ex.: `3. Escopo › Fora`). A origem participa da busca por similaridade
- No contexto enviado ao modelo do Explore, cada trecho aparece identificado pelo arquivo e pela seção de onde veio. O comportamento muda, então o prompt do RAG ganha uma nova versão
- Nova configuração no painel: **score mínimo** de similaridade. Trecho abaixo do score mínimo não é recuperado. O campo traz a sugestão de 0,30 como ponto de partida
- Na tela, a estratégia de corte vem primeiro. Nas estratégias por seção e por parágrafo, o campo de tamanho aparece como tamanho máximo do trecho
- O score mínimo vale na resposta do Explore com documentos e na busca da tela de documentos
- Se nenhum trecho atingir o score mínimo no Explore, a resposta segue o fluxo padrão do Explore, como já acontece quando não há trechos
- Se nenhum trecho atingir o score mínimo na tela de documentos, a tela informa que não encontrou trechos
- Trocar a estratégia de corte não reindexa: o painel exibe o mesmo aviso de reindexação pendente que já aparece ao trocar tamanho do chunk ou modelo de embedding
- Atualizar `docs/rag.md` com as estratégias de corte, a origem do trecho e o score mínimo, e retirar das limitações o corte por caractere e a ausência de score mínimo

### Fora

- Sobreposição (overlap) entre trechos
- Usar mensagens anteriores da conversa na busca
- Busca híbrida (palavra-chave combinada com similaridade)
- Troca de vector store
- Reindexar automaticamente ao salvar a configuração
- Exibir a seção ou o score no tooltip de referência do Explore
- Estratégias de corte diferentes por projeto ou por documento
- Mínimo e máximo para tamanho do chunk e top-K

## Fluxo

1. O admin escolhe a estratégia de corte e o score mínimo no painel e salva.
2. Se a estratégia mudou, o painel avisa que os documentos já indexados precisam ser reindexados.
3. Na próxima indexação (upload ou nova sincronização do projeto), o documento é cortado pela estratégia salva e cada trecho grava a sua origem.
4. Numa busca, os trechos abaixo do score mínimo são descartados e, dos restantes, voltam os top-K mais similares.
5. No Explore, o modelo recebe os trechos identificados por arquivo e seção. Se nenhum trecho passou, a resposta segue o fluxo padrão do Explore.

## 4. Premissas

- A configuração continua única para o sistema e restrita ao admin, como os demais parâmetros do RAG
- Até o admin salvar uma troca, a estratégia é **por tamanho** e o score mínimo é **0** (sem filtro). O comportamento atual se mantém, exceto pelo corte que deixa de partir caracteres
- Score mínimo é um número entre 0 e 1
- A sugestão de 0,30 vale para o `text-embedding-3-small`: abaixo disso os trechos tendem a não ter relação com a pergunta. É ponto de partida, a calibrar com perguntas reais na tela de documentos, que mostra o score de cada trecho
- O limite de seção curta acompanha o tamanho do chunk, sem parâmetro próprio no painel
- Os documentos indexados são Markdown. Documento sem títulos de seção, na estratégia por seção, é cortado por parágrafo
- O score mínimo vale na busca seguinte, sem reindexação
- A reindexação usa os caminhos que já existem: novo upload do arquivo ou nova sincronização dos documentos do projeto
- A referência exibida no tooltip do Explore continua sendo o nome do arquivo

## 5. Considerações de segurança

- Dados sensíveis: não aplicável. A origem gravada no trecho é título, nome do arquivo e seção, dados que já estão no documento indexado
- Autenticação/Autorização: só o admin altera estratégia de corte e score mínimo, como os demais parâmetros do RAG
- Exposição de APIs: não aplicável. Não há endpoint novo
- Compliance: não aplicável
- Outras considerações: nenhuma identificada

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Implementado**

Estratégias de corte (seção, parágrafo e tamanho), origem do trecho, score mínimo e prompt `rag/v2` estão no painel, na indexação e na busca.
