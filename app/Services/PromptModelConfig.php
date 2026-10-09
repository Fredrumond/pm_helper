<?php

namespace App\Services;

use App\Models\PromptModel;
use App\Support\ChatComposer;

class PromptModelConfig
{
    public const EXPLORE_DEFAULT_MODEL = 'gpt-4o-mini';

    /**
     * @var list<string>
     */
    public const PROMPTS = [
        'interview',
        'docs_retrieval',
        'docs_briefing',
        'card_generation',
        'explore',
    ];

    public function active(string $prompt): string
    {
        $stored = PromptModel::query()->where('prompt', $prompt)->value('model');

        if (is_string($stored) && ChatComposer::isAllowedModel($stored)) {
            return $stored;
        }

        return $this->fallback($prompt);
    }

    public function save(string $prompt, string $model): bool
    {
        if (! in_array($prompt, self::PROMPTS, true) || ! ChatComposer::isAllowedModel($model)) {
            return false;
        }

        PromptModel::query()->updateOrCreate(
            ['prompt' => $prompt],
            ['model' => $model],
        );

        return true;
    }

    private function fallback(string $prompt): string
    {
        if ($prompt === 'explore') {
            return self::EXPLORE_DEFAULT_MODEL;
        }

        if (in_array($prompt, ['docs_retrieval', 'docs_briefing'], true)) {
            $configured = config("chat.prompts.{$prompt}.model");
            $configured = is_string($configured) ? $configured : '';

            if ($configured !== '' && ChatComposer::isAllowedModel($configured)) {
                return $configured;
            }
        }

        return ChatComposer::defaultModel();
    }
}
