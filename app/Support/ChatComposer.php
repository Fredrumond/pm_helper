<?php

namespace App\Support;

class ChatComposer
{
    /**
     * @return list<array{id: string, name: string, tier: string}>
     */
    public static function models(): array
    {
        /** @var list<array{id: string, name: string, tier: string}> $models */
        $models = array_values(config('chat.models', []));
        $default = (string) config('services.openrouter.model');

        if ($default !== '' && ! self::containsModel($models, $default)) {
            array_unshift($models, [
                'id' => $default,
                'name' => $default,
                'tier' => 'Padrão',
            ]);
        }

        return $models;
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
        $configured = (string) config('services.openrouter.model');
        $ids = array_column(self::models(), 'id');

        if ($configured !== '' && in_array($configured, $ids, true)) {
            return $configured;
        }

        return $ids[0] ?? $configured;
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

    /**
     * @param  list<array{id: string, name: string, tier: string}>  $models
     */
    private static function containsModel(array $models, string $id): bool
    {
        foreach ($models as $model) {
            if ($model['id'] === $id) {
                return true;
            }
        }

        return false;
    }
}
