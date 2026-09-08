<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preços por modelo (USD / 1M tokens)
    |--------------------------------------------------------------------------
    |
    | A OpenRouter devolve `usage.cost` na resposta — esse valor prevalece.
    | A OpenAI (e a maioria das APIs diretas) devolve só tokens. Neste caso
    | o LlmUsage estima o custo a partir desta tabela.
    |
    | Obrigatório ao adicionar um adapter novo:
    |   1. Registrar cada modelo do catálogo aqui (input / cached / output).
    |   2. Snapshots (gpt-4o-mini-2024-07-18) resolvem pelo prefixo mais longo.
    |   3. Sem entrada, o custo fica 0 e um warning é gravado no log.
    |
    | Fonte OpenAI: https://developers.openai.com/api/docs/models
    |
    */
    'pricing' => [
        'gpt-4o-mini' => [
            'provider' => 'openai',
            'input' => 0.15,
            'cached' => 0.075,
            'output' => 0.60,
        ],
        'gpt-4o' => [
            'provider' => 'openai',
            'input' => 2.50,
            'cached' => 1.25,
            'output' => 10.00,
        ],
        'gpt-4.1' => [
            'provider' => 'openai',
            'input' => 2.00,
            'cached' => 0.50,
            'output' => 8.00,
        ],
        'o4-mini' => [
            'provider' => 'openai',
            'input' => 0.55,
            'cached' => 0.14,
            'output' => 2.20,
        ],
    ],

];
