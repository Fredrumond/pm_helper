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
        'prompt_name',
        'prompt_version',
        'current_step',
        'interview_summary',
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

    public function llmUsages(): HasMany
    {
        return $this->hasMany(LlmUsage::class)->orderBy('created_at');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInterviewComplete(): bool
    {
        return is_string($this->interview_summary) && trim($this->interview_summary) !== '';
    }

    public function isScopeTooBroad(): bool
    {
        return $this->current_step === 'scope_too_broad';
    }

    /**
     * @return array{calls: int, prompt_tokens: int, completion_tokens: int, total_tokens: int, cached_tokens: int, cost: float}
     */
    public function usageSummary(): array
    {
        $usages = $this->relationLoaded('llmUsages')
            ? $this->llmUsages
            : $this->llmUsages()->get();

        return [
            'calls' => $usages->count(),
            'prompt_tokens' => (int) $usages->sum('prompt_tokens'),
            'completion_tokens' => (int) $usages->sum('completion_tokens'),
            'total_tokens' => (int) $usages->sum('total_tokens'),
            'cached_tokens' => (int) $usages->sum('cached_tokens'),
            'cost' => (float) $usages->sum(fn (LlmUsage $usage) => (float) $usage->cost),
        ];
    }

    public function formattedUsageCost(): string
    {
        return LlmUsage::formatCost($this->usageSummary()['cost']);
    }

    /**
     * Retorna o histórico de mensagens no formato esperado pela API da LLM.
     */
    public function toLlmHistory(): array
    {
        return $this->messages->map(fn (Message $msg) => [
            'role' => $msg->role,
            'content' => $msg->content,
        ])->toArray();
    }
}
