<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\CosineSimilarity;
use App\Services\DocumentSearch;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
