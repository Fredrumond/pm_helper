<?php

namespace App\Contracts;

use App\Models\Conversation;

interface LlmGateway
{
    public function chat(Conversation $conversation, ?string $model = null): string;

    public function generateCard(Conversation $conversation, string $summary, ?string $model = null): string;
}
