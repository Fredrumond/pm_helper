<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Services\DocumentChunker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentChunkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_content_every_500_characters_and_stores_indexes(): void
    {
        $content = str_repeat('a', 500).str_repeat('b', 500).str_repeat('c', 10);
        $document = Document::factory()->create(['content' => $content]);

        (new DocumentChunker)->chunk($document);

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
    }

    public function test_replaces_previous_chunks_when_run_again(): void
    {
        $document = Document::factory()->create(['content' => str_repeat('x', 600)]);

        $chunker = new DocumentChunker;
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
}
