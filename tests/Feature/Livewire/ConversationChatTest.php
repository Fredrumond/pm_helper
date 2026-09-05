<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ConversationChat;
use App\Models\Conversation;
use App\Models\User;
use App\Support\ChatComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
        ]);
    }

    public function test_stores_user_message_and_assistant_reply_from_openrouter(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Qual problema você quer resolver?']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout para produto digital')
            ->call('sendMessage')
            ->assertSee('Qual problema você quer resolver?');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Quero um checkout para produto digital',
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Qual problema você quer resolver?',
        ]);
        $this->assertSame(
            'Quero um checkout para produto digital',
            $conversation->fresh()->title
        );
    }

    public function test_shows_openrouter_http_error_in_the_conversation(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Missing Authentication header',
                    'code' => 401,
                ],
            ], 401),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um card')
            ->call('sendMessage')
            ->assertSee('Erro OpenRouter')
            ->assertSee('HTTP 401')
            ->assertSee('Missing Authentication header');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Quero um card',
        ]);
    }

    public function test_persists_card_and_redirects_when_assistant_returns_card_json(): void
    {
        $reply = <<<'TXT'
Card gerado.

<CARD_JSON>
{"title":"Checkout MVP","type":"feature","user_story":"Como comprador, quero pagar","acceptance_criteria":["Dado o carrinho"],"priority":"high"}
</CARD_JSON>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-card-1',
                'model' => 'test/model',
                'provider' => 'TestProvider',
                'usage' => [
                    'prompt_tokens' => 80,
                    'completion_tokens' => 20,
                    'total_tokens' => 100,
                    'cost' => 0,
                ],
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => ['content' => $reply],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Pode gerar o card')
            ->call('sendMessage')
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('cards', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Checkout MVP',
            'status' => 'draft',
        ]);
        $this->assertSame('completed', $conversation->fresh()->status);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'gen-card-1',
            'total_tokens' => 100,
        ]);
    }

    public function test_does_not_send_blank_or_completed_conversation_messages(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', '   ')
            ->call('sendMessage');

        $this->assertSame(0, $conversation->messages()->count());

        $conversation->update(['status' => 'completed']);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation->fresh()])
            ->set('input', 'mais uma ideia')
            ->call('sendMessage');

        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_forbids_access_to_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $owner->id,
            'title' => 'Privada',
        ]);

        Livewire::actingAs($other)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->assertForbidden();
    }

    public function test_escapes_user_message_html_in_the_chat(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Entendi.']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', '<script>alert("xss")</script>')
            ->call('sendMessage')
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_renders_composer_with_models_and_deferred_actions(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->assertSee('Descreva sua necessidade ou ideia')
            ->assertSee('Skills (em breve)', false)
            ->assertSee('Anexar arquivo (em breve)', false)
            ->assertDontSee('Gerar card')
            ->assertSee('bg-white border-t border-gray-200', false)
            ->assertSee('wire:submit="sendMessage"', false)
            ->assertSee('wire:keydown.enter.exact.prevent="sendMessage"', false)
            ->assertDontSee('$wire.set(', false)
            ->assertSee(ChatComposer::findModel(ChatComposer::defaultModel())['name'] ?? ChatComposer::defaultModel());
    }

    public function test_sends_the_selected_model_to_openrouter(): void
    {
        config([
            'chat.models' => [
                ['id' => 'test/model', 'name' => 'Test', 'tier' => 'Free'],
                ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o', 'tier' => 'High'],
            ],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Certo.']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->call('selectModel', 'openai/gpt-4o')
            ->assertSet('selectedModel', 'openai/gpt-4o')
            ->set('input', 'Quero um checkout')
            ->call('sendMessage');

        Http::assertSent(fn (Request $request) => $request['model'] === 'openai/gpt-4o');
    }

    public function test_ignores_unknown_model_selection(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->call('selectModel', 'unknown/model')
            ->assertSet('selectedModel', ChatComposer::defaultModel());
    }
}
