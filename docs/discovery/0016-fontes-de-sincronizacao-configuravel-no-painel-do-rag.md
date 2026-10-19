# Discovery: Fontes de sincronização configuráveis no painel do RAG

## 1. Resumo

Hoje a sincronização de cada projeto lê sempre as mesmas origens: o arquivo `README.md` da raiz e toda a pasta `docs/` com subpastas — comportamento definido em `GitHubMcpProjectDocsGateway`. O PM Helper passou a usar a metodologia [skills-metodologia](https://github.com/Fredrumond/skills-metodologia), que cria subpastas dentro de `docs/` para artefatos de processo: `docs/discovery/` (documentos de Discovery), `docs/adr/` (decisões de arquitetura), `docs/references/` (referências técnicas), entre outras. Nem todos esses artefatos são bons candidatos para a base de conhecimento do RAG — documentos de Discovery, por exemplo, descrevem o processo de levantamento e não a regra de negócio ou a documentação técnica do produto. A demanda é permitir que o administrador configure, no painel de parâmetros do RAG, quais caminhos (arquivos ou pastas) do repositório entram na sincronização, em vez de deixar isso fixo no código.

## 2. Objetivo

Dar ao administrador controle sobre quais fontes do repositório são indexadas para o RAG, permitindo excluir artefatos de processo que poluem a base de conhecimento ou incluir caminhos adicionais relevantes — sem alterar o comportamento atual para projetos que ainda não configuraram nada.

## 3. Escopo

### Dentro

- Nova seção no painel de parâmetros do RAG ("Fontes de sincronização") com uma lista fixa de checkboxes — um por fonte conhecida
- Fontes da lista, na ordem de exibição:
  - `README.md` — arquivo de apresentação do projeto na raiz
  - `docs/` — todos os arquivos Markdown fora das subpastas listadas abaixo
  - `docs/adr/` — decisões de arquitetura
  - `docs/discovery/` — documentos de Discovery
  - `docs/references/` — referências técnicas
  - `docs/rules/` — regras do projeto
- Padrão: todas as fontes marcadas (reproduz o comportamento atual de README + `docs` recursivo)
- Quando o admin salva, os caminhos marcados são gravados no `rag_settings` como JSON
- Na próxima sincronização de qualquer projeto, o `ProjectDocumentsIndexer` usa os caminhos gravados em vez dos dois hardcoded
- `docs/` genérico inclui os arquivos `.md` diretamente na raiz de `docs/` mas **não** os das subpastas listadas acima (que têm checkbox próprio). Isso evita contar conteúdo em duplicata quando uma subpasta também está marcada
- Se nenhuma fonte estiver marcada, o comportamento é o padrão (tudo incluído), para evitar deixar projetos sem conteúdo por acidente
- Painel exibe aviso de que a troca vale na próxima sincronização manual de cada projeto — o mesmo padrão dos outros parâmetros que exigem reindexação
- Persistência em `rag_settings`, junto com os demais parâmetros do RAG

### Fora

- Configuração por projeto (a fonte é global, como os demais parâmetros do RAG)
- Listas de exclusão (negação de caminhos); o campo é de inclusão
- Re-sincronização automática ao salvar as fontes
- Caminhos customizados fora da lista fixa
- Validação de existência do caminho no repositório (caminho ausente resulta em zero arquivos — o gateway já trata esse caso silenciosamente)
- Suporte a glob ou wildcard nos caminhos

## Fluxo

1. O admin, no painel de parâmetros do RAG, marca ou desmarca as fontes desejadas e salva.
2. Os caminhos das fontes marcadas são gravados no `rag_settings` como JSON.
3. O painel exibe o aviso de que a mudança vale na próxima sincronização manual de cada projeto.
4. Na próxima sincronização de qualquer projeto (botão "Sincronizar" ou primeiro salvamento do cadastro), o `ProjectDocumentsIndexer` lê os caminhos do `RagConfig` e passa essa lista ao gateway; o gateway lê cada caminho — arquivo ou pasta recursiva — filtrando apenas `.md`, como hoje.
5. Se nenhuma fonte estiver marcada, o comportamento é o padrão: `README.md` + `docs` completo.

## 4. Premissas

- O padrão (todas as fontes marcadas) reproduz o comportamento atual; projetos existentes não mudam até o admin desmarcar algo
- `docs/` genérico cobre apenas os `.md` na raiz de `docs/`, não as subpastas com checkbox próprio — isso evita duplicata quando subpasta e `docs/` genérico estiverem ambos marcados
- Se nenhuma fonte estiver marcada, o fallback é o padrão completo (`README.md` + `docs` recursivo), para evitar deixar projetos sem conteúdo indexado por engano
- Caminhos ausentes no repositório resultam em zero arquivos para aquela fonte — comportamento já existente no gateway

## 5. Considerações de segurança

- Dados sensíveis: as fontes marcadas determinam o que é lido do repositório e enviado ao provedor de embeddings; o admin precisa ter ciência disso ao incluir pastas com conteúdo sensível
- Autenticação/Autorização: a configuração fica na tela restrita a administrador, como os demais parâmetros do RAG
- Exposição de APIs: não há endpoint novo; a leitura continua server-side pelo GitHub App
- Compliance: não há requisito novo; vale a mesma observação dos demais parâmetros — conteúdo dos `.md` é enviado ao provedor de embeddings
- Outras considerações: a lista fixa de caminhos no servidor elimina o risco de path traversal — o admin não digita caminhos livres

## 6. Dúvidas

Nenhuma dúvida em aberto.

## 7. Informações ausentes

Nenhuma informação ausente que bloqueie o planejamento.

## 8. Status

**Pronto para Planejamento?** Sim

Objetivo, escopo, fluxo e premissas estão definidos; a validação e o contrato com o gateway existente estão claros.
