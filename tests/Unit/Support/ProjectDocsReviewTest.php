<?php

namespace Tests\Unit\Support;

use App\Models\Project;
use App\Services\ProjectDocsResult;
use App\Support\CurrentProject;
use App\Support\ProjectDocsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDocsReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_keeps_content_only_when_status_is_ok(): void
    {
        $project = Project::factory()->create([
            'repository' => 'acme/checkout',
        ]);

        $ok = ProjectDocsReview::payloadFrom(
            $project,
            ProjectDocsResult::ok("# Regras\nPagamento obrigatório.", 3, 1),
        );

        $this->assertSame([
            'status' => 'ok',
            'content' => "# Regras\nPagamento obrigatório.",
            'project_id' => $project->id,
            'repository' => 'acme/checkout',
            'branch' => 'main',
            'file_count' => 3,
            'skipped_non_text' => 1,
            'chars' => mb_strlen("# Regras\nPagamento obrigatório.", 'UTF-8'),
        ], $ok);

        $empty = ProjectDocsReview::payloadFrom($project, ProjectDocsResult::empty(2));

        $this->assertNull($empty['content']);
        $this->assertSame('empty', $empty['status']);
        $this->assertSame(0, $empty['file_count']);
        $this->assertSame(2, $empty['skipped_non_text']);

        $tooLarge = ProjectDocsReview::payloadFrom($project, ProjectDocsResult::tooLarge(4, 0, 99_000));

        $this->assertNull($tooLarge['content']);
        $this->assertSame('too_large', $tooLarge['status']);
        $this->assertSame(4, $tooLarge['file_count']);
        $this->assertSame(99_000, $tooLarge['chars']);

        $failed = ProjectDocsReview::payloadFrom(
            $project,
            ProjectDocsResult::failed(ProjectDocsResult::ERROR_TIMEOUT),
        );

        $this->assertNull($failed['content']);
        $this->assertSame('failed', $failed['status']);
    }

    public function test_store_roundtrips_in_session_keyed_by_conversation(): void
    {
        $project = Project::factory()->create();
        $result = ProjectDocsResult::ok('doc', 1, 0);

        $this->startSession();
        ProjectDocsReview::store(42, $project, $result);

        $this->assertSame(
            ProjectDocsReview::payloadFrom($project, $result),
            session('project_docs.42'),
        );
        $this->assertTrue(ProjectDocsReview::isFailed(41) === false);
        $this->assertFalse(ProjectDocsReview::isFailed(42));
        $this->assertFalse(ProjectDocsReview::canRetry(42));

        ProjectDocsReview::store(42, $project, ProjectDocsResult::empty());

        $this->assertFalse(ProjectDocsReview::isFailed(42));
        $this->assertTrue(ProjectDocsReview::canRetry(42));

        ProjectDocsReview::store(42, $project, ProjectDocsResult::failed(ProjectDocsResult::ERROR_MCP));

        $this->assertTrue(ProjectDocsReview::isFailed(42));
        $this->assertTrue(ProjectDocsReview::canRetry(42));
        $this->assertNull(ProjectDocsReview::get(42)['content']);

        ProjectDocsReview::forget(42);

        $this->assertNull(ProjectDocsReview::get(42));
        $this->assertFalse(session()->has('project_docs.42'));
    }

    public function test_current_project_follows_the_session_selection(): void
    {
        $this->assertNull(ProjectDocsReview::currentProject());

        $project = Project::factory()->create();
        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        $this->assertTrue($project->is(ProjectDocsReview::currentProject()));
    }

    public function test_assistant_messages_match_review_status(): void
    {
        $this->assertSame(
            'Revisão carregada (1 arquivo). Você já pode gerar o card.',
            ProjectDocsReview::assistantMessage(ProjectDocsResult::ok('x', 1, 0)),
        );
        $this->assertSame(
            'Revisão carregada (3 arquivos). Você já pode gerar o card.',
            ProjectDocsReview::assistantMessage(ProjectDocsResult::ok('x', 3, 0)),
        );
        $this->assertStringContainsString(
            'Não há informações adicionais em /docs',
            ProjectDocsReview::assistantMessage(ProjectDocsResult::empty()),
        );
        $this->assertStringContainsString(
            'excede o teto',
            ProjectDocsReview::assistantMessage(ProjectDocsResult::tooLarge(2, 0, 10)),
        );
        $this->assertStringContainsString(
            'tentar novamente',
            ProjectDocsReview::assistantMessage(ProjectDocsResult::failed(ProjectDocsResult::ERROR_AUTH)),
        );
    }
}
