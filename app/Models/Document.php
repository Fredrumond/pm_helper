<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    public const RETRIEVABLE_STATUSES = ['current', 'stable'];

    protected $fillable = [
        'project_id',
        'name',
        'content',
        'status',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Document>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Document>
     */
    public function scopeRetrievable($query)
    {
        return $query->where(function ($documents): void {
            $documents->whereNull('status')
                ->orWhereIn('status', self::RETRIEVABLE_STATUSES);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }
}
