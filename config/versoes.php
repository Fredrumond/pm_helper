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

    'releases' => [

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
