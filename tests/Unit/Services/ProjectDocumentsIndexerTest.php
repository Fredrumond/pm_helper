<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\Project;
use App\Services\DocumentChunker;
use App\Services\EmbeddingService;
use App\Services\ProjectDocumentsIndexer;
use App\Services\ProjectDocsResult;
use App\Services\ProjectIndexableFilesResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeProjectDocsGateway;
use Tests\TestCase;

class ProjectDocumentsIndexerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [1.0, 0.0]],
                ],
            ], 200),
        ]);
    }

    public function test_indexes_each_file_as_a_document_of_the_project(): void
    {
        $project = Project::factory()->create([
            'repository' => 'acme/checkout',
            'branch' => 'develop',
        ]);
        $docs = (new FakeProjectDocsGateway)->queueIndexable(ProjectIndexableFilesResult::ok([
            ['path' => 'README.md', 'content' => '# Checkout'],
            ['path' => 'docs/regras/pagamento.md', 'content' => 'Pagamento obrigatório.'],
        ]));

        $this->indexer($docs)->index($project);

        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'develop'],
        ], $docs->indexableCalls);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => '# Checkout',
        ]);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'docs/regras/pagamento.md',
            'content' => 'Pagamento obrigatório.',
        ]);
        $this->assertSame(2, Document::query()->where('project_id', $project->id)->count());
        $this->assertSame(2, $project->documents()->withCount('chunks')->get()->sum('chunks_count'));
    }

    public function test_logs_sync_progress_for_each_file(): void
    {
        $project = Project::factory()->create([
            'name' => 'Checkout',
            'repository' => 'acme/checkout',
            'branch' => 'develop',
        ]);
        $docs = (new FakeProjectDocsGateway)->queueIndexable(ProjectIndexableFilesResult::ok([
            ['path' => 'README.md', 'content' => '# Checkout'],
            ['path' => 'docs/regras/pagamento.md', 'content' => 'Pagamento obrigatório.'],
        ]));
        Event::fake([MessageLogged::class]);

        $this->indexer($docs)->index($project);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($project): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.sync.start'
                && ($log->context['project_id'] ?? null) === $project->id
                && ($log->context['name'] ?? null) === 'Checkout'
                && ($log->context['repository'] ?? null) === 'acme/checkout'
                && ($log->context['branch'] ?? null) === 'develop';
        });
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($project): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.sync.read'
                && ($log->context['project_id'] ?? null) === $project->id
                && ($log->context['status'] ?? null) === ProjectIndexableFilesResult::STATUS_OK
                && ($log->context['files'] ?? null) === 2
                && ($log->context['paths'] ?? null) === ['README.md', 'docs/regras/pagamento.md'];
        });
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($project): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.sync.file.done'
                && ($log->context['project_id'] ?? null) === $project->id
                && ($log->context['path'] ?? null) === 'README.md'
                && ($log->context['position'] ?? null) === 1
                && ($log->context['total'] ?? null) === 2
                && ($log->context['chunks'] ?? null) === 1;
        });
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($project): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.sync.done'
                && ($log->context['project_id'] ?? null) === $project->id
                && ($log->context['status'] ?? null) === 'ok'
                && ($log->context['indexed'] ?? null) === 2
                && ($log->context['failed'] ?? null) === 0;
        });
    }

    public function test_does_not_create_documents_when_the_read_is_empty_or_failed(): void
    {
        $project = Project::factory()->create();
        $empty = (new FakeProjectDocsGateway)->queueIndexable(ProjectIndexableFilesResult::empty());
        $failed = (new FakeProjectDocsGateway)->queueIndexable(
            ProjectIndexableFilesResult::failed(ProjectDocsResult::ERROR_MCP)
        );

        Event::fake([MessageLogged::class]);

        $this->indexer($empty)->index($project);
        $this->indexer($failed)->index($project);

        $this->assertDatabaseCount('documents', 0);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($project): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.sync.done'
                && ($log->context['project_id'] ?? null) === $project->id
                && ($log->context['status'] ?? null) === ProjectIndexableFilesResult::STATUS_EMPTY
                && ($log->context['indexed'] ?? null) === 0;
        });
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($project): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.sync.read'
                && ($log->context['project_id'] ?? null) === $project->id
                && ($log->context['status'] ?? null) === ProjectIndexableFilesResult::STATUS_FAILED
                && ($log->context['errorCode'] ?? null) === ProjectDocsResult::ERROR_MCP;
        });
    }

    private function indexer(FakeProjectDocsGateway $docs): ProjectDocumentsIndexer
    {
        return new ProjectDocumentsIndexer(
            $docs,
            new DocumentChunker(new EmbeddingService),
        );
    }
}
