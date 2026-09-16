<?php

namespace Tests\Unit\Services;

use App\Services\ProjectDocsPathsResult;
use App\Services\ProjectDocsResult;
use Tests\TestCase;

class ProjectDocsPathsResultTest extends TestCase
{
    public function test_ok_keeps_the_path_list_without_error_code(): void
    {
        $result = ProjectDocsPathsResult::ok([
            'docs/README.md',
            'docs/regras/pagamento.md',
        ]);

        $this->assertSame(ProjectDocsPathsResult::STATUS_OK, $result->status);
        $this->assertSame([
            'docs/README.md',
            'docs/regras/pagamento.md',
        ], $result->paths);
        $this->assertNull($result->errorCode);
    }

    public function test_failed_keeps_error_code_and_empty_paths(): void
    {
        $result = ProjectDocsPathsResult::failed(ProjectDocsResult::ERROR_TIMEOUT);

        $this->assertSame(ProjectDocsPathsResult::STATUS_FAILED, $result->status);
        $this->assertSame([], $result->paths);
        $this->assertSame(ProjectDocsResult::ERROR_TIMEOUT, $result->errorCode);
    }
}
