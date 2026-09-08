<?php

namespace App\Support;

class LlmPricing
{
    /**
     * @return array{provider: string, input: float, cached: float, output: float}|null
     */
    public static function ratesFor(string $model): ?array
    {
        $catalog = config('llm.pricing', []);

        if (! is_array($catalog) || $catalog === []) {
            return null;
        }

        if (isset($catalog[$model]) && is_array($catalog[$model])) {
            return self::normalizeRates($catalog[$model]);
        }

        $matches = [];

        foreach ($catalog as $id => $rates) {
            if (! is_string($id) || ! is_array($rates)) {
                continue;
            }

            if (str_starts_with($model, $id.'-')) {
                $matches[$id] = $rates;
            }
        }

        if ($matches === []) {
            return null;
        }

        uksort($matches, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return self::normalizeRates(array_values($matches)[0]);
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
     * @param  array<string, mixed>  $rates
     * @return array{provider: string, input: float, cached: float, output: float}
     */
    private static function normalizeRates(array $rates): array
    {
        $input = (float) ($rates['input'] ?? 0);

        return [
            'provider' => (string) ($rates['provider'] ?? ''),
            'input' => $input,
            'cached' => (float) ($rates['cached'] ?? $input),
            'output' => (float) ($rates['output'] ?? 0),
        ];
    }
}
