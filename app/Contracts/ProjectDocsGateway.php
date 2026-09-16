<?php

namespace App\Contracts;

use App\Services\ProjectDocsPathsResult;
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

    /**
     * Lista os caminhos relativos em `/docs` sem ler o conteúdo dos arquivos.
     * O identificador deve ser `owner/repo`; valores malformados devolvem
     * failed/invalid_repo sem chamar o MCP.
     */
    public function listDocsPaths(string $repository, ?string $branch = null): ProjectDocsPathsResult;

    /**
     * Lê somente os arquivos informados. Paths fora de `/docs`, inválidos
     * ou fora do escopo são ignorados. O teto de caracteres vale sobre o
     * conteúdo filtrado, não sobre a árvore inteira.
     *
     * @param  list<string>  $paths
     */
    public function readDocsByPaths(string $repository, array $paths, ?string $branch = null): ProjectDocsResult;
}
