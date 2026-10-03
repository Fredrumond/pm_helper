<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\LlmModel;
use App\Services\DocumentChunker;
use App\Services\EmbeddingService;
use App\Services\RagConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class DocumentChunkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.21, -0.43, 0.72]],
                ],
            ], 200),
        ]);
    }

    public function test_splits_content_every_500_characters_and_stores_indexes(): void
    {
        $content = str_repeat('a', 500).str_repeat('b', 500).str_repeat('c', 10);
        $document = Document::factory()->create(['content' => $content]);

        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $this->assertDatabaseCount('document_chunks', 3);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => str_repeat('a', 500),
        ]);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 1,
            'content' => str_repeat('b', 500),
        ]);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 2,
            'content' => str_repeat('c', 10),
        ]);
        $this->assertSame(
            [0.21, -0.43, 0.72],
            $document->chunks()->first()?->embedding
        );
    }

    public function test_replaces_previous_chunks_when_run_again(): void
    {
        $document = Document::factory()->create(['content' => str_repeat('x', 600)]);

        $chunker = new DocumentChunker(new EmbeddingService);
        $chunker->chunk($document);

        $document->update(['content' => 'curto']);
        $chunker->chunk($document);

        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'curto',
        ]);
    }

    public function test_next_index_uses_the_configured_chunk_size_and_saving_config_keeps_existing_chunks(): void
    {
        $this->seedEmbeddingModel();
        $document = Document::factory()->create(['content' => str_repeat('a', 25)]);
        $chunker = new DocumentChunker(new EmbeddingService);
        $chunker->chunk($document);

        $this->assertDatabaseCount('document_chunks', 1);

        $this->assertTrue(app(RagConfig::class)->save(10, RagConfig::DEFAULT_EMBEDDING_MODEL, 3));
        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaa', $document->chunks()->first()?->content);

        $chunker->chunk($document);

        $this->assertDatabaseCount('document_chunks', 3);
        $this->assertSame(
            [str_repeat('a', 10), str_repeat('a', 10), str_repeat('a', 5)],
            $document->chunks()->orderBy('chunk_index')->pluck('content')->all()
        );
    }

    public function test_non_positive_chunk_size_stores_the_whole_content_as_one_piece(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(0, RagConfig::DEFAULT_EMBEDDING_MODEL, 3));

        $document = Document::factory()->create(['content' => 'abcdef']);
        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'abcdef',
        ]);
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
