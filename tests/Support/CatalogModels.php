<?php

namespace Tests\Support;

use App\Models\LlmModel;

final class CatalogModels
{
    /**
     * @param  list<array{id: string, name: string, tier?: string, provider?: string, active?: bool, price_input?: float|null, price_cached?: float|null, price_output?: float|null}>  $models
     */
    public static function seed(array $models): void
    {
        foreach ($models as $model) {
            LlmModel::query()->create([
                'model_id' => $model['id'],
                'name' => $model['name'],
                'tier' => $model['tier'] ?? LlmModel::TIER_FREE,
                'provider' => $model['provider'] ?? LlmModel::PROVIDER_OPENROUTER,
                'active' => $model['active'] ?? true,
                'price_input' => $model['price_input'] ?? null,
                'price_cached' => $model['price_cached'] ?? null,
                'price_output' => $model['price_output'] ?? null,
            ]);
        }
    }

    public static function seedGpt4oMiniPrice(): void
    {
        self::seed([[
            'id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 0.15,
            'price_cached' => 0.075,
            'price_output' => 0.60,
        ]]);
    }
}
