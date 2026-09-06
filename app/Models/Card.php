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
        'objetivo',
        'como_funciona_hoje',
        'regras',
        'onde',
        'aceite',
        'o_que_nao_fazer',
        'stakeholders',
        'como_validar',
        'priority',
        'status',
    ];

    protected $casts = [
        'regras' => 'array',
        'onde' => 'array',
        'aceite' => 'array',
        'o_que_nao_fazer' => 'array',
        'stakeholders' => 'array',
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

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
