<?php

namespace App\Support;

use App\Models\LlmModel;
use App\Services\LlmModelCatalog;

class ChatComposer
{
    /**
     * @return list<array{id: string, name: string, tier: string}>
     */
    public static function models(): array
    {
        return app(LlmModelCatalog::class)
            ->active()
            ->map(fn (LlmModel $model): array => [
                'id' => $model->model_id,
                'name' => $model->name,
                'tier' => $model->tier,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{slash: string, name: string, description: string, prompt: string}>
     */
    public static function skills(): array
    {
        /** @var list<array{slash: string, name: string, description: string, prompt: string}> $skills */
        $skills = array_values(config('chat.skills', []));

        return $skills;
    }

    public static function defaultModel(): string
    {
        $ids = array_column(self::models(), 'id');
        $configured = (string) config('services.openrouter.model');

        if ($configured !== '' && in_array($configured, $ids, true)) {
            return $configured;
        }

        return $ids[0] ?? '';
    }

    public static function isAllowedModel(string $model): bool
    {
        return in_array($model, array_column(self::models(), 'id'), true);
    }

    /**
     * @return array{id: string, name: string, tier: string}|null
     */
    public static function findModel(string $id): ?array
    {
        foreach (self::models() as $model) {
            if ($model['id'] === $id) {
                return $model;
            }
        }

        return null;
    }
}
