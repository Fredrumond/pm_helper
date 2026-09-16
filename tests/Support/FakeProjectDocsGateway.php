<?php

namespace Tests\Support;

use App\Contracts\ProjectDocsGateway;
use App\Services\ProjectDocsResult;
use RuntimeException;

class FakeProjectDocsGateway implements ProjectDocsGateway
{
    /** @var list<array{repository: string, branch: ?string}> */
    public array $calls = [];

    /** @var list<ProjectDocsResult> */
    private array $results = [];

    public function queue(ProjectDocsResult $result): self
    {
        $this->results[] = $result;

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
}
