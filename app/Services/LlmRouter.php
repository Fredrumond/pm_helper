<?php

namespace App\Services;

use App\Contracts\LlmGateway;
use App\Models\Conversation;

class LlmRouter implements LlmGateway
{
    /**
     * @param  array<string, LlmGateway>  $adapters  Provedor do catálogo (`openai`) => adapter. OpenRouter é o default.
     */
    public function __construct(
        private readonly LlmGateway $default,
        private readonly array $adapters = [],
    ) {}

    public function chat(Conversation $conversation, ?string $model = null): string
    {
        return $this->resolve($model)->chat($conversation, $model);
    }

    public function generateCard(Conversation $conversation, string $summary, ?string $model = null, ?string $projectDocs = null): string
    {
        return $this->resolve($model)->generateCard($conversation, $summary, $model, $projectDocs);
    }

    public function completePrompt(
        array $messages,
        ?string $model = null,
        string $step = 'docs_retrieval',
        ?Conversation $conversation = null,
    ): string {
        return $this->resolve($model)->completePrompt($messages, $model, $step, $conversation);
    }

    private function resolve(?string $model): LlmGateway
    {
        if (! is_string($model) || $model === '') {
            return $this->default;
        }

        $record = app(LlmModelCatalog::class)->findActive($model);

        if ($record !== null && isset($this->adapters[$record->provider])) {
            return $this->adapters[$record->provider];
        }

        return $this->default;
    }
}
