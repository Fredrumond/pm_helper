<?php

/*
|--------------------------------------------------------------------------
| Histórico de versões
|--------------------------------------------------------------------------
|
| Releases em ordem decrescente: o primeiro item é a versão atual.
| Agrupe os commits do git log (hash, data e mensagem) e descreva o que
| entrou de fato no sistema. Ao fechar uma entrega, adicione um bloco
| no topo com os commits desde a versão anterior.
|
| Os estados indicam a maturidade de cada item, não do release inteiro.
| Não use classes CSS aqui: o Tailwind só varre resources/views.
|
*/

return [

    'estados' => [
        'stable' => 'Estável',
        'development' => 'Development',
        'test' => 'Test',
        'bug' => 'Bug',
    ],

    /*
    |--------------------------------------------------------------------------
    | Evolução dos prompts
    |--------------------------------------------------------------------------
    |
    | Cada entry documenta o que mudou de uma versão para a próxima.
    | Ordem cronológica crescente dentro de cada step (v1 primeiro).
    | Nunca edite uma versão já registrada; adicione sempre no final do array
    | do step correspondente.
    |
    */
    'prompts' => [

        'interview' => [
            [
                'versao' => 'v1',
                'data' => '2026-09-05',
                'release' => '0.3.0',
                'resumo' => 'Primeira versão do prompt de entrevista. Framework clássico de product discovery (problema, persona, contexto, impacto, critérios de sucesso e aceite, fora do escopo, restrições técnicas). A entrevista era dividida em três fases, e a emissão das tags de encerramento só ocorria após o PM confirmar explicitamente o resumo em uma mensagem separada.',
                'mudancas' => [],
            ],
            [
                'versao' => 'v2',
                'data' => '2026-09-05',
                'release' => '0.3.0',
                'resumo' => 'Mantém o mesmo framework do v1, mas remove a espera de confirmação do PM: o resumo e as tags de encerramento passam a ser emitidos na mesma mensagem. Aceita pedido de pular etapas quando o contexto já é suficiente, em vez de insistir nas fases.',
                'mudancas' => [
                    'Fase de validação unificada: resumo e tags INTERVIEW_COMPLETE emitidos na mesma mensagem, sem aguardar confirmação do PM',
                    'Comportamento ao pular etapas: se o PM pedir para avançar e já houver contexto suficiente, a entrevista encerra imediatamente',
                ],
            ],
            [
                'versao' => 'v3',
                'data' => '2026-09-06',
                'release' => '0.5.0',
                'resumo' => 'Adota o framework de cards do time no lugar do framework clássico. O fluxo passa a cobrir Objetivo, Como funciona hoje, Regras, Onde, Aceite, O que não fazer, Stakeholders e Como validar — cinco fases no lugar de três. O INTERVIEW_SUMMARY passa a usar os campos do novo framework. Adiciona bloco de Regras do Framework com diretrizes explícitas de comportamento vs implementação.',
                'mudancas' => [
                    'Framework substituído: sai user story + critérios de aceite, entra Objetivo / Como funciona hoje / Regras / Onde / Aceite / O que não fazer / Stakeholders / Como validar',
                    'Cinco fases no lugar de três (novo: Regras e onde, Aceite e limites, Pessoas e validação)',
                    'INTERVIEW_SUMMARY atualizado com os novos campos do framework',
                    'Bloco "Regras do Framework" adicionado: comportamento ≠ implementação; testável por colega de produto; dúvidas técnicas e decisões em aberto registradas explicitamente',
                    'Stakeholders passam a exigir nomes de pessoas, nunca times ou áreas genéricas',
                    '"Como funciona hoje" e "O que não fazer" marcados como opcionais com critério explícito',
                ],
            ],
            [
                'versao' => 'v4',
                'data' => '2026-09-07',
                'release' => '0.5.2',
                'resumo' => 'Adiciona trava de escopo amplo: quando a demanda equivale a vários cards, o modelo recusa a conduzir a entrevista, emite a tag INTERVIEW_SCOPE_TOO_BROAD e pede que o PM retorne com um card definido. INTERVIEW_COMPLETE e INTERVIEW_SUMMARY ficam bloqueados enquanto o escopo permanecer amplo. O caminho feliz (único card) permanece idêntico ao v3.',
                'mudancas' => [
                    'Nova seção "Escopo: um card por entrevista" com critério de escopo amplo e protocolo de rejeição',
                    'Tag INTERVIEW_SCOPE_TOO_BROAD emitida quando a demanda cobre vários cards; impede qualquer encerramento feliz',
                    'Comportamento ao insistir: o modelo repete a recomendação e não avança para geração',
                    'Fase 5 de encerramento passa a ter guarda explícita: tags de entrevista pronta só para escopo de um card',
                ],
            ],
        ],

        'card_generation' => [
            [
                'versao' => 'v1',
                'data' => '2026-09-05',
                'release' => '0.3.0',
                'resumo' => 'Primeira versão do prompt de geração de card. Recebe o INTERVIEW_SUMMARY e gera JSON com os campos clássicos: title, type, user_story, context, acceptance_criteria, out_of_scope, technical_notes, priority, labels e estimated_complexity.',
                'mudancas' => [],
            ],
            [
                'versao' => 'v2',
                'data' => '2026-09-06',
                'release' => '0.5.0',
                'resumo' => 'Espelha a mudança de framework do interview/v3. O JSON de saída adota os campos do framework do time: objetivo, como_funciona_hoje, regras, onde, aceite, o_que_nao_fazer, stakeholders e como_validar. Campos clássicos (user_story, type, labels, estimated_complexity) são removidos. Adiciona as mesmas regras de comportamento vs implementação do prompt de entrevista.',
                'mudancas' => [
                    'JSON de saída substituído: sai user_story / context / acceptance_criteria, entra objetivo / como_funciona_hoje / regras / onde / aceite / o_que_nao_fazer / stakeholders / como_validar',
                    'Campos removidos: type, labels, estimated_complexity',
                    'Regras do framework adicionadas: comportamento ≠ implementação; testável por colega de produto; lacunas explicitadas',
                    'como_funciona_hoje aceita null quando a funcionalidade é nova e independente',
                    'stakeholders: lista de nomes de pessoas; lista vazia se o resumo não trouxer nomes',
                ],
            ],
            [
                'versao' => 'v3',
                'data' => '2026-09-16',
                'release' => '0.6.17',
                'resumo' => 'Quando a geração recebe um bloco de regras do projeto (/docs), o card deve se alinhar a elas. O resumo da entrevista continua a fonte dos fatos; não inventar escopo só com base em /docs. Sem o bloco, o comportamento permanece equivalente ao v2.',
                'mudancas' => [
                    'Instrução para alinhar o card às regras do projeto quando o bloco /docs estiver presente',
                    'Resumo da entrevista permanece a única fonte dos fatos do card',
                    'Sem bloco de regras, geração equivalente ao v2',
                ],
            ],
        ],

        'docs_retrieval' => [
            [
                'versao' => 'v1',
                'data' => '2026-09-16',
                'release' => '0.6.23',
                'resumo' => 'Primeira versão do prompt de retrieval de /docs. Recebe o índice de paths e o resumo da entrevista e devolve JSON com no máximo 10 arquivos relevantes para o card. Descarta ADRs genéricos, histórico e docs de infraestrutura; ignora instruções embutidas nos nomes dos arquivos.',
                'mudancas' => [],
            ],
        ],

        'docs_briefing' => [
            [
                'versao' => 'v1',
                'data' => '2026-09-16',
                'release' => '0.6.25',
                'resumo' => 'Primeira versão do prompt de briefing de /docs. Cruza o conteúdo lido com o resumo da entrevista e devolve texto livre em português, destacando sobreposição, conflito, vocabulário e objetivo. Trata /docs como dado, nunca como instrução; não faz perguntas e não impõe teto de tamanho.',
                'mudancas' => [],
            ],
        ],

        'discovery' => [
            [
                'versao' => 'v1',
                'data' => '2026-09-05',
                'release' => '0.3.0',
                'resumo' => 'Modo legado: um único prompt conduzia a entrevista e gerava o card no mesmo fluxo, sem separação de steps. Framework clássico (problema, persona, contexto, impacto, critérios de sucesso e aceite, fora do escopo, restrições técnicas). Substituído pelos steps interview + card_generation a partir do release 0.3.0; mantido apenas para conversas antigas.',
                'mudancas' => [],
            ],
        ],

    ],

    'releases' => [

        [
            'versao' => '0.7.0',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Retrieval e briefing de /docs na conversa',
            'resumo' => 'Ao encerrar a entrevista, um prompt versionado escolhe quais arquivos de /docs ler; outro cruza o conteúdo com o resumo e escreve um briefing na conversa antes do card. O dump completo fica só como fallback. ADR 0005 e o README documentam a pipeline.',
            'commits' => [
                [
                    'hash' => '3c9e959',
                    'data' => '2026-09-16',
                    'mensagem' => 'Registrar o discovery do retrieval versionado de /docs para filtrar o contexto do card.',
                ],
                [
                    'hash' => '0d69c78',
                    'data' => '2026-09-16',
                    'mensagem' => 'Registrar o discovery do briefing de /docs na conversa antes de gerar o card.',
                ],
            ],
            'modulos' => [
                [
                    'nome' => 'Arquitetura',
                    'itens' => [
                        ['titulo' => 'ADR 0005 — filtrar /docs via prompt versionado em dois turns', 'estado' => 'development', 'nota' => 'docs/adr/0005-filtrar-docs-via-prompt-versionado.md; completePrompt no LlmGateway'],
                    ],
                ],
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'DocsRetrievalService: listar paths, retrieval, leitura filtrada e briefing', 'estado' => 'development', 'nota' => 'docs_retrieval@v1 e docs_briefing@v1; fallback para dump completo'],
                        ['titulo' => 'ProjectDocsGateway lista índice e lê subset', 'estado' => 'development', 'nota' => 'listDocsPaths e readDocsByPaths; teto de chars no filtrado'],
                    ],
                ],
                [
                    'nome' => 'Chat',
                    'itens' => [
                        ['titulo' => 'Briefing persistido como mensagem assistant após a frase de revisão', 'estado' => 'development', 'nota' => 'Falha silenciosa; generateCard usa /docs da sessão'],
                    ],
                ],
                [
                    'nome' => 'Documentação',
                    'itens' => [
                        ['titulo' => 'Discoveries 0004 e 0005', 'estado' => 'development', 'nota' => 'Retrieval versionado e briefing na conversa'],
                        ['titulo' => 'README 0.7.0 com fluxo de retrieval e briefing', 'estado' => 'development', 'nota' => 'Diagrama à parte, no mesmo estilo do fluxo completo'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.28',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Testes no AGENTS.md sem aprovação do usuário',
            'resumo' => 'A execução de testes no container (`composer test` e filtros PHPUnit equivalentes) passa a ser feita sem pedir confirmação no chat. A regra vale só para testes; outros scripts continuam sujeitos a aprovação.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Documentação',
                    'itens' => [
                        ['titulo' => 'AGENTS.md: testes sem aprovação do usuário', 'estado' => 'stable', 'nota' => 'docker compose exec app composer test; permissões de sandbox/Docker no próprio comando'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.27',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Métricas de docs_retrieval e docs_briefing',
            'resumo' => 'completePrompt passa a receber a conversa e resolver o prompt versionado no catálogo, gravando LlmUsage com step, versão e hash. A pipeline por step compara docs_retrieval@v1 e docs_briefing@v1 junto com interview e card_generation.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'completePrompt grava LlmUsage dos prompts avulsos', 'estado' => 'development', 'nota' => 'DocsRetrievalService envia a conversa; adapters resolvem docs_retrieval@v1 e docs_briefing@v1'],
                        ['titulo' => 'Pipeline por step inclui os novos prompts', 'estado' => 'development', 'nota' => 'compareByStep agrupa step@version; dashboard lista docs_retrieval e docs_briefing'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.26',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Briefing de /docs como mensagem na conversa',
            'resumo' => 'Quando a revisão de /docs vem ok com briefing, a conversa ganha uma segunda mensagem assistant com o texto humanizado, depois da frase de revisão. Falha ou briefing vazio fica silenciosa: só a frase atual, sem erro na UI e sem gravar o briefing na sessão.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'reviewProjectDocs persiste o briefing como mensagem assistant', 'estado' => 'development', 'nota' => 'Ordem: frase de revisão, depois briefing; retry relê /docs e gera briefing novo'],
                        ['titulo' => 'Fallback silencioso quando briefing é null ou vazio', 'estado' => 'development', 'nota' => 'empty/too_large/failed seguem só com a frase atual; sessão e card_generation inalterados'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.25',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Briefing de /docs no DocsRetrievalService',
            'resumo' => 'Após ler /docs com status ok, um segundo completePrompt gera um texto humanizado (docs_briefing@v1) e o anexa em ProjectDocsResult. Falha, timeout ou resposta vazia deixam briefing null e o resultado continua ok; a conversa ainda não persiste essa mensagem.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'Prompt docs_briefing@v1', 'estado' => 'development', 'nota' => 'CHAT_DOCS_BRIEFING_PROMPT_VERSION=v1; texto livre em pt-BR, sem perguntas'],
                        ['titulo' => 'DocsRetrievalService gera briefing após status ok', 'estado' => 'development', 'nota' => 'Log project_docs.briefing (status, chars_input, chars_output); modelo via CHAT_DOCS_BRIEFING_MODEL'],
                        ['titulo' => 'completePrompt propaga o step até o adapter', 'estado' => 'development', 'nota' => 'docs_briefing distinto de docs_retrieval; LlmUsage recebe o step quando houver conversa'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.24',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Retrieval de /docs em dois turns com fallback',
            'resumo' => 'O encerramento da entrevista escolhe arquivos de /docs via prompt docs_retrieval@v1 e lê só o subset. Se o retrieval falhar, a lista vier vazia ou os paths forem inválidos, o fluxo volta ao dump completo.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'DocsRetrievalService orquestra listagem, prompt e leitura filtrada', 'estado' => 'development', 'nota' => 'Log project_docs.retrieval; modelo configurável em chat.prompts.docs_retrieval.model'],
                        ['titulo' => 'ConversationChat usa o retrieval com fallback para readProjectDocs', 'estado' => 'development', 'nota' => 'reviewProjectDocs deixa de chamar o dump direto'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.23',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Prompt docs_retrieval@v1 para filtrar /docs',
            'resumo' => 'Novo system prompt versionado escolhe, pelo índice de paths e pelo resumo da entrevista, no máximo 10 arquivos de /docs relevantes para o card. A saída é JSON estrito; o catálogo passa a resolver docs_retrieval@v1.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'Prompt docs_retrieval@v1', 'estado' => 'development', 'nota' => 'CHAT_DOCS_RETRIEVAL_PROMPT_VERSION=v1; JSON {"paths": [...]} máx. 10'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.22',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Port de /docs lista paths e lê arquivos filtrados',
            'resumo' => 'O ProjectDocsGateway passa a listar os caminhos de /docs sem ler o conteúdo e a ler somente os arquivos pedidos. O teto de caracteres vale sobre o subset; a leitura completa da árvore permanece igual.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'MCP',
                    'itens' => [
                        ['titulo' => 'listDocsPaths devolve índice plano de /docs sem conteúdo', 'estado' => 'development', 'nota' => 'ProjectDocsPathsResult (ok, failed)'],
                        ['titulo' => 'readDocsByPaths lê só os paths informados e aplica o teto no filtrado', 'estado' => 'development', 'nota' => 'Paths fora de /docs são ignorados'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.21',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Fluxo do README inclui a leitura de /docs',
            'resumo' => 'O fluxograma completo mostra a leitura de /docs via MCP no encerramento da entrevista, antes do botão Gerar Card, e a geração usa o resumo mais /docs quando a revisão ok.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Documentação',
                    'itens' => [
                        ['titulo' => 'Nó de /docs no fluxo completo do README', 'estado' => 'development', 'nota' => 'Entre interview_summary e o botão Gerar Card; sem ramificar empty/failed/retry'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.20',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'ADR da leitura de /docs via MCP GitHub',
            'resumo' => 'Registra a decisão de ler a pasta /docs pelo MCP oficial (port ProjectDocsGateway, token efêmero, payload na sessão) em vez da REST ou de cache local.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Arquitetura',
                    'itens' => [
                        ['titulo' => 'ADR 0004 — Ler /docs via MCP GitHub', 'estado' => 'development', 'nota' => 'docs/adr/0004-ler-docs-via-mcp-github.md'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.19',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Retry de /docs vazio e pasta docs',
            'resumo' => 'A leitura de regras do projeto passa a usar a pasta /docs. Quando a revisão vier vazia, o PM vê o botão Tentar revisão novamente — o mesmo já existia só em falha de GitHub.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Chat',
                    'itens' => [
                        ['titulo' => 'Botão Tentar revisão novamente também no status empty', 'estado' => 'development', 'nota' => 'too_large e ok continuam sem retry'],
                        ['titulo' => 'Mensagens, prompt v3 e adapters passam a citar /docs', 'estado' => 'development'],
                    ],
                ],
                [
                    'nome' => 'MCP',
                    'itens' => [
                        ['titulo' => 'docs_path padrão docs em vez de doc', 'estado' => 'development', 'nota' => 'config/mcp.php'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.18',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Branch do projeto na leitura de /doc',
            'resumo' => 'O cadastro de projeto passa a exigir a branch de onde a pasta /doc será lida. Essa branch vai como ref no get_file_contents do MCP; sem o campo o cliente omite ref e o GitHub usa a branch default do repositório.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Projetos',
                    'itens' => [
                        ['titulo' => 'Campo branch no cadastro e na listagem admin', 'estado' => 'development', 'nota' => 'Obrigatório; default main nos registros existentes'],
                        ['titulo' => 'Validação GitHubBranch (nome curto, sem refs/ ou caracteres inseguros)', 'estado' => 'development'],
                    ],
                ],
                [
                    'nome' => 'MCP',
                    'itens' => [
                        ['titulo' => 'get_file_contents envia ref quando a branch do projeto está preenchida', 'estado' => 'development', 'nota' => 'Vazio: omite ref e o MCP usa a default do repo'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.17',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Geração de card com contexto de /doc',
            'resumo' => 'Novas gerações usam card_generation@v3. Com revisão ok na sessão, o texto de /doc vai para a LLM junto com o resumo; nos demais status o card sai sem esse contexto e sem reler o GitHub.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Chat',
                    'itens' => [
                        ['titulo' => 'generateCard lê project_docs da sessão e envia /doc só se status=ok', 'estado' => 'development', 'nota' => 'empty/too_large/failed/ausente: sem bloco; não persiste no banco'],
                        ['titulo' => 'Log de geração com has_project_docs e chars, sem o texto', 'estado' => 'development'],
                    ],
                ],
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'LlmGateway::generateCard aceita contexto opcional de /doc', 'estado' => 'development', 'nota' => 'LlmRouter, OpenRouterAdapter e OpenAiAdapter'],
                        ['titulo' => 'Prompt card_generation@v3', 'estado' => 'development', 'nota' => 'CHAT_CARD_PROMPT_VERSION=v3; v2 intacto'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.16',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Revisão de /doc ao encerrar a entrevista',
            'resumo' => 'Com projeto selecionado, o encerramento da entrevista lê a pasta doc via ProjectDocsGateway, avisa o PM no chat e guarda o resultado na sessão. Falha oferece retry só da leitura; a geração do card ainda não usa esse texto.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Chat',
                    'itens' => [
                        ['titulo' => 'Leitura de /doc em markInterviewReady quando há projeto na sessão', 'estado' => 'development', 'nota' => 'Sem projeto: nenhuma chamada nem mensagem extra'],
                        ['titulo' => 'Mensagens de status ok, empty, too_large e failed no chat', 'estado' => 'development', 'nota' => 'failed mostra Tentar revisão novamente'],
                        ['titulo' => 'Payload efêmero project_docs.{conversationId} para a geração do card', 'estado' => 'development', 'nota' => 'Sessão, não MySQL; content só em ok'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.15',
            'data' => '2026-09-16',
            'estado' => 'development',
            'titulo' => 'Cliente MCP GitHub para ler a pasta doc',
            'resumo' => 'Port ProjectDocsGateway lê a pasta doc do repositório via MCP Streamable HTTP, autenticado com installation token da GitHub App. O token fica só em memória; sem credenciais o gateway devolve failed sem HTTP outbound. Chat e geração de card não mudam.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'MCP',
                    'itens' => [
                        ['titulo' => 'Port ProjectDocsGateway + ProjectDocsResult (ok, empty, too_large, failed)', 'estado' => 'development', 'nota' => 'app/Contracts/ProjectDocsGateway.php'],
                        ['titulo' => 'Mint do installation token da GitHub App (JWT → access_tokens)', 'estado' => 'development', 'nota' => 'Token efêmero; nunca persistido nem logado'],
                        ['titulo' => 'Adapter MCP Streamable HTTP + get_file_contents recursivo em doc', 'estado' => 'development', 'nota' => 'Pula não-texto/não-UTF-8; aborta sem truncar acima de max_chars'],
                    ],
                ],
                [
                    'nome' => 'Configuração',
                    'itens' => [
                        ['titulo' => 'config/mcp.php com URL, timeout e max_chars', 'estado' => 'development', 'nota' => 'GITHUB_MCP_URL, GITHUB_MCP_TIMEOUT, GITHUB_MCP_MAX_CHARS'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.14',
            'data' => '2026-09-15',
            'estado' => 'stable',
            'titulo' => 'Papel tipado e seleção de projeto reutilizável',
            'resumo' => 'O papel do usuário passa a ser um enum PHP com cast (admin | product_manager), o CRUD admin reforça a autorização no render e a seleção de projeto da sessão ganha um ponto único (CurrentProject) para o MCP validar se o projeto ainda está ativo.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Autorização',
                    'itens' => [
                        ['titulo' => 'UserRole enum + cast no User; valores inválidos não hidratam', 'estado' => 'stable'],
                        ['titulo' => 'AdminProjects::render() exige can(admin)', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Projetos',
                    'itens' => [
                        ['titulo' => 'CurrentProject higieniza current_project_id fora da listagem', 'estado' => 'development', 'nota' => 'SelectProjects e futuros consumidores (MCP) leem CurrentProject::id()'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'UserRoleTest rejeita papel inválido; CurrentProjectTest cobre sessão stale', 'estado' => 'test'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.13',
            'data' => '2026-09-15',
            'estado' => 'stable',
            'titulo' => 'Métricas e versões só no menu do admin',
            'resumo' => 'Métricas e Versões saem da barra principal e passam a ficar no dropdown do usuário, visíveis e acessíveis apenas para admin. Product managers continuam vendo Conversas, Cards, Projetos e o perfil.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Navegação',
                    'itens' => [
                        ['titulo' => 'Métricas e Versões no dropdown do usuário, só para admin', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Autorização',
                    'itens' => [
                        ['titulo' => 'Rotas /metrics e /versoes protegidas pelo middleware admin', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'PM não vê os links nem acessa as páginas; admin vê no dropdown', 'estado' => 'test'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.12',
            'data' => '2026-09-15',
            'estado' => 'bug',
            'titulo' => 'Livewire inicia uma vez só e o save de projeto volta a persistir',
            'resumo' => 'O Alpine/Livewire era iniciado duas vezes (bundle ESM + auto-start), o que quebrava o $persist no browser e impedia o wire:submit de cadastrar o projeto. Os layouts passam a emitir @livewireScriptConfig para o start único do app.js.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Frontend',
                    'itens' => [
                        ['titulo' => '@livewireStyles e @livewireScriptConfig nos layouts app e guest', 'estado' => 'bug', 'nota' => 'Evita inject de livewire.js junto com livewire.esm.js; Livewire.start() no app.js fica único'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'Página admin de projetos e login expõem script config e não o livewire.js injetado', 'estado' => 'test'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.11',
            'data' => '2026-09-15',
            'estado' => 'bug',
            'titulo' => 'Cadastro de projeto aceita URL do GitHub',
            'resumo' => 'O formulário admin de projetos recusava https://github.com/owner/repo e o clone SSH, então o cadastro parecia não salvar. Agora a URL é convertida para owner/repo antes de persistir; hosts que não sejam github.com continuam recusados.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Projetos',
                    'itens' => [
                        ['titulo' => 'Normalizar URL/SSH do github.com para owner/repo no save', 'estado' => 'bug', 'nota' => 'GitHubRepository::normalize(); mensagem de sucesso após cadastrar/editar'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'AdminProjects e GitHubRepository cobrem URL, .git e host estranho', 'estado' => 'test'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.10',
            'data' => '2026-09-15',
            'estado' => 'development',
            'titulo' => 'Listagem e seleção de projeto pelo PM',
            'resumo' => 'Todo usuário autenticado lista projetos ativos em /projetos e escolhe um pelo nome amigável. A escolha fica só na sessão (current_project_id); o chat ainda não consome essa seleção. Soft-deleted e ids inexistentes não entram na sessão.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Projetos',
                    'itens' => [
                        ['titulo' => 'Rota /projetos autenticada e verificada, sem middleware admin', 'estado' => 'development'],
                        ['titulo' => 'Livewire SelectProjects — listar ativos, selecionar e limpar', 'estado' => 'development', 'nota' => 'Sessão current_project_id só com projeto ativo; PM não vê owner/repo'],
                        ['titulo' => 'Link Projetos na navigation para todos os autenticados', 'estado' => 'development', 'nota' => 'Desktop e menu mobile; admin continua com Gerenciar Projetos no CRUD'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'SelectProjectsTest — guest, visibilidade, sessão e CRUD admin intacto', 'estado' => 'test', 'nota' => 'tests/Feature/Livewire/SelectProjectsTest.php'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.9',
            'data' => '2026-09-15',
            'estado' => 'development',
            'titulo' => 'UI admin para cadastrar, editar e desativar projetos',
            'resumo' => 'Administradores passam a gerenciar projetos GitHub em /admin/projetos (criar, editar nome/repositório e desativar com soft delete). Product managers recebem 403 e não veem o link. Repositório inválido ou duplicado (inclusive desativado) não persiste; o chat permanece inalterado.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Admin',
                    'itens' => [
                        ['titulo' => 'Rotas /admin/projetos com auth, verified e middleware admin', 'estado' => 'development'],
                        ['titulo' => 'Livewire AdminProjects — listar ativos, criar, editar e desativar', 'estado' => 'development', 'nota' => 'Desativar usa delete() (SoftDeletes), sem forceDelete nem undelete'],
                        ['titulo' => 'Link Projetos na navigation só se can(admin)', 'estado' => 'development', 'nota' => 'Desktop e menu mobile'],
                        ['titulo' => 'Log de auditoria criar/editar/desativar com user_id e project_id', 'estado' => 'development', 'nota' => 'Sem dump de request nem Private Key'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'AdminProjectsTest — guest, PM 403, CRUD e unique', 'estado' => 'test', 'nota' => 'tests/Feature/Livewire/AdminProjectsTest.php'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.8',
            'data' => '2026-09-15',
            'estado' => 'development',
            'titulo' => 'Persistência de projetos GitHub e config da GitHub App',
            'resumo' => 'Projetos globais (nome amigável + owner/repo) com soft delete e unicidade de repository. Credenciais da GitHub App entram só em env/config; a app sobe com as chaves vazias e não faz HTTP ao GitHub.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Projetos',
                    'itens' => [
                        ['titulo' => 'Tabela projects (name, repository único, soft delete)', 'estado' => 'development'],
                        ['titulo' => 'Model Project com factory', 'estado' => 'development', 'nota' => 'Sem dono: projetos não pertencem a usuário'],
                        ['titulo' => 'Rule GitHubRepository (formato owner/repo)', 'estado' => 'development', 'nota' => 'Recusa URL, IP, esquema, host, .., espaços e barras extras'],
                    ],
                ],
                [
                    'nome' => 'Configuração',
                    'itens' => [
                        ['titulo' => 'services.github_app lê GITHUB_APP_ID, PRIVATE_KEY e INSTALLATION_ID', 'estado' => 'development', 'nota' => 'Vazias por padrão; Private Key nunca vai para o banco'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'ProjectTest — factory, unique e soft delete', 'estado' => 'test', 'nota' => 'tests/Feature/Models/ProjectTest.php'],
                        ['titulo' => 'GitHubRepositoryTest — casos válidos e inválidos', 'estado' => 'test', 'nota' => 'tests/Unit/Rules/GitHubRepositoryTest.php'],
                        ['titulo' => 'GitHubAppConfigTest — env sem HTTP outbound', 'estado' => 'test', 'nota' => 'tests/Feature/Config/GitHubAppConfigTest.php'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.7',
            'data' => '2026-09-15',
            'estado' => 'development',
            'titulo' => 'Papéis admin e product_manager com Gate e middleware',
            'resumo' => 'Usuários passam a ter papel admin ou product_manager (default). O primeiro admin entra pelo seed (test@example.com); em produção, o comando user:promote-admin promove um e-mail já cadastrado. Rotas futuras de configuração usam o alias de middleware admin.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Autenticação',
                    'itens' => [
                        ['titulo' => 'Coluna users.role (admin | product_manager)', 'estado' => 'development', 'nota' => 'Default product_manager; usuários existentes não são elevados'],
                        ['titulo' => 'Gate admin e middleware alias admin', 'estado' => 'development'],
                        ['titulo' => 'Seed promove test@example.com a admin', 'estado' => 'development'],
                        ['titulo' => 'Comando artisan user:promote-admin {email}', 'estado' => 'development', 'nota' => 'Loga só id e e-mail; e-mail inexistente falha sem criar usuário'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'UserRoleTest — factory, Gate, middleware, seed e comando', 'estado' => 'test', 'nota' => 'tests/Feature/Auth/UserRoleTest.php'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.6',
            'data' => '2026-09-08',
            'estado' => 'bug',
            'titulo' => 'Fallback OpenRouter também em resposta vazia',
            'resumo' => 'HTTP 200 sem conteúdo (comum em modelos free da OpenRouter) deixa de abortar a conversa: o adapter troca para o próximo modelo de OPENROUTER_FALLBACK_MODELS e registra no log que trocou para o fallback.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'OpenRouterAdapter — fallback em resposta vazia com log da troca', 'estado' => 'bug', 'nota' => 'app/Services/Adapters/OpenRouterAdapter.php'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'OpenRouterAdapterTest e ConversationChatTest — fallback em content vazio', 'estado' => 'test', 'nota' => 'tests/Feature/Services/OpenRouterAdapterTest.php'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.5',
            'data' => '2026-09-08',
            'estado' => 'development',
            'titulo' => 'AGENTS.md alinhado ao adapter OpenAI',
            'resumo' => 'Atualiza o guia de agentes com OpenAiAdapter, prefixos nativos, tabela de preços em config/llm.php, ADRs e a regra de só registrar o adapter quando a chave existir.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Docs',
                    'itens' => [
                        ['titulo' => 'AGENTS.md — stack, estrutura e convenções de adapter OpenAI', 'estado' => 'development', 'nota' => 'AGENTS.md'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.4',
            'data' => '2026-09-08',
            'estado' => 'development',
            'titulo' => 'Docs: OpenAI no README e no guia de launch',
            'resumo' => 'Atualiza README e MVP_LAUNCH_GUIDE para o adapter OpenAI: chave opcional, roteamento por prefixo nativo, custo estimado e o fato de que o conteúdo da conversa pode ir à OpenAI ou à OpenRouter conforme o modelo.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Docs',
                    'itens' => [
                        ['titulo' => 'README 0.6.3 — OpenAI no diagrama, setup e tabela de preços', 'estado' => 'development', 'nota' => 'README.md'],
                        ['titulo' => 'Guia de launch: provedor, privacidade e custo estimado', 'estado' => 'development', 'nota' => 'docs/MVP_LAUNCH_GUIDE.md'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.3',
            'data' => '2026-09-08',
            'estado' => 'development',
            'titulo' => 'ADRs do adapter OpenAI e da tabela de preços',
            'resumo' => 'Registra duas decisões posteriores ao ADR 0001: roteamento OpenAI por prefixo nativo (gpt-, o1, o3, o4) e estimativa de custo em LlmUsage via config/llm.php quando a API não devolve usage.cost.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Docs',
                    'itens' => [
                        ['titulo' => 'ADR 0002 — rotear OpenAI direto por prefixo nativo', 'estado' => 'development', 'nota' => 'docs/adr/0002-rotear-openai-direto-por-prefixo-nativo.md'],
                        ['titulo' => 'ADR 0003 — estimar custo de LLM pela tabela de preços', 'estado' => 'development', 'nota' => 'docs/adr/0003-estimar-custo-llm-pela-tabela-de-precos.md'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.2',
            'data' => '2026-09-08',
            'estado' => 'stable',
            'titulo' => 'Métricas não marcam OpenAI como grátis',
            'resumo' => 'Chamadas pagas com cost=0 (registros antigos ou API sem usage.cost) passam a mostrar o valor estimado pela tabela em config/llm.php. A página de métricas explica que modelos pagos consultam essa tabela e nunca aparecem como Grátis.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Métricas',
                    'itens' => [
                        ['titulo' => 'LlmUsage.effectiveCost estima pela tabela quando o custo gravado é 0', 'estado' => 'stable', 'nota' => 'Usado no resumo, por modelo, sessão e pipeline de prompt'],
                        ['titulo' => 'Página /metrics aponta para config/llm.php', 'estado' => 'stable', 'nota' => 'Selo “tabela de preços” nas linhas estimadas'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.1',
            'data' => '2026-09-08',
            'estado' => 'stable',
            'titulo' => 'Custo estimado para adapters sem usage.cost',
            'resumo' => 'A OpenAI (e qualquer API direta) não devolve o valor gasto na resposta. O LlmUsage passa a estimar o custo pelos tokens e pela tabela em config/llm.php. Se a API já mandar usage.cost (OpenRouter), esse valor continua prevalecendo — inclusive 0 nos modelos grátis.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'LlmPricing estima USD a partir de tokens + tabela de preços', 'estado' => 'stable', 'nota' => 'app/Support/LlmPricing.php — snapshots resolvem pelo prefixo mais longo'],
                        ['titulo' => 'LlmUsage.recordFromResponse preenche cost e provider quando a API omite', 'estado' => 'stable', 'nota' => 'Warning no log se o modelo não estiver em config/llm.php'],
                        ['titulo' => 'Tabela de preços OpenAI: gpt-4o-mini, gpt-4o, gpt-4.1, o4-mini', 'estado' => 'stable', 'nota' => 'config/llm.php — obrigatório cadastrar ao adicionar adapter novo'],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'LlmPricingTest + SessionUsageTest para custo estimado e custo reportado', 'estado' => 'stable', 'nota' => ''],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.6.0',
            'data' => '2026-09-08',
            'estado' => 'stable',
            'titulo' => 'Adapter OpenAI direto',
            'resumo' => 'Adiciona OpenAiAdapter que roteia modelos gpt-*, o1*, o3* e o4* direto para a API da OpenAI, sem passar pelo OpenRouter. A chave OPENAI_API_KEY é opcional; quando ausente, todos os modelos continuam indo para o OpenRouter. Catálogo do composer ganha GPT-4o Mini, GPT-4o, GPT-4.1 e o4 Mini.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'OpenAiAdapter — roteamento direto para api.openai.com', 'estado' => 'stable', 'nota' => 'app/Services/Adapters/OpenAiAdapter.php'],
                        ['titulo' => 'LlmRouter registra prefixos gpt-, o1, o3, o4 → OpenAiAdapter', 'estado' => 'stable', 'nota' => 'Ativo apenas quando OPENAI_API_KEY está configurada'],
                        ['titulo' => 'Modelos GPT-4o Mini, GPT-4o, GPT-4.1, o4 Mini no catálogo', 'estado' => 'stable', 'nota' => 'config/chat.php'],
                    ],
                ],
                [
                    'nome' => 'Config',
                    'itens' => [
                        ['titulo' => 'services.openai com api_key e model', 'estado' => 'stable', 'nota' => 'config/services.php'],
                        ['titulo' => '.env.example documentado com OPENAI_API_KEY e OPENAI_MODEL', 'estado' => 'stable', 'nota' => ''],
                    ],
                ],
                [
                    'nome' => 'Testes',
                    'itens' => [
                        ['titulo' => 'OpenAiAdapterTest — 8 cenários (chat, card, erros, rate limit, override de modelo)', 'estado' => 'stable', 'nota' => 'tests/Feature/Services/OpenAiAdapterTest.php'],
                        ['titulo' => 'ChatComposerTest atualizado com novos modelos do catálogo', 'estado' => 'stable', 'nota' => ''],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.9',
            'data' => '2026-09-08',
            'estado' => 'development',
            'titulo' => 'ADR da arquitetura LLM',
            'resumo' => 'Registra a decisão de desacoplar a LLM com Ports & Adapters: contrato LlmGateway, LlmRouter por prefixo e OpenRouterAdapter como default.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Docs',
                    'itens' => [
                        ['titulo' => 'ADR 0001 — desacoplar LLM com Ports & Adapters', 'estado' => 'development', 'nota' => 'docs/adr/0001-desacoplar-llm-com-ports-e-adapters.md'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.8',
            'data' => '2026-09-08',
            'estado' => 'bug',
            'titulo' => 'Catálogo de modelos sem MiniMax M3',
            'resumo' => 'Remove minimax/minimax-m3:free, que deixou de responder. O composer passa a listar Nemotron 3 Ultra, Laguna S 2.1, Nemotron 3.5 Lightning e Ling 3.0 Flash Fin, nessa ordem.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'MiniMax M3 removido do catálogo e do default', 'estado' => 'bug', 'nota' => 'OPENROUTER_MODEL agora é nvidia/nemotron-3-ultra-550b-a55b:free; fallbacks são Laguna, Lightning e Ling'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.7',
            'data' => '2026-09-08',
            'estado' => 'development',
            'titulo' => 'LLM desacoplada via Ports & Adapters',
            'resumo' => 'ConversationChat passa a depender do contrato LlmGateway. OpenRouter vira adapter; LlmRouter escolhe o provedor pelo prefixo do modelo.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'Port LlmGateway e LlmRouter', 'estado' => 'development', 'nota' => 'Router resolve o adapter pelo prefixo do modelo e cai no OpenRouterAdapter por padrão'],
                        ['titulo' => 'OpenRouterService extraído para OpenRouterAdapter', 'estado' => 'development', 'nota' => 'ConversationChat deixa de conhecer o provedor; mensagens de erro genéricas (Erro LLM)'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.6',
            'data' => '2026-09-08',
            'estado' => 'bug',
            'titulo' => 'Login via ngrok sem erro 419 de CSRF',
            'resumo' => 'O app passa a confiar em proxies TLS (ngrok) e a gerar URLs/cookies em HTTPS, evitando o 419 no POST /login ao apresentar o MVP em outro computador.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Auth',
                    'itens' => [
                        ['titulo' => 'TrustProxies e HTTPS atrás do ngrok', 'estado' => 'bug', 'nota' => 'X-Forwarded-Proto honrado no Laravel e no nginx; formulário de login usa a URL HTTPS pública'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.5',
            'data' => '2026-09-07',
            'estado' => 'development',
            'titulo' => 'README alinhado à versão atual e ao OpenRouter',
            'resumo' => 'README curto com stack atual (Livewire 4), fluxo de entrevista e geração de card, e OpenRouter como único gateway de LLM.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Docs',
                    'itens' => [
                        ['titulo' => 'README atualizado para o MVP 0.5.x', 'estado' => 'development', 'nota' => 'OpenRouter explícito; setup sem bootstrap legado; modelos e fila obsoletos removidos'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.4',
            'data' => '2026-09-07',
            'estado' => 'development',
            'titulo' => 'Trava de escopo amplo mais tolerante e selo imediato',
            'resumo' => 'A detecção de INTERVIEW_SCOPE_TOO_BROAD aceita conteúdo interno e self-closing; o header troca o selo na mesma sessão; helpers de entrevista pronta respeitam a tag.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Conversas',
                    'itens' => [
                        ['titulo' => 'Parser alinha detecção e limpeza da tag de escopo amplo', 'estado' => 'development', 'nota' => 'Tag vazia, com texto interno ou self-closing entram em scope_too_broad; looksInterviewReady e extractInterviewSummary recusam a tag'],
                        ['titulo' => 'Selo Escopo amplo demais atualiza sem reload', 'estado' => 'development', 'nota' => 'Evento interview-scope-too-broad no header Alpine; persistScopeTooBroad unifica o primeiro persist e a reentrada'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.3',
            'data' => '2026-09-07',
            'estado' => 'development',
            'titulo' => 'Trava de geração quando o escopo da entrevista é amplo',
            'resumo' => 'A tag INTERVIEW_SCOPE_TOO_BROAD passa a bloquear a conversa (current_step scope_too_broad): sem resumo, sem botão Gerar Card e sem geração, mesmo se o PM insistir. O caminho feliz da entrevista de um card permanece inalterado.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Conversas',
                    'itens' => [
                        ['titulo' => 'Estado grudento scope_too_broad recusa geração de card', 'estado' => 'development', 'nota' => 'Parser, sendMessage, generateCard e atalho “gere o card”; selo no header; composer segue aberto'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.2',
            'data' => '2026-09-07',
            'estado' => 'development',
            'titulo' => 'Prompt de entrevista v4 recusa escopo de épico',
            'resumo' => 'A versão corrente do prompt de interview passa a ser v4: recusa demanda equivalente a vários cards, pede retorno com um card definido e emite INTERVIEW_SCOPE_TOO_BROAD, sem gerar card nem INTERVIEW_COMPLETE.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Prompts',
                    'itens' => [
                        ['titulo' => 'Prompt de entrevista (v4)', 'estado' => 'development', 'nota' => 'Um card por entrevista; tag INTERVIEW_SCOPE_TOO_BROAD no escopo amplo; conversas em v3 permanecem no v3'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.1',
            'data' => '2026-09-07',
            'estado' => 'development',
            'titulo' => 'Orientação de escopo no empty state do Interview',
            'resumo' => 'A tela inicial do Assistente de Interview passa a deixar explícito que a entrevista cobre um único card e que o épico deve estar definido antes de começar.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Conversas',
                    'itens' => [
                        ['titulo' => 'Copy do empty state: um card por entrevista e épico prévio', 'estado' => 'development'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.5.0',
            'data' => '2026-09-06',
            'estado' => 'development',
            'titulo' => 'Framework de cards do time',
            'resumo' => 'Entrevista e geração de card passam a usar o framework do time (objetivo, regras, onde, aceite, stakeholders e como validar), no lugar de user story e critérios de aceite clássicos.',
            'nota' => 'Prompts interview@v3 e card_generation@v2. Cards existentes perdem os campos antigos na migration.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Prompts',
                    'itens' => [
                        ['titulo' => 'Prompt de entrevista (v3)', 'estado' => 'development', 'nota' => 'Conduz pelo framework e fecha com INTERVIEW_SUMMARY estruturado'],
                        ['titulo' => 'Prompt de geração de card (v2)', 'estado' => 'development', 'nota' => 'JSON com objetivo, regras, onde, aceite, stakeholders e como validar'],
                    ],
                ],
                [
                    'nome' => 'Cards',
                    'itens' => [
                        ['titulo' => 'Campos do framework no modelo e na migration', 'estado' => 'development'],
                        ['titulo' => 'Parser normaliza listas e textos do novo JSON', 'estado' => 'development'],
                        ['titulo' => 'Lista, detalhe e preview com as novas seções', 'estado' => 'development'],
                    ],
                ],
                [
                    'nome' => 'Plataforma',
                    'itens' => [
                        ['titulo' => 'AGENTS.md com convenções de stack, prompts e versões', 'estado' => 'development'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.4.0',
            'data' => '2026-09-05',
            'estado' => 'development',
            'titulo' => 'Histórico de versões',
            'resumo' => 'Página autenticada que registra a evolução do PM Helper a partir dos commits: versão, data, o que entrou e a maturidade de cada entrega.',
            'nota' => 'Novas versões entram no topo de config/versoes.php, com os commits do intervalo.',
            'commits' => [],
            'modulos' => [
                [
                    'nome' => 'Plataforma',
                    'itens' => [
                        ['titulo' => 'Página /versoes com linha do tempo', 'estado' => 'development'],
                        ['titulo' => 'Busca por versão, módulo, feature ou commit', 'estado' => 'development'],
                        ['titulo' => 'Filtro por maturidade (estável, development, test, bug)', 'estado' => 'development'],
                        ['titulo' => 'Commits de cada release (hash, data e mensagem)', 'estado' => 'development'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.3.0',
            'data' => '2026-09-05',
            'estado' => 'development',
            'titulo' => 'Prompts versionados, métricas e pipeline',
            'resumo' => 'System prompts passam a viver em arquivos versionados, a entrevista e a geração de card viram steps distintos, e o painel de métricas compara consumo e desempenho por versão de prompt. Inclui fallback automático de LLM e o clique em Gerar Card.',
            'nota' => 'Cinco commits do dia 05/09/2026.',
            'commits' => [
                [
                    'hash' => 'c90fdaf',
                    'data' => '2026-09-05',
                    'mensagem' => 'Corrigir o clique em Gerar Card e aceitar o pedido no chat.',
                ],
                [
                    'hash' => 'e5b8a22',
                    'data' => '2026-09-05',
                    'mensagem' => 'Trocar automaticamente para LLM de fallback em rate limit.',
                ],
                [
                    'hash' => '5d29e1f',
                    'data' => '2026-09-05',
                    'mensagem' => 'Separar entrevista e geração de card em prompts e steps distintos.',
                ],
                [
                    'hash' => 'bf9ea08',
                    'data' => '2026-09-05',
                    'mensagem' => 'Adicionar menu de métricas de consumo e de versões de prompt.',
                ],
                [
                    'hash' => 'c56a329',
                    'data' => '2026-09-05',
                    'mensagem' => 'Isolar o system prompt em arquivos versionados para medir evolução.',
                ],
            ],
            'modulos' => [
                [
                    'nome' => 'Prompts',
                    'itens' => [
                        ['titulo' => 'Catálogo em resources/prompts', 'estado' => 'stable', 'nota' => 'SystemPromptCatalog; versão gravada na conversa e no uso'],
                        ['titulo' => 'Prompt de entrevista (v1 e v2)', 'estado' => 'development'],
                        ['titulo' => 'Prompt de geração de card (v1)', 'estado' => 'development'],
                        ['titulo' => 'Prompt de discovery (v1)', 'estado' => 'development', 'nota' => 'Modo legado ainda disponível'],
                    ],
                ],
                [
                    'nome' => 'Pipeline',
                    'itens' => [
                        ['titulo' => 'Steps interview e card_generation', 'estado' => 'development'],
                        ['titulo' => 'Campos de entrevista na conversa', 'estado' => 'development'],
                        ['titulo' => 'Parser de card e resumo da entrevista', 'estado' => 'stable'],
                        ['titulo' => 'Clique em Gerar Card e aceitar o pedido no chat', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Métricas',
                    'itens' => [
                        ['titulo' => 'Página /metrics', 'estado' => 'stable'],
                        ['titulo' => 'Comparação por versão de prompt', 'estado' => 'development'],
                        ['titulo' => 'Consumo por step e por modelo', 'estado' => 'development'],
                    ],
                ],
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'Fallback automático em rate limit', 'estado' => 'stable', 'nota' => 'OPENROUTER_FALLBACK_MODEL'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.2.0',
            'data' => '2026-09-04',
            'estado' => 'stable',
            'titulo' => 'Rastreio de consumo de tokens',
            'resumo' => 'Cada chamada à LLM passa a gravar tokens, cache e custo. A sessão mostra o consumo no chat e o compositor centraliza modelos e skills.',
            'commits' => [
                [
                    'hash' => '82ddea7',
                    'data' => '2026-09-04',
                    'mensagem' => 'controller token coast',
                ],
            ],
            'modulos' => [
                [
                    'nome' => 'Consumo',
                    'itens' => [
                        ['titulo' => 'Tabela llm_usages', 'estado' => 'stable'],
                        ['titulo' => 'Widget de consumo da sessão', 'estado' => 'stable'],
                        ['titulo' => 'Custo formatado por chamada', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Chat',
                    'itens' => [
                        ['titulo' => 'ChatComposer (modelos e skills)', 'estado' => 'stable'],
                        ['titulo' => 'Seletor de modelo na conversa', 'estado' => 'stable'],
                    ],
                ],
            ],
        ],

        [
            'versao' => '0.1.0',
            'data' => '2026-09-04',
            'estado' => 'stable',
            'titulo' => 'Primeira versão',
            'resumo' => 'Base do PM Helper: autenticação, conversas de discovery, geração de cards e integração com OpenRouter, empacotada em Docker.',
            'nota' => 'Commit inicial (39138ba).',
            'commits' => [
                [
                    'hash' => '39138ba',
                    'data' => '2026-09-04',
                    'mensagem' => 'first commit',
                ],
            ],
            'modulos' => [
                [
                    'nome' => 'Plataforma',
                    'itens' => [
                        ['titulo' => 'Laravel 13 + PHP 8.3', 'estado' => 'stable'],
                        ['titulo' => 'Livewire 4 + Tailwind + Vite', 'estado' => 'stable'],
                        ['titulo' => 'Docker (nginx, PHP, MySQL)', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Autenticação',
                    'itens' => [
                        ['titulo' => 'Login e registro (Breeze)', 'estado' => 'stable'],
                        ['titulo' => 'Verificação de e-mail', 'estado' => 'stable'],
                        ['titulo' => 'Recuperação de senha', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Conversas',
                    'itens' => [
                        ['titulo' => 'Criar e listar conversas', 'estado' => 'stable'],
                        ['titulo' => 'Chat Livewire', 'estado' => 'stable'],
                        ['titulo' => 'Excluir conversa', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'Cards',
                    'itens' => [
                        ['titulo' => 'Gerar card a partir da conversa', 'estado' => 'stable'],
                        ['titulo' => 'Preview do card', 'estado' => 'stable'],
                        ['titulo' => 'Listar e visualizar cards', 'estado' => 'stable'],
                    ],
                ],
                [
                    'nome' => 'LLM',
                    'itens' => [
                        ['titulo' => 'Integração OpenRouter', 'estado' => 'stable'],
                    ],
                ],
            ],
        ],

    ],

];
