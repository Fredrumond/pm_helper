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
                'versao'  => 'v1',
                'data'    => '2026-09-05',
                'release' => '0.3.0',
                'resumo'  => 'Primeira versão do prompt de entrevista. Framework clássico de product discovery (problema, persona, contexto, impacto, critérios de sucesso e aceite, fora do escopo, restrições técnicas). A entrevista era dividida em três fases, e a emissão das tags de encerramento só ocorria após o PM confirmar explicitamente o resumo em uma mensagem separada.',
                'mudancas' => [],
            ],
            [
                'versao'  => 'v2',
                'data'    => '2026-09-05',
                'release' => '0.3.0',
                'resumo'  => 'Mantém o mesmo framework do v1, mas remove a espera de confirmação do PM: o resumo e as tags de encerramento passam a ser emitidos na mesma mensagem. Aceita pedido de pular etapas quando o contexto já é suficiente, em vez de insistir nas fases.',
                'mudancas' => [
                    'Fase de validação unificada: resumo e tags INTERVIEW_COMPLETE emitidos na mesma mensagem, sem aguardar confirmação do PM',
                    'Comportamento ao pular etapas: se o PM pedir para avançar e já houver contexto suficiente, a entrevista encerra imediatamente',
                ],
            ],
            [
                'versao'  => 'v3',
                'data'    => '2026-09-06',
                'release' => '0.5.0',
                'resumo'  => 'Adota o framework de cards do time no lugar do framework clássico. O fluxo passa a cobrir Objetivo, Como funciona hoje, Regras, Onde, Aceite, O que não fazer, Stakeholders e Como validar — cinco fases no lugar de três. O INTERVIEW_SUMMARY passa a usar os campos do novo framework. Adiciona bloco de Regras do Framework com diretrizes explícitas de comportamento vs implementação.',
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
                'versao'  => 'v4',
                'data'    => '2026-09-07',
                'release' => '0.5.2',
                'resumo'  => 'Adiciona trava de escopo amplo: quando a demanda equivale a vários cards, o modelo recusa a conduzir a entrevista, emite a tag INTERVIEW_SCOPE_TOO_BROAD e pede que o PM retorne com um card definido. INTERVIEW_COMPLETE e INTERVIEW_SUMMARY ficam bloqueados enquanto o escopo permanecer amplo. O caminho feliz (único card) permanece idêntico ao v3.',
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
                'versao'  => 'v1',
                'data'    => '2026-09-05',
                'release' => '0.3.0',
                'resumo'  => 'Primeira versão do prompt de geração de card. Recebe o INTERVIEW_SUMMARY e gera JSON com os campos clássicos: title, type, user_story, context, acceptance_criteria, out_of_scope, technical_notes, priority, labels e estimated_complexity.',
                'mudancas' => [],
            ],
            [
                'versao'  => 'v2',
                'data'    => '2026-09-06',
                'release' => '0.5.0',
                'resumo'  => 'Espelha a mudança de framework do interview/v3. O JSON de saída adota os campos do framework do time: objetivo, como_funciona_hoje, regras, onde, aceite, o_que_nao_fazer, stakeholders e como_validar. Campos clássicos (user_story, type, labels, estimated_complexity) são removidos. Adiciona as mesmas regras de comportamento vs implementação do prompt de entrevista.',
                'mudancas' => [
                    'JSON de saída substituído: sai user_story / context / acceptance_criteria, entra objetivo / como_funciona_hoje / regras / onde / aceite / o_que_nao_fazer / stakeholders / como_validar',
                    'Campos removidos: type, labels, estimated_complexity',
                    'Regras do framework adicionadas: comportamento ≠ implementação; testável por colega de produto; lacunas explicitadas',
                    'como_funciona_hoje aceita null quando a funcionalidade é nova e independente',
                    'stakeholders: lista de nomes de pessoas; lista vazia se o resumo não trouxer nomes',
                ],
            ],
        ],

        'discovery' => [
            [
                'versao'  => 'v1',
                'data'    => '2026-09-05',
                'release' => '0.3.0',
                'resumo'  => 'Modo legado: um único prompt conduzia a entrevista e gerava o card no mesmo fluxo, sem separação de steps. Framework clássico (problema, persona, contexto, impacto, critérios de sucesso e aceite, fora do escopo, restrições técnicas). Substituído pelos steps interview + card_generation a partir do release 0.3.0; mantido apenas para conversas antigas.',
                'mudancas' => [],
            ],
        ],

    ],

    'releases' => [

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
