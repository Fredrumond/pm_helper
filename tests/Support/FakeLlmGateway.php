<?php

namespace Tests\Support;

use App\Contracts\LlmGateway;
use App\Models\Conversation;
use RuntimeException;
use Throwable;

class FakeLlmGateway implements LlmGateway
{
    /** @var list<string> */
    public array $chatReplies = [];

    /** @var list<string|Throwable> */
    public array $completeReplies = [];

    /** @var list<array{messages: list<array{role: string, content: string}>, model: ?string, step: string, conversation: ?Conversation}> */
    public array $completeCalls = [];

    public ?Conversation $lastConversation = null;

    public ?string $lastModel = null;

    public function queueChat(string $reply): self
    {
        $this->chatReplies[] = $reply;

        return $this;
    }

    public function queueComplete(string|Throwable $reply): self
    {
        $this->completeReplies[] = $reply;

        return $this;
    }

    public function chat(Conversation $conversation, ?string $model = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastModel = $model;

        if ($this->chatReplies === []) {
            throw new RuntimeException('FakeLlmGateway sem resposta de chat');
        }

        return array_shift($this->chatReplies);
    }

    public function generateCard(Conversation $conversation, string $summary, ?string $model = null, ?string $projectDocs = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastModel = $model;

        return 'card';
    }

    public function completePrompt(
        array $messages,
        ?string $model = null,
        string $step = 'docs_retrieval',
        ?Conversation $conversation = null,
    ): string {
        $this->completeCalls[] = [
            'messages' => $messages,
            'model' => $model,
            'step' => $step,
            'conversation' => $conversation,
        ];
        $this->lastConversation = $conversation;
        $this->lastModel = $model;

        if ($this->completeReplies === []) {
            throw new RuntimeException('FakeLlmGateway sem resposta de complete');
        }

        $reply = array_shift($this->completeReplies);

        if ($reply instanceof Throwable) {
            throw $reply;
        }

        return $reply;
    }
}
