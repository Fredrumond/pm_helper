<?php

namespace App\Models;

use App\Prompts\SystemPrompt;
use App\Support\LlmPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class LlmUsage extends Model
{
    protected $fillable = [
        'conversation_id',
        'generation_id',
        'model',
        'step',
        'prompt_version',
        'prompt_hash',
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
    public static function recordFromResponse(
        Conversation $conversation,
        array $data,
        string $fallbackModel,
        ?SystemPrompt $prompt = null,
        ?string $step = null,
    ): self {
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $details = is_array($usage['prompt_tokens_details'] ?? null)
            ? $usage['prompt_tokens_details']
            : [];

        $model = (string) ($data['model'] ?? $fallbackModel);
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($usage['completion_tokens'] ?? 0);
        $cachedTokens = (int) ($details['cached_tokens'] ?? 0);
        $cost = self::resolveCost($usage, $model, $promptTokens, $completionTokens, $cachedTokens);

        return self::query()->create([
            'conversation_id' => $conversation->id,
            'generation_id' => $data['id'] ?? null,
            'model' => $model,
            'step' => $step ?? $prompt?->name,
            'prompt_version' => $prompt?->version,
            'prompt_hash' => $prompt?->hash,
            'provider' => $data['provider'] ?? LlmPricing::resolveProvider($model),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'cached_tokens' => $cachedTokens,
            'cost' => $cost,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private static function resolveCost(
        array $usage,
        string $model,
        int $promptTokens,
        int $completionTokens,
        int $cachedTokens,
    ): float {
        if (array_key_exists('cost', $usage) && is_numeric($usage['cost'])) {
            return (float) $usage['cost'];
        }

        $estimated = LlmPricing::estimateCost($model, $promptTokens, $completionTokens, $cachedTokens);

        if ($estimated !== null) {
            return $estimated;
        }

        Log::warning('LLM usage sem preço cadastrado', [
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cached_tokens' => $cachedTokens,
        ]);

        return 0.0;
    }

    /**
     * Custo usado nas métricas: valor gravado pela API, ou estimativa
     * pela tabela em config/llm.php quando o modelo é pago e o custo veio 0.
     */
    public function effectiveCost(): float
    {
        $stored = (float) $this->cost;

        if ($stored > 0) {
            return $stored;
        }

        $estimated = LlmPricing::estimateCost(
            (string) $this->model,
            (int) $this->prompt_tokens,
            (int) $this->completion_tokens,
            (int) $this->cached_tokens,
        );

        return $estimated ?? $stored;
    }

    public function isEstimatedCost(): bool
    {
        return (float) $this->cost <= 0
            && LlmPricing::ratesFor((string) $this->model) !== null;
    }

    public function formattedCost(): string
    {
        return self::formatCost($this->effectiveCost());
    }

    public static function formatCost(float $cost): string
    {
        if ($cost <= 0) {
            return 'Grátis';
        }

        return '$'.rtrim(rtrim(number_format($cost, 8, '.', ''), '0'), '.');
    }
}
