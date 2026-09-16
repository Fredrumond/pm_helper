**Data:** 16/09/2026

# ADR 0004 — Ler `/docs` do projeto via MCP GitHub

## Contexto

Com o projeto selecionado (`CurrentProject`), o card precisa respeitar as regras do repositório. O [Discovery 0002](../discovery/0002-cadastro-projetos-github-mcp.md) definiu GitHub App e um port de MCP; o [Discovery 0003](../discovery/0003-acesso-regras-projetos-no-chat.md) definiu *quando* ler (`/docs`, no encerramento da entrevista). Faltava decidir *como* o servidor fala com o GitHub e onde o texto vive até `generateCard`. O [ADR 0001](0001-desacoplar-llm-com-ports-e-adapters.md) cobre só a LLM.

## Opções consideradas

1. **GitHub REST** (`/repos/{owner}/{repo}/contents`) — HTTP familiar; o app reimplementa listagem recursiva, MIME e teto.
2. **Clone ou cache local do repo** — leitura barata depois do fetch; disco, sync e superfície maior que o escopo.
3. **MCP oficial (Streamable HTTP) + port `ProjectDocsGateway`** — `get_file_contents` com toolset `repos` e `X-MCP-Readonly`; installation token só em memória; resultado na sessão, não no MySQL.

## Decisão

Adotar a opção 3.

- **Port:** `App\Contracts\ProjectDocsGateway` (`readProjectDocs`).
- **Adapter:** `GitHubMcpProjectDocsGateway` + `GitHubMcpClient` em `config('mcp.github.url')`.
- **Auth:** installation token da GitHub App (Discovery 0002), mintado por request, nunca persistido nem logado.
- **Contrato com o chat:** payload `project_docs.{conversationId}` na sessão; `content` só em status `ok`. `generateCard` não relê o GitHub.
- **Teto:** abortar a leitura (não truncar) e notificar o PM.

Leitura de outras pastas, escrita no repo e outros provedores de docs ficam fora. Novo consumidor de `/docs` usa o port, não o MCP direto.

## Justificativa

O MCP já entrega listing + conteúdo com sessão readonly; a REST empurraria a mesma recursão para o app. Port próprio evita misturar GitHub em `LlmGateway`. Sessão atende o Discovery (sem auditoria/histórico) e some com o logout. Abortar no teto impede contexto LLM silencioso e incompleto.

## Consequências

### Benefícios

- Chat e testes de fluxo fakeiam o port; o protocolo MCP fica no adapter.
- Mesmo padrão do 0001 (contrato + adapter), domínio separado.
- Token não entra em banco, log nem sessão.

### Riscos

- Protocolo MCP (SSE/JSON-RPC) é mais opaco que REST; regressão de path/`max_chars` exige teste no gateway.
- Caminhada recursiva herda timeout por chamada, não teto total da árvore.
- Conteúdo de `/docs` vai ao provedor de LLM (risco aceito no Discovery 0003).

### Débitos técnicos

- Sem allowlist rígida de host além do env `GITHUB_MCP_URL`.
- `GitHubMcpClient` concentra HTTP, SSE e heurísticas (`resource_link` → `too_large`).

### Próximos passos

- Testes do gateway: `invalid_repo`, path fora de `/docs`, abort em `max_chars`, short-circuit sem credenciais.
- Outro provedor de docs: novo adapter no binding de `ProjectDocsGateway`.
