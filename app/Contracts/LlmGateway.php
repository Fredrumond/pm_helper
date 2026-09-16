<?php

namespace App\Contracts;

use App\Models\Conversation;

interface LlmGateway
{
    public function chat(Conversation $conversation, ?string $model = null): string;

    public function generateCard(Conversation $conversation, string $summary, ?string $model = null, ?string $projectDocs = null): string;

    /**
     * Completa um prompt avulso, sem o histórico da conversa.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function completePrompt(
        array $messages,
        ?string $model = null,
        string $step = 'docs_retrieval',
        ?Conversation $conversation = null,
    ): string;
}
