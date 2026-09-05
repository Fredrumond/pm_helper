<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmUsage extends Model
{
    protected $fillable = [
        'conversation_id',
        'generation_id',
        'model',
        'provider',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cached_tokens',
        'cost',
        'finish_reason',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cached_tokens' => 'integer',
        'cost' => 'decimal:8',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function recordFromResponse(Conversation $conversation, array $data, string $fallbackModel): self
    {
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $details = is_array($usage['prompt_tokens_details'] ?? null)
            ? $usage['prompt_tokens_details']
            : [];

        return self::query()->create([
            'conversation_id' => $conversation->id,
            'generation_id' => $data['id'] ?? null,
            'model' => $data['model'] ?? $fallbackModel,
            'provider' => $data['provider'] ?? null,
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'cached_tokens' => (int) ($details['cached_tokens'] ?? 0),
            'cost' => $usage['cost'] ?? 0,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
        ]);
    }

    public function formattedCost(): string
    {
        return self::formatCost((float) $this->cost);
    }

    public static function formatCost(float $cost): string
    {
        if ($cost <= 0) {
            return 'Grátis';
        }

        return '$'.rtrim(rtrim(number_format($cost, 8, '.', ''), '0'), '.');
    }
}
