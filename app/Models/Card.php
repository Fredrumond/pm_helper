<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'title',
        'type',
        'user_story',
        'context',
        'acceptance_criteria',
        'out_of_scope',
        'technical_notes',
        'priority',
        'labels',
        'estimated_complexity',
        'status',
    ];

    protected $casts = [
        'acceptance_criteria' => 'array',
        'out_of_scope'        => 'array',
        'labels'              => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function priorityLabel(): string
    {
        return match($this->priority) {
            'low'      => 'Baixa',
            'medium'   => 'Média',
            'high'     => 'Alta',
            'critical' => 'Crítica',
            default    => $this->priority,
        };
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'feature'   => 'Feature',
            'bug'       => 'Bug',
            'tech_debt' => 'Tech Debt',
            'spike'     => 'Spike',
            default     => $this->type,
        };
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
