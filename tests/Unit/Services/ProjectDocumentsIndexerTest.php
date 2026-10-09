<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\RagSetting;
use App\Services\DocumentChunker;
use App\Services\EmbeddingService;
use App\Services\ProjectDocsResult;
use App\Services\ProjectDocumentsIndexer;
use App\Services\ProjectDocumentsSyncResult;
use App\Services\ProjectIndexableFilesResult;
use App\Services\RagConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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
            [
                'repository' => 'acme/checkout',
                'branch' => 'develop',
                'collectionSpecs' => app(RagConfig::class)->indexableCollectionSpecsForSync(),
            ],
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

    public function test_indexes_only_markdown_files(): void
    {
        $project = Project::factory()->create();
        $docs = (new FakeProjectDocsGateway)->queueIndexable(ProjectIndexableFilesResult::ok([
            ['path' => 'README.md', 'content' => '# Checkout'],
            ['path' => 'docs/regras.txt', 'content' => 'Regra 1'],
            ['path' => 'docs/adr/0001.MD', 'content' => 'ADR'],
        ]));

        $result = $this->indexer($docs)->index($project);

        $this->assertSame(ProjectDocumentsSyncResult::STATUS_OK, $result->status);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'README.md',
        ]);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'docs/adr/0001.MD',
        ]);
        $this->assertDatabaseMissing('documents', [
            'project_id' => $project->id,
            'name' => 'docs/regras.txt',
        ]);
        $this->assertSame(2, Document::query()->where('project_id', $project->id)->count());
    }

    public function test_replaces_existing_project_documents_when_read_succeeds(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $old = Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'docs/antigo.md',
            'content' => 'Obsoleto',
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $old->id,
            'content' => 'Obsoleto',
        ]);
        $otherDocument = Document::factory()->create([
            'project_id' => $other->id,
            'name' => 'README.md',
            'content' => 'Outro projeto',
        ]);
        $docs = (new FakeProjectDocsGateway)->queueIndexable(ProjectIndexableFilesResult::ok([
            ['path' => 'README.md', 'content' => '# Novo'],
        ]));

        $result = $this->indexer($docs)->index($project);

        $this->assertSame(ProjectDocumentsSyncResult::STATUS_OK, $result->status);
        $this->assertDatabaseMissing('documents', ['id' => $old->id]);
        $this->assertDatabaseMissing('document_chunks', ['document_id' => $old->id]);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => '# Novo',
        ]);
        $this->assertDatabaseHas('documents', [
            'id' => $otherDocument->id,
            'content' => 'Outro projeto',
        ]);
        $this->assertSame(1, Document::query()->where('project_id', $project->id)->count());
    }

    public function test_keeps_existing_documents_when_read_fails(): void
    {
        $project = Project::factory()->create();
        Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => 'Atual',
        ]);
        $failed = (new FakeProjectDocsGateway)->queueIndexable(
            ProjectIndexableFilesResult::failed(ProjectDocsResult::ERROR_MCP)
        );

        $result = $this->indexer($failed)->index($project);

        $this->assertTrue($result->isFailed());
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => 'Atual',
        ]);
        $this->assertSame(1, Document::query()->where('project_id', $project->id)->count());
    }

    public function test_keeps_existing_documents_when_gateway_throws(): void
    {
        $project = Project::factory()->create();
        Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => 'Atual',
        ]);
        $docs = (new FakeProjectDocsGateway)->throwOnIndexable(new RuntimeException('mcp down'));

        $result = $this->indexer($docs)->index($project);

        $this->assertTrue($result->isFailed());
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'content' => 'Atual',
        ]);
    }

    public function test_clears_existing_documents_when_read_is_empty(): void
    {
        $project = Project::factory()->create();
        Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'docs/antigo.md',
        ]);
        $docs = (new FakeProjectDocsGateway)->queueIndexable(ProjectIndexableFilesResult::empty());

        $result = $this->indexer($docs)->index($project);

        $this->assertSame(ProjectDocumentsSyncResult::STATUS_EMPTY, $result->status);
        $this->assertSame(0, Document::query()->where('project_id', $project->id)->count());
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

    public function test_passes_partial_sync_sources_to_the_gateway(): void
    {
        $project = Project::factory()->create();
        $docs = (new FakeProjectDocsGateway)->queueIndexable(ProjectIndexableFilesResult::empty());

        RagSetting::query()->create([
            'chunk_size' => RagConfig::DEFAULT_CHUNK_SIZE,
            'embedding_model' => RagConfig::DEFAULT_EMBEDDING_MODEL,
            'top_k' => RagConfig::DEFAULT_TOP_K,
            'chunk_strategy' => RagConfig::DEFAULT_STRATEGY,
            'min_score' => RagConfig::DEFAULT_MIN_SCORE,
            'sync_sources' => [RagConfig::SYNC_SOURCE_README],
        ]);

        $this->indexer($docs)->index($project);

        $this->assertSame([
            ['path' => 'README.md', 'scope' => RagConfig::SYNC_SCOPE_FILE],
        ], $docs->indexableCalls[0]['collectionSpecs']);
    }

    private function indexer(FakeProjectDocsGateway $docs): ProjectDocumentsIndexer
    {
        return new ProjectDocumentsIndexer(
            $docs,
            new DocumentChunker(new EmbeddingService),
            app(RagConfig::class),
        );
    }
}
