<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub MCP
    |--------------------------------------------------------------------------
    |
    | Cliente Streamable HTTP do servidor oficial (remoto ou sidecar).
    | O installation token da GitHub App vai só no header Authorization;
    | nunca é persistido. Sem credenciais em services.github_app o
    | gateway falha sem HTTP outbound.
    |
    */

    'github' => [
        'url' => env('GITHUB_MCP_URL', 'https://api.githubcopilot.com/mcp/'),
        'timeout' => (int) env('GITHUB_MCP_TIMEOUT', 20),
        'max_chars' => (int) env('GITHUB_MCP_MAX_CHARS', 80000),
        'docs_path' => 'docs',
    ],

];
