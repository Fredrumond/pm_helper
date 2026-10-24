<?php

namespace App\Models;

use Database\Factories\CardDiscoveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardDiscovery extends Model
{
    /** @use HasFactory<CardDiscoveryFactory> */
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'card_id',
        'provider',
        'status',
        'external_agent_id',
        'external_run_id',
        'error_message',
        'document_disk',
        'document_path',
        'started_at',
        'finished_at',
        'deadline_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_RUNNING], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_REQUESTED => 'Solicitado',
            self::STATUS_RUNNING => 'Em andamento',
            self::STATUS_COMPLETED => 'Concluído',
            self::STATUS_FAILED => 'Falhou',
            default => (string) $this->status,
        };
    }
}
