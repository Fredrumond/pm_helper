<?php

namespace App\Services;

use App\Models\LlmModel;
use App\Models\RagSetting;

class RagConfig
{
    public const DEFAULT_CHUNK_SIZE = 500;

    public const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';

    public const DEFAULT_TOP_K = 3;

    public function chunkSize(): int
    {
        $stored = RagSetting::query()->value('chunk_size');

        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        return (int) $stored;
    }

    public function embeddingModel(): string
    {
        $stored = RagSetting::query()->value('embedding_model');

        if (is_string($stored) && $stored !== '' && $this->modelIsInCatalog($stored)) {
            return $stored;
        }

        return self::DEFAULT_EMBEDDING_MODEL;
    }

    public function topK(): int
    {
        $stored = RagSetting::query()->value('top_k');

        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return self::DEFAULT_TOP_K;
        }

        return (int) $stored;
    }

    public function save(int $chunkSize, string $embeddingModel, int $topK): bool
    {
        if (! $this->modelIsInCatalog($embeddingModel)) {
            return false;
        }

        $setting = RagSetting::query()->first() ?? new RagSetting;
        $setting->fill([
            'chunk_size' => $chunkSize,
            'embedding_model' => $embeddingModel,
            'top_k' => $topK,
        ]);
        $setting->save();

        return true;
    }

    private function modelIsInCatalog(string $model): bool
    {
        return LlmModel::query()->where('model_id', $model)->exists();
    }
}
