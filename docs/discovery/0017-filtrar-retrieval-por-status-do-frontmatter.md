# Discovery: Filtrar retrieval por status do frontmatter

## 1. Resumo

Os documentos indexados no RAG podem conter um bloco de frontmatter (delimitado por `---`) com campos como `id`, `entity`, `status`, `verified_at` e `source_discovery`. O campo `status` indica se aquela regra ou definição está vigente (`current`), superada (`deprecated`), em rascunho (`draft`), entre outros valores possíveis. Hoje o indexador ingere o conteúdo completo do arquivo — incluindo o bloco frontmatter — sem interpretar esses campos. O retrieval por similaridade pode, portanto, retornar chunks de documentos não-vigentes mesmo quando existe uma versão `current` do mesmo conceito. A demanda é: extrair o `status` do frontmatter durante a indexação, persistir esse valor no `Document`, e filtrar no retrieval para que apenas documentos com `status: current` (ou sem frontmatter) sejam considerados como candidatos.

## 2. Objetivo

Garantir que o retrieval retorne apenas chunks de documentos vigentes, eliminando ruído de regras superadas ou rascunhos, e produzir embeddings mais limpos ao separar metadados de conteúdo.

## 3. Escopo

### Dentro

- Extração do bloco frontmatter (`---…---`) do início do conteúdo durante a indexação
- Parse do campo `status` do frontmatter (valor string)
- Nova coluna `status` na tabela `documents` (nullable — documentos sem frontmatter ou sem o campo `status` ficam com `null`)
- Strip do bloco frontmatter antes de passar o conteúdo ao chunker, para que o embedding reflita apenas o conteúdo real
- Filtro no `DocumentSearch`: passam no retrieval documentos com `status IN ('current', 'stable')` **ou** `status IS NULL` (ausência de frontmatter ou de campo `status` equivale a `stable`)
- Documentos com qualquer outro valor de status (ex.: `deprecated`, `draft`) são excluídos do retrieval

### Fora

- Outros campos do frontmatter (`id`, `entity`, `verified_at`, `source_discovery`) — não são persistidos nesta entrega
- Configuração da whitelist de status pelo admin (fixo em código nesta entrega)
- Reindexação automática de documentos já existentes — a mudança vale na próxima sincronização manual de cada projeto
- Validação do valor de `status` contra um enum fixo no momento da indexação

## Fluxo

1. Durante a sincronização, o `ProjectDocumentsIndexer` recebe o conteúdo bruto do arquivo.
2. Um parser extrai o bloco frontmatter (se presente) e retorna: o `status` parseado e o conteúdo sem o bloco `---`.
3. O `Document` é criado com `status` preenchido (ou `null`) e `content` já sem o frontmatter.
4. O chunker processa o conteúdo limpo, gerando embeddings do texto real.
5. No retrieval, o `DocumentSearch` filtra chunks descartando documentos onde `status IS NOT NULL AND status != 'current'`.
6. O ranking por cosine similarity ocorre apenas sobre os chunks restantes.

## 4. Premissas

- O frontmatter, quando presente, está sempre no início do arquivo e delimitado por `---` na primeira e na última linha do bloco
- Documentos sem frontmatter **ou** com frontmatter mas sem o campo `status` são tratados como `stable` — passam no filtro (comportamento atual preservado)
- A reindexação dos documentos existentes é manual; até lá, documentos já indexados permanecem com `status = null` e continuam sendo retornados normalmente
- A whitelist de status que passa no retrieval é `current` e `stable`; qualquer outro valor resulta em exclusão
- O corpus de documentos está começando a adotar o campo `status` agora — a grande maioria dos arquivos existentes não terá o campo e continuará funcionando como antes

## 5. Considerações de segurança

- Dados sensíveis: Não aplicável
- Autenticação/Autorização: Não aplicável — o filtro opera server-side, sem nova superfície de acesso
- Exposição de APIs: Não aplicável
- Compliance: Não aplicável
- Outras considerações: Nenhuma identificada

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente que bloqueie o planejamento.

## 8. Status

**Pronto para Planejamento?** Sim

Escopo, premissas e regras do filtro estão definidos; ausência de frontmatter ou de campo `status` equivale a `stable` e passa no retrieval.
