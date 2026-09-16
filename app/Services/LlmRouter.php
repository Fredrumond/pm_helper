<?php

namespace App\Services;

use App\Contracts\LlmGateway;
use App\Models\Conversation;

class LlmRouter implements LlmGateway
{
    /**
     * @param  array<string, LlmGateway>  $adapters
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

    private function resolve(?string $model): LlmGateway
    {
        foreach ($this->adapters as $prefix => $adapter) {
            if ($model && str_starts_with($model, $prefix)) {
                return $adapter;
            }
        }

        return $this->default;
    }
}
