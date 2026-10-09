<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class RagSetting extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'chunk_size',
        'embedding_model',
        'top_k',
        'chunk_strategy',
        'min_score',
        'sync_sources',
    ];

    protected $casts = [
        'chunk_size' => 'integer',
        'top_k' => 'integer',
        'sync_sources' => 'array',
    ];
}
