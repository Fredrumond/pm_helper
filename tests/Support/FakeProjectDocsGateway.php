<?php

namespace Tests\Support;

use App\Contracts\ProjectDocsGateway;
use App\Services\ProjectDocsPathsResult;
use App\Services\ProjectDocsResult;
use RuntimeException;

class FakeProjectDocsGateway implements ProjectDocsGateway
{
    /** @var list<array{repository: string, branch: ?string}> */
    public array $calls = [];

    /** @var list<array{repository: string, branch: ?string}> */
    public array $listPathsCalls = [];

    /** @var list<array{repository: string, paths: list<string>, branch: ?string}> */
    public array $readByPathsCalls = [];

    /** @var list<ProjectDocsResult> */
    private array $results = [];

    /** @var list<ProjectDocsPathsResult> */
    private array $pathResults = [];

    /** @var list<ProjectDocsResult> */
    private array $readByPathResults = [];

    public function queue(ProjectDocsResult $result): self
    {
        $this->results[] = $result;

        return $this;
    }

    public function queuePaths(ProjectDocsPathsResult $result): self
    {
        $this->pathResults[] = $result;

        return $this;
    }

    public function queueReadByPaths(ProjectDocsResult $result): self
    {
        $this->readByPathResults[] = $result;

        return $this;
    }

    public function readProjectDocs(string $repository, ?string $branch = null): ProjectDocsResult
    {
        $this->calls[] = [
            'repository' => $repository,
            'branch' => $branch,
        ];

        if ($this->results === []) {
            throw new RuntimeException('FakeProjectDocsGateway sem resultado para '.$repository);
        }

        return array_shift($this->results);
    }

    public function listDocsPaths(string $repository, ?string $branch = null): ProjectDocsPathsResult
    {
        $this->listPathsCalls[] = [
            'repository' => $repository,
            'branch' => $branch,
        ];

        if ($this->pathResults === []) {
            throw new RuntimeException('FakeProjectDocsGateway sem lista de paths para '.$repository);
        }

        return array_shift($this->pathResults);
    }

    public function readDocsByPaths(string $repository, array $paths, ?string $branch = null): ProjectDocsResult
    {
        $this->readByPathsCalls[] = [
            'repository' => $repository,
            'paths' => $paths,
            'branch' => $branch,
        ];

        if ($this->readByPathResults === []) {
            throw new RuntimeException('FakeProjectDocsGateway sem leitura filtrada para '.$repository);
        }

        return array_shift($this->readByPathResults);
    }
}
