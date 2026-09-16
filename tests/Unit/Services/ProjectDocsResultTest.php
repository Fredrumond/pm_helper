<?php

namespace Tests\Unit\Services;

use App\Services\ProjectDocsResult;
use Tests\TestCase;

class ProjectDocsResultTest extends TestCase
{
    public function test_ok_factory_leaves_briefing_null(): void
    {
        $result = ProjectDocsResult::ok('# Regras', 1, 0);

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertNull($result->briefing);
    }

    public function test_with_briefing_returns_a_copy_without_mutating_the_original(): void
    {
        $original = ProjectDocsResult::ok('# Regras', 2, 1);
        $copy = $original->withBriefing('A documentação confirma o pagamento.');

        $this->assertNull($original->briefing);
        $this->assertSame('A documentação confirma o pagamento.', $copy->briefing);
        $this->assertSame($original->status, $copy->status);
        $this->assertSame($original->content, $copy->content);
        $this->assertSame($original->filesRead, $copy->filesRead);
        $this->assertSame($original->skippedNonText, $copy->skippedNonText);
        $this->assertSame($original->chars, $copy->chars);
        $this->assertSame($original->errorCode, $copy->errorCode);
    }
}
