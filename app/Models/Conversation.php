<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function card(): HasOne
    {
        return $this->hasOne(Card::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Retorna o histórico de mensagens no formato esperado pela API da LLM.
     */
    public function toLlmHistory(): array
    {
        return $this->messages->map(fn (Message $msg) => [
            'role'    => $msg->role,
            'content' => $msg->content,
        ])->toArray();
    }
}
