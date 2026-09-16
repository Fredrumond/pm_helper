# Discovery: Prompt de retrieval versionado para `/docs`

## 1. Resumo

Hoje o PM Helper lê **recursivamente todos os arquivos** de `/docs` no encerramento da entrevista e injeta o conteúdo bruto no `card_generation`. Essa abordagem é "tudo ou nada": se o conteúdo ultrapassar 80 k chars, o card é gerado sem `/docs`; abaixo do teto, arquivos irrelevantes (ADRs antigos, histórico, docs de outra área) entram como ruído no prompt. A demanda é introduzir um **step intermediário** — um prompt versionado de retrieval — que recebe o índice de `/docs` mais o resumo da entrevista e decide quais arquivos valem ser lidos, antes de chamar o `card_generation`.

## 2. Objetivo

Versionar separadamente a estratégia de seleção de contexto de `/docs`, permitindo iterar "o que é relevante para o card" de forma independente do prompt de geração, reduzir tokens enviados ao LLM e evitar que o teto de 80 k chars aborte a revisão em repositórios com documentação volumosa.

## 3. Escopo

### Dentro

- Novo prompt `resources/prompts/docs_retrieval/v1.md` com instrução de relevância (entrada: índice de paths + resumo da entrevista; saída: lista de paths selecionados)
- Novo método no port `ProjectDocsGateway` para listar paths disponíveis em `/docs` sem ler o conteúdo (`listDocsPaths`)
- Novo método (ou sobrecarga) para ler somente os paths informados (`readDocsByPaths`)
- Registro no `SystemPromptCatalog` e em `config/chat.php`
- Telemetria: logar paths selecionados, paths descartados e char count após filtro
- Comportamento de fallback: se o retrieval falhar ou retornar lista vazia, seguir com o comportamento atual (dump completo ou empty/too\_large)
- Teto de chars continua aplicado sobre o conteúdo **filtrado**, não sobre a árvore inteira

### Fora

- LLM chamando o MCP direto (model "agent-with-tools") — descartado pelo ADR 0004
- Alteração na interface de seleção de projetos ou na autenticação GitHub App
- Cache persistente do índice de `/docs`
- Outros provedores de docs além do GitHub MCP
- Busca semântica / embeddings
- Versões anteriores dos prompts `card_generation` ou `interview` — permanecem sem alteração

## 4. Premissas

- Feature flag: **Não** — entrega direta sem toggle
- O fluxo é **dois turns determinísticos** (não agente): (1) LLM retrieval recebe índice + resumo e devolve paths; (2) gateway lê só esses paths; (3) `generateCard` usa o subset
- O port `ProjectDocsGateway` é o único ponto que fala com o MCP; o prompt de retrieval **nunca** recebe token ou URL do GitHub
- O índice enviado ao LLM conterá apenas caminhos relativos (ex.: `docs/regras/pagamento.md`), sem conteúdo nem primeira linha dos arquivos
- O teto `GITHUB_MCP_MAX_CHARS` permanece como limite final sobre o conteúdo lido; o retrieval reduz o volume antes de chegar ao teto, mas não o elimina
- Falha no step de retrieval (LLM, timeout, lista vazia inválida) aciona fallback: dump completo com comportamento atual

## 5. Considerações de segurança

- **Dados sensíveis:** nomes de paths de `/docs` serão enviados ao LLM no step de retrieval; se o repositório usar nomes de arquivo com informação sensível, esses nomes vão ao provedor. Risco aceito no Discovery 0003 para o conteúdo; aplica-se igualmente ao índice
- **Autenticação/Autorização:** sem alteração — installation token da GitHub App, mintado por request, nunca persistido (ADR 0004)
- **Exposição de APIs:** nenhum endpoint novo; `listDocsPaths` usa o mesmo `get_file_contents` (diretório raiz) já existente no `GitHubMcpClient`
- **Prompt injection:** o conteúdo dos nomes de arquivo (índice) é dado de usuário indireto; o prompt de retrieval deve pedir saída estruturada (lista de paths) para reduzir superfície de injeção
- **Compliance:** mesmas considerações de LGPD do Discovery 0003 — `/docs` pode conter dados de produto; responsabilidade do time garantir que a pasta seja segura para tráfego externo

## 6. Dúvidas

- **Formato do índice:** ~~só paths, ou paths + primeira linha?~~ **Decisão: só paths** — simples e menos tokens; se a qualidade do retrieval for insuficiente, v2 do prompt adiciona primeira linha
- **Limite de paths selecionados pelo retrieval:** ~~o prompt deve impor um máximo?~~ **Decisão: sim, máximo de 10 arquivos** — instrução explícita no prompt `docs_retrieval/v1.md`
- **Modelo do step de retrieval:** ~~usar o mesmo modelo do card ou um modelo mais barato/rápido?~~ **Decisão: mesmo modelo do card generation** — configurável via `config/chat.php`
- **Saída do retrieval:** ~~lista JSON de paths ou texto livre?~~ **Decisão: JSON estruturado** — mais seguro para parse; parser a implementar no planejamento técnico

## 7. Informações ausentes

- Nenhuma. Todas as dúvidas foram resolvidas; o planejamento técnico pode avançar.

## 8. Status

**Pronto para Planejamento? Sim**

Objetivo, escopo e decisão de arquitetura (dois turns determinísticos, port separado, fallback explícito) estão definidos. As dúvidas em aberto são detalhes de formato e configuração que podem ser resolvidos durante o planejamento técnico.
