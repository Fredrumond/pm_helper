<?php

namespace App\Models;

use Database\Factories\DocumentChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    /** @use HasFactory<DocumentChunkFactory> */
    use HasFactory;

    protected $fillable = [
        'document_id',
        'content',
        'chunk_index',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
