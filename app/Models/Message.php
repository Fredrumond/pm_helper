<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'sources',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
        ];
    }

    /**
     * Nomes dos arquivos gravados que originaram a resposta, um por linha.
     */
    public function sourceTooltip(): ?string
    {
        if (! is_array($this->sources) || $this->sources === []) {
            return null;
        }

        return implode("\n", $this->sources);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }
}
