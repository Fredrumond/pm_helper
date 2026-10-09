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
        $document = Document::factory()->create(['name' => 'notas.md', 'content' => $content]);

        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $this->assertDatabaseCount('document_chunks', 3);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'heading_path' => null,
            'content' => $this->withOrigin($document, str_repeat('a', 500)),
        ]);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 1,
            'content' => $this->withOrigin($document, str_repeat('b', 500)),
        ]);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 2,
            'content' => $this->withOrigin($document, str_repeat('c', 10)),
        ]);
        $this->assertSame(
            [0.21, -0.43, 0.72],
            $document->chunks()->first()?->embedding
        );
    }

    public function test_replaces_previous_chunks_when_run_again(): void
    {
        $document = Document::factory()->create(['name' => 'notas.md', 'content' => str_repeat('x', 600)]);

        $chunker = new DocumentChunker(new EmbeddingService);
        $chunker->chunk($document);

        $document->update(['content' => 'curto']);
        $chunker->chunk($document);

        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => $this->withOrigin($document->fresh(), 'curto'),
        ]);
    }

    public function test_next_index_uses_the_configured_chunk_size_and_saving_config_keeps_existing_chunks(): void
    {
        $this->seedEmbeddingModel();
        $document = Document::factory()->create(['name' => 'notas.md', 'content' => str_repeat('a', 25)]);
        $chunker = new DocumentChunker(new EmbeddingService);
        $chunker->chunk($document);

        $this->assertDatabaseCount('document_chunks', 1);

        $this->assertTrue(app(RagConfig::class)->save(10, RagConfig::DEFAULT_EMBEDDING_MODEL, 3));
        $this->assertSame($this->withOrigin($document, str_repeat('a', 25)), $document->chunks()->first()?->content);

        $chunker->chunk($document);

        $this->assertDatabaseCount('document_chunks', 3);
        $this->assertSame(
            [
                $this->withOrigin($document, str_repeat('a', 10)),
                $this->withOrigin($document, str_repeat('a', 10)),
                $this->withOrigin($document, str_repeat('a', 5)),
            ],
            $document->chunks()->orderBy('chunk_index')->pluck('content')->all()
        );
    }

    public function test_non_positive_chunk_size_stores_the_whole_content_as_one_piece(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(0, RagConfig::DEFAULT_EMBEDDING_MODEL, 3));

        $document = Document::factory()->create(['name' => 'notas.md', 'content' => 'abcdef']);
        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'heading_path' => null,
            'content' => $this->withOrigin($document, 'abcdef'),
        ]);
    }

    public function test_size_strategy_does_not_split_inside_an_accented_character(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(4, RagConfig::DEFAULT_EMBEDDING_MODEL, 3));

        $document = Document::factory()->create(['name' => 'notas.md', 'content' => 'zzção']);
        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $bodies = $document->chunks()->orderBy('chunk_index')->pluck('content')->map(
            fn (string $content): string => mb_substr($content, mb_strpos($content, "\n") + 1),
        )->all();

        $this->assertSame(['zzçã', 'o'], $bodies);
        $this->assertTrue(mb_check_encoding($bodies[0], 'UTF-8'));
        $this->assertTrue(mb_check_encoding($bodies[1], 'UTF-8'));
    }

    public function test_paragraph_strategy_keeps_a_paragraph_whole_while_it_fits(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(
            25,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            3,
            RagConfig::STRATEGY_PARAGRAPH,
        ));

        $first = str_repeat('A', 20);
        $second = str_repeat('B', 20);
        $document = Document::factory()->create([
            'name' => 'notas.md',
            'content' => $first."\n\n".$second,
        ]);

        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $chunks = $document->chunks()->orderBy('chunk_index')->get();

        $this->assertCount(2, $chunks);
        $this->assertNull($chunks[0]->heading_path);
        $this->assertSame($this->withOrigin($document, $first), $chunks[0]->content);
        $this->assertSame($this->withOrigin($document, $second), $chunks[1]->content);
    }

    public function test_markdown_strategy_splits_on_headings_and_joins_a_short_section(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(
            200,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            3,
            RagConfig::STRATEGY_MARKDOWN,
        ));

        $escopo = str_repeat('E', 80);
        $fora = str_repeat('F', 80);
        $status = str_repeat('S', 80);
        $document = Document::factory()->create([
            'name' => 'manual.md',
            'content' => "## 3. Escopo\n\n{$escopo}\n\n### Fora\n\n{$fora}\n\n## 6. Dúvidas\n\nNão.\n\n## 8. Status\n\n{$status}\n",
        ]);

        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $chunks = $document->chunks()->orderBy('chunk_index')->get();

        $this->assertSame([
            '3. Escopo',
            '3. Escopo › Fora',
            '6. Dúvidas · 8. Status',
        ], $chunks->pluck('heading_path')->all());
        $this->assertStringStartsWith('manual.md (manual.md) › 3. Escopo'."\n", $chunks[0]->content);
        $this->assertStringContainsString($escopo, $chunks[0]->content);
        $this->assertStringContainsString($fora, $chunks[1]->content);
        $this->assertStringContainsString('Não.', $chunks[2]->content);
        $this->assertStringContainsString($status, $chunks[2]->content);
        $this->assertStringNotContainsString('Não.', $chunks[1]->content);
    }

    public function test_markdown_strategy_splits_a_long_section_by_paragraph(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(
            50,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            3,
            RagConfig::STRATEGY_MARKDOWN,
        ));

        $first = str_repeat('A', 30);
        $second = str_repeat('B', 30);
        $document = Document::factory()->create([
            'name' => 'manual.md',
            'content' => "## Longa\n\n{$first}\n\n{$second}",
        ]);

        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $chunks = $document->chunks()->orderBy('chunk_index')->get();

        $this->assertCount(2, $chunks);
        $this->assertSame('Longa', $chunks[0]->heading_path);
        $this->assertSame('Longa', $chunks[1]->heading_path);
        $this->assertStringContainsString($first, $chunks[0]->content);
        $this->assertStringNotContainsString($second, $chunks[0]->content);
        $this->assertStringContainsString($second, $chunks[1]->content);
        $this->assertStringNotContainsString($first, $chunks[1]->content);
    }

    public function test_markdown_strategy_without_headings_groups_paragraphs(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(
            50,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            3,
            RagConfig::STRATEGY_MARKDOWN,
        ));

        $first = str_repeat('A', 20);
        $second = str_repeat('B', 20);
        $document = Document::factory()->create([
            'name' => 'notas.md',
            'content' => $first."\n\n".$second,
        ]);

        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $chunks = $document->chunks()->orderBy('chunk_index')->get();

        $this->assertCount(1, $chunks);
        $this->assertNull($chunks[0]->heading_path);
        $this->assertSame($this->withOrigin($document, $first."\n\n".$second), $chunks[0]->content);
    }

    public function test_short_section_stays_apart_when_joining_would_pass_the_maximum(): void
    {
        $this->seedEmbeddingModel();
        $this->assertTrue(app(RagConfig::class)->save(
            100,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            3,
            RagConfig::STRATEGY_MARKDOWN,
        ));

        $escopo = str_repeat('E', 80);
        $document = Document::factory()->create([
            'name' => 'manual.md',
            'content' => "## 3. Escopo\n\n{$escopo}\n\n## 6. Dúvidas\n\nNão.\n",
        ]);

        (new DocumentChunker(new EmbeddingService))->chunk($document);

        $chunks = $document->chunks()->orderBy('chunk_index')->get();

        $this->assertCount(2, $chunks);
        $this->assertSame('3. Escopo', $chunks[0]->heading_path);
        $this->assertSame('6. Dúvidas', $chunks[1]->heading_path);
    }

    private function withOrigin(Document $document, string $body, ?string $path = null): string
    {
        $title = $document->name;

        if (preg_match('/^#\s+(.+)$/m', $document->content, $matches) === 1) {
            $title = trim($matches[1]);
        }

        $line = $title.' ('.$document->name.')';

        if ($path !== null && $path !== '') {
            $line .= ' › '.$path;
        }

        return $line."\n".$body;
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
