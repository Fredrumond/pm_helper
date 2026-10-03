<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;

class DocumentChunker
{
    public const SIZE = 500;

    public function chunk(Document $document): void
    {
        $document->chunks()->delete();

        foreach (str_split($document->content, self::SIZE) as $index => $content) {
            DocumentChunk::query()->create([
                'document_id' => $document->id,
                'content' => $content,
                'chunk_index' => $index,
            ]);
        }
    }
}
