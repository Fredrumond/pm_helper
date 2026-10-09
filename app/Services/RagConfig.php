<?php

namespace App\Services;

use App\Models\LlmModel;
use App\Models\RagSetting;

class RagConfig
{
    public const DEFAULT_CHUNK_SIZE = 500;

    public const DEFAULT_EMBEDDING_MODEL = 'text-embedding-3-small';

    public const DEFAULT_TOP_K = 3;

    public const STRATEGY_SIZE = 'size';

    public const STRATEGY_PARAGRAPH = 'paragraph';

    public const STRATEGY_MARKDOWN = 'markdown';

    public const STRATEGIES = [
        self::STRATEGY_SIZE,
        self::STRATEGY_PARAGRAPH,
        self::STRATEGY_MARKDOWN,
    ];

    public const DEFAULT_STRATEGY = self::STRATEGY_SIZE;

    public const DEFAULT_MIN_SCORE = 0.0;

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

    public function strategy(): string
    {
        $stored = RagSetting::query()->value('chunk_strategy');

        if (! is_string($stored) || ! in_array($stored, self::STRATEGIES, true)) {
            return self::DEFAULT_STRATEGY;
        }

        return $stored;
    }

    public function minScore(): float
    {
        $stored = RagSetting::query()->value('min_score');

        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return self::DEFAULT_MIN_SCORE;
        }

        $score = (float) $stored;

        if ($score < 0 || $score > 1) {
            return self::DEFAULT_MIN_SCORE;
        }

        return $score;
    }

    public function save(
        int $chunkSize,
        string $embeddingModel,
        int $topK,
        string $strategy = self::DEFAULT_STRATEGY,
        float $minScore = self::DEFAULT_MIN_SCORE,
    ): bool {
        if (! $this->modelIsInCatalog($embeddingModel)) {
            return false;
        }

        if (! in_array($strategy, self::STRATEGIES, true) || ! is_finite($minScore) || $minScore < 0 || $minScore > 1) {
            return false;
        }

        $setting = RagSetting::query()->first() ?? new RagSetting;
        $setting->fill([
            'chunk_size' => $chunkSize,
            'embedding_model' => $embeddingModel,
            'top_k' => $topK,
            'chunk_strategy' => $strategy,
            'min_score' => $minScore,
        ]);
        $setting->save();

        return true;
    }

    private function modelIsInCatalog(string $model): bool
    {
        return LlmModel::query()->where('model_id', $model)->exists();
    }
}
