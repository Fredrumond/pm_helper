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
}

class FakeLlmGateway implements LlmGateway
{
    public ?Conversation $lastConversation = null;

    public ?string $lastModel = null;

    public ?string $lastSummary = null;

    public function __construct(private readonly string $name) {}

    public function chat(Conversation $conversation, ?string $model = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastModel = $model;

        return "{$this->name}:chat";
    }

    public function generateCard(Conversation $conversation, string $summary, ?string $model = null): string
    {
        $this->lastConversation = $conversation;
        $this->lastSummary = $summary;
        $this->lastModel = $model;

        return "{$this->name}:card";
    }
}
