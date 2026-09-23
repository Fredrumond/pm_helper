<?php

namespace App\Support;

use App\Models\LlmModel;
use App\Services\LlmModelCatalog;

class LlmPricing
{
    /**
     * @return array{provider: string, input: float, cached: float, output: float}|null
     */
    public static function ratesFor(string $model): ?array
    {
        $paid = app(LlmModelCatalog::class)
            ->list()
            ->filter(fn (LlmModel $record): bool => $record->isPaid());

        $exact = $paid->firstWhere('model_id', $model);

        if ($exact instanceof LlmModel) {
            return self::ratesFromModel($exact);
        }

        $matches = [];

        foreach ($paid as $record) {
            if (str_starts_with($model, $record->model_id.'-')) {
                $matches[$record->model_id] = $record;
            }
        }

        if ($matches === []) {
            return null;
        }

        uksort($matches, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return self::ratesFromModel(array_values($matches)[0]);
    }

    public static function resolveProvider(string $model): ?string
    {
        return self::ratesFor($model)['provider'] ?? null;
    }

    public static function estimateCost(
        string $model,
        int $promptTokens,
        int $completionTokens,
        int $cachedTokens = 0,
    ): ?float {
        $rates = self::ratesFor($model);

        if ($rates === null) {
            return null;
        }

        $cached = max(0, min($cachedTokens, $promptTokens));
        $input = max(0, $promptTokens - $cached);
        $output = max(0, $completionTokens);

        $cost = ($input * $rates['input'] / 1_000_000)
            + ($cached * $rates['cached'] / 1_000_000)
            + ($output * $rates['output'] / 1_000_000);

        return round($cost, 8);
    }

    /**
     * @return array{provider: string, input: float, cached: float, output: float}|null
     */
    private static function ratesFromModel(LlmModel $record): ?array
    {
        if ($record->price_input === null || $record->price_output === null) {
            return null;
        }

        $input = (float) $record->price_input;

        return [
            'provider' => $record->provider,
            'input' => $input,
            'cached' => $record->price_cached === null ? $input : (float) $record->price_cached,
            'output' => (float) $record->price_output,
        ];
    }
}
