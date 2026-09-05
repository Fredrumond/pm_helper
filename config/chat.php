<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelos disponíveis no composer
    |--------------------------------------------------------------------------
    |
    | IDs no formato da OpenRouter. O modelo do .env (OPENROUTER_MODEL) entra
    | automaticamente na lista caso ainda não esteja aqui.
    |
    */
    'models' => [
        [
            'id' => 'minimax/minimax-m3:free',
            'name' => 'MiniMax M3',
            'tier' => 'Free',
        ],
        [
            'id' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'name' => 'Nemotron 3 Ultra',
            'tier' => 'Free',
        ],
        [
            'id' => 'poolside/laguna-s-2.1:free',
            'name' => 'Laguna S 2.1',
            'tier' => 'Free',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modo do chat
    |--------------------------------------------------------------------------
    |
    | interview — entrevista + geração de card em prompts separados.
    | discovery — prompt único legado (resources/prompts/discovery).
    | Conversas já iniciadas mantêm o prompt original gravado no banco.
    |
    */
    'mode' => env('CHAT_MODE', 'interview'),

    /*
    |--------------------------------------------------------------------------
    | System prompts
    |--------------------------------------------------------------------------
    |
    | Cada versão vive em resources/prompts/{name}/{version}.md.
    | Para evoluir: copie o arquivo para v2.md, ajuste o texto e troque a
    | versão abaixo. Conversas já iniciadas permanecem na versão original.
    | Não edite um arquivo já publicado — isso mistura medições.
    |
    */
    'prompts' => [
        'discovery' => [
            'version' => env('CHAT_PROMPT_VERSION', 'v1'),
        ],
        'interview' => [
            'version' => env('CHAT_INTERVIEW_PROMPT_VERSION', 'v2'),
        ],
        'card_generation' => [
            'version' => env('CHAT_CARD_PROMPT_VERSION', 'v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Skills do composer (atalho /)
    |--------------------------------------------------------------------------
    |
    | Reservado para uma versão futura. O botão "/" do chat mostra "Em breve".
    |
    */
    'skills' => [
        [
            'slash' => 'discovery',
            'name' => 'Discovery',
            'description' => 'Conduzir o discovery de uma necessidade',
            'prompt' => "Quero iniciar o discovery desta necessidade:\n",
        ],
        [
            'slash' => 'card',
            'name' => 'Gerar card',
            'description' => 'Avançar para um card estruturado',
            'prompt' => 'Com o contexto que já temos, avance para a geração do card estruturado.',
        ],
        [
            'slash' => 'historia',
            'name' => 'User story',
            'description' => 'Escrever a história de usuário',
            'prompt' => "Ajude-me a escrever a user story desta necessidade:\n",
        ],
        [
            'slash' => 'criterios',
            'name' => 'Critérios de aceite',
            'description' => 'Definir critérios no formato Dado/Quando/Então',
            'prompt' => "Vamos definir os critérios de aceite (Dado/Quando/Então) para:\n",
        ],
        [
            'slash' => 'bug',
            'name' => 'Bug',
            'description' => 'Estruturar um card de correção',
            'prompt' => "Quero reportar um bug. Descrevo o que acontece:\n",
        ],
        [
            'slash' => 'divida',
            'name' => 'Dívida técnica',
            'description' => 'Documentar uma dívida técnica',
            'prompt' => "Quero registrar uma dívida técnica:\n",
        ],
        [
            'slash' => 'spike',
            'name' => 'Spike',
            'description' => 'Planejar uma investigação técnica',
            'prompt' => "Preciso de um spike para investigar:\n",
        ],
        [
            'slash' => 'escopo',
            'name' => 'Fora de escopo',
            'description' => 'Definir o que não entra nesta entrega',
            'prompt' => "Vamos delimitar o que fica fora do escopo desta entrega:\n",
        ],
    ],

];
