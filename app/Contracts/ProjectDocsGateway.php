<?php

namespace App\Contracts;

use App\Services\ProjectDocsResult;

interface ProjectDocsGateway
{
    /**
     * Lê recursivamente a pasta `doc` do repositório via MCP (somente leitura).
     * O identificador deve ser `owner/repo` já validado pelo cadastro; valores
     * malformados devolvem failed/invalid_repo sem chamar o MCP.
     * `$branch` vira o `ref` do get_file_contents; vazio omite o campo e o
     * MCP usa a branch default do repositório.
     */
    public function readProjectDocs(string $repository, ?string $branch = null): ProjectDocsResult;
}
