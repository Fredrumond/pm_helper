<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;

class DocumentChunker
{
    public const SIZE = RagConfig::DEFAULT_CHUNK_SIZE;

    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly ?RagConfig $rag = null,
    ) {}

    public function chunk(Document $document): void
    {
        $document->chunks()->delete();

        $size = $this->rag()->chunkSize();
        $pieces = $size > 0
            ? str_split($document->content, $size)
            : [$document->content];

        foreach ($pieces as $index => $content) {
            DocumentChunk::query()->create([
                'document_id' => $document->id,
                'content' => $content,
                'chunk_index' => $index,
                'embedding' => $this->embeddings->embed($content),
            ]);
        }
    }

    private function rag(): RagConfig
    {
        return $this->rag ?? app(RagConfig::class);
    }
}
