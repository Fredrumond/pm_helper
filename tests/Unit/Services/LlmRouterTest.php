<?php

namespace Tests\Unit\Services;

use App\Contracts\LlmGateway;
use App\Models\Conversation;
use App\Services\LlmRouter;
use Tests\TestCase;

class LlmRouterTest extends TestCase
{
    public function test_delegates_chat_to_default_adapter_when_no_prefix_matches(): void
    {
        $default = new FakeLlmGateway('default');
        $other = new FakeLlmGateway('other');
        $router = new LlmRouter($default, ['anthropic/' => $other]);
        $conversation = new Conversation;

        $this->assertSame('default:chat', $router->chat($conversation, 'openai/gpt-4o'));
        $this->assertSame($conversation, $default->lastConversation);
        $this->assertSame('openai/gpt-4o', $default->lastModel);
        $this->assertNull($other->lastConversation);

        $this->assertSame('default:chat', $router->chat($conversation, null));
        $this->assertNull($default->lastModel);
    }

    public function test_delegates_to_matching_prefix_adapter(): void
    {
        $default = new FakeLlmGateway('default');
        $anthropic = new FakeLlmGateway('anthropic');
        $router = new LlmRouter($default, ['anthropic/' => $anthropic]);
        $conversation = new Conversation;

        $this->assertSame('anthropic:chat', $router->chat($conversation, 'anthropic/claude-3.5-sonnet'));
        $this->assertSame('anthropic:card', $router->generateCard($conversation, 'resumo', 'anthropic/claude'));
        $this->assertNull($default->lastConversation);
        $this->assertSame($conversation, $anthropic->lastConversation);
        $this->assertSame('resumo', $anthropic->lastSummary);
    }

    public function test_generate_card_uses_default_when_model_does_not_match(): void
    {
        $default = new FakeLlmGateway('default');
        $router = new LlmRouter($default, ['ollama/' => new FakeLlmGateway('ollama')]);
        $conversation = new Conversation;

        $this->assertSame('default:card', $router->generateCard($conversation, 'resumo', 'openai/gpt-4o'));
        $this->assertSame('resumo', $default->lastSummary);
        $this->assertSame('openai/gpt-4o', $default->lastModel);
    }

    public function test_complete_prompt_delegates_to_matching_adapter(): void
    {
        $default = new FakeLlmGateway('default');
        $anthropic = new FakeLlmGateway('anthropic');
        $router = new LlmRouter($default, ['anthropic/' => $anthropic]);
        $messages = [['role' => 'user', 'content' => 'índice']];

        $conversation = new Conversation;

        $this->assertSame('anthropic:complete', $router->completePrompt($messages, 'anthropic/claude', 'docs_retrieval', $conversation));
        $this->assertSame($messages, $anthropic->lastMessages);
        $this->assertSame('anthropic/claude', $anthropic->lastModel);
        $this->assertSame('docs_retrieval', $anthropic->lastStep);
        $this->assertSame($conversation, $anthropic->lastConversation);
        $this->assertNull($default->lastMessages);
    }
}

class FakeLlmGateway implements LlmGateway
{
    public ?Conversation $lastConversation = null;

    public ?string $lastModel = null;

    public ?string $lastSummary = null;

    /** @var list<array{role: string, content: string}>|null */
    public ?array $lastMessages = null;

    public ?string $lastStep = null;

    public function __construct(private readonly string $name) {}

    public function chat(Conversation $conversation, ?string $model = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastModel = $model;

        return "{$this->name}:chat";
    }

    public function generateCard(Conversation $conversation, string $summary, ?string $model = null, ?string $projectDocs = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastSummary = $summary;
        $this->lastModel = $model;

        return "{$this->name}:card";
    }

    public function completePrompt(
        array $messages,
        ?string $model = null,
        string $step = 'docs_retrieval',
        ?Conversation $conversation = null,
    ): string {
        $this->lastMessages = $messages;
        $this->lastModel = $model;
        $this->lastStep = $step;
        $this->lastConversation = $conversation;

        return "{$this->name}:complete";
    }
}
