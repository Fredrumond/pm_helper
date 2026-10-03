<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\LlmModel;
use App\Models\Project;
use App\Services\CosineSimilarity;
use App\Services\DocumentSearch;
use App\Services\EmbeddingService;
use App\Services\RagConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class DocumentSearchTest extends TestCase
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

    public function test_ranks_chunks_by_cosine_and_keeps_top_three(): void
    {
        $document = Document::factory()->create(['name' => 'missao.md']);

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk A',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk B',
            'chunk_index' => 1,
            'embedding' => [0.8, 0.6],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk C',
            'chunk_index' => 2,
            'embedding' => [0.6, 0.8],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk D',
            'chunk_index' => 3,
            'embedding' => [0.0, 1.0],
        ]);

        $matches = (new DocumentSearch(new EmbeddingService, new CosineSimilarity))
            ->search('quantos quilos de Lumina');

        $this->assertCount(3, $matches);
        $this->assertSame(['Chunk A', 'Chunk B', 'Chunk C'], array_column($matches, 'content'));
        $this->assertEqualsWithDelta(1.0, $matches[0]['score'], 0.0001);
        $this->assertEqualsWithDelta(0.8, $matches[1]['score'], 0.0001);
        $this->assertEqualsWithDelta(0.6, $matches[2]['score'], 0.0001);
        $this->assertSame('missao.md', $matches[0]['document']);
    }

    public function test_skips_chunks_without_embedding(): void
    {
        $document = Document::factory()->create();

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Sem vetor',
            'chunk_index' => 0,
            'embedding' => null,
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Com vetor',
            'chunk_index' => 1,
            'embedding' => [1.0, 0.0],
        ]);

        $matches = (new DocumentSearch(new EmbeddingService, new CosineSimilarity))
            ->search('pergunta');

        $this->assertCount(1, $matches);
        $this->assertSame('Com vetor', $matches[0]['content']);
    }

    public function test_scoped_search_without_project_uses_only_unscoped_documents(): void
    {
        $project = Project::factory()->create();
        $manual = Document::factory()->create(['name' => 'manual.md', 'project_id' => null]);
        $ofProject = Document::factory()->create(['name' => 'projeto.md', 'project_id' => $project->id]);

        DocumentChunk::factory()->create([
            'document_id' => $manual->id,
            'content' => 'Doc sem projeto',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $ofProject->id,
            'content' => 'Doc do projeto',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        $matches = (new DocumentSearch(new EmbeddingService, new CosineSimilarity))
            ->search('pergunta', null, true);

        $this->assertCount(1, $matches);
        $this->assertSame('Doc sem projeto', $matches[0]['content']);
    }

    public function test_scoped_search_with_project_uses_only_that_projects_documents(): void
    {
        $selected = Project::factory()->create();
        $other = Project::factory()->create();
        $manual = Document::factory()->create(['name' => 'manual.md', 'project_id' => null]);
        $ofSelected = Document::factory()->create(['name' => 'selecionado.md', 'project_id' => $selected->id]);
        $ofOther = Document::factory()->create(['name' => 'outro.md', 'project_id' => $other->id]);

        DocumentChunk::factory()->create([
            'document_id' => $manual->id,
            'content' => 'Doc sem projeto',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $ofSelected->id,
            'content' => 'Doc do selecionado',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $ofOther->id,
            'content' => 'Doc de outro projeto',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        $matches = (new DocumentSearch(new EmbeddingService, new CosineSimilarity))
            ->search('pergunta', $selected->id, true);

        $this->assertCount(1, $matches);
        $this->assertSame('Doc do selecionado', $matches[0]['content']);
    }

    public function test_keeps_only_the_configured_top_k(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(RagConfig::DEFAULT_CHUNK_SIZE, RagConfig::DEFAULT_EMBEDDING_MODEL, 1));

        $document = Document::factory()->create(['name' => 'missao.md']);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk A',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk B',
            'chunk_index' => 1,
            'embedding' => [0.0, 1.0],
        ]);

        $matches = (new DocumentSearch(new EmbeddingService, new CosineSimilarity))->search('pergunta');

        $this->assertCount(1, $matches);
        $this->assertSame('Chunk A', $matches[0]['content']);
    }

    public function test_returns_nothing_when_top_k_is_not_positive(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(RagConfig::DEFAULT_CHUNK_SIZE, RagConfig::DEFAULT_EMBEDDING_MODEL, 0));

        $document = Document::factory()->create();
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk A',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        $matches = (new DocumentSearch(new EmbeddingService, new CosineSimilarity))->search('pergunta');

        $this->assertSame([], $matches);
    }

    private function seedEmbeddingModel(): void
    {
        CatalogModels::seed([[
            'id' => RagConfig::DEFAULT_EMBEDDING_MODEL,
            'name' => 'Embedding small',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'active' => false,
        ]]);
    }
}
