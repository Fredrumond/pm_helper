# Discovery: Referência do documento na resposta do Explore

## 1. Resumo

Quando o Explore responde com base em documentos, a mensagem mostra só o texto gerado. Quem recebeu a resposta não vê de qual documento ela saiu e não tem como conferir a fonte.

A demanda informa a referência do documento nessa resposta, em tooltip, somente com as referências. A referência é o nome do arquivo como foi gravado.

## 2. Objetivo

Permitir que quem recebeu a resposta do Explore confira a fonte, vendo no tooltip o nome do arquivo como foi gravado, sem misturar essa referência ao texto da resposta.

## 3. Escopo

### Dentro

- Na resposta do Explore que veio de documentos, um tooltip com o nome de cada arquivo como foi gravado (`documents.name`: no upload, o nome original; na indexação do repositório, o caminho do arquivo)
- O tooltip lista só as referências, sem o texto do trecho, sem score e sem repetir o conteúdo da resposta
- Se a mesma resposta usou mais de um documento, o tooltip lista cada documento uma vez
- A referência continua visível naquela resposta enquanto ela aparece na exploração, inclusive ao reabrir a conversa
- Resposta que não veio de documento não exibe tooltip de fonte
- Atualizar `docs/rag.md` para registrar que a resposta do Explore informa a referência do documento em tooltip

### Fora

- Embutir a referência no corpo da resposta
- Abrir o arquivo, mostrar o trecho usado ou o score de similaridade
- Tooltip na busca da tela de documentos, que não gera resposta
- Escolher modelo do Explore ou alterar parâmetros de indexação e de busca
- Exibir a fonte para quem não é o destinatário daquela resposta

## Fluxo

1. A pessoa envia uma pergunta no Explore e recebe uma resposta baseada em documentos.
2. Ao apontar para a resposta, vê um tooltip só com o nome de cada arquivo usado, como foi gravado.
3. Se a resposta não veio de documento, não há tooltip de fonte.

## 4. Premissas

- Referência, nesta demanda, é o nome do arquivo como foi gravado em `documents.name`, não um rótulo separado nem o texto do trecho
- O tooltip aparece na resposta do assistente no Explore, para quem já vê essa conversa
- Vários trechos do mesmo documento viram uma única referência
- A referência fica associada à resposta exibida, também depois de recarregar a exploração
- Resposta do fluxo padrão do Explore, sem documentos, não ganha tooltip

## 5. Considerações de segurança

- Dados sensíveis: o tooltip mostra o nome do arquivo gravado usado na resposta. O conteúdo do trecho não entra no tooltip
- Autenticação/Autorização: só quem já acessa a própria exploração vê o tooltip daquela resposta. A demanda não amplia a consulta de documentos para outra pessoa
- Exposição de APIs: não aplicável — não há endpoint novo para fora do produto
- Compliance: não aplicável
- Outras considerações: o nome do documento pode identificar material interno do projeto; a audiência é a mesma de quem já recebeu a resposta. Nenhuma outra identificada

## 6. Dúvidas

Nenhuma dúvida em aberto. A referência ficou definida como o nome do arquivo gravado em `documents.name`.

## 7. Informações ausentes

Nenhuma informação ausente.

## 8. Status

**Implementado**

A resposta do Explore que veio de documentos mostra, em tooltip, o nome de cada arquivo como foi gravado. A referência fica na mensagem e continua visível ao reabrir a exploração.
