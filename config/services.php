<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenRouter API
    |--------------------------------------------------------------------------
    */
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model'   => env('OPENROUTER_MODEL', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'OPENROUTER_FALLBACK_MODELS',
                'poolside/laguna-s-2.1:free,nvidia/nemotron-3.5-lightning:free,inclusionai/ling-3.0-flash-fin:free'
            ))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI API
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub App
    |--------------------------------------------------------------------------
    |
    | Credenciais da instalação única do sistema. Opcionais: a aplicação
    | sobe com os valores vazios. A Private Key nunca deve ir para o banco.
    |
    */
    'github_app' => [
        'app_id' => env('GITHUB_APP_ID', ''),
        'private_key' => env('GITHUB_APP_PRIVATE_KEY', ''),
        'installation_id' => env('GITHUB_APP_INSTALLATION_ID', ''),
    ],

];
