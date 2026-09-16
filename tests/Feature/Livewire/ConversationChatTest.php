<?php

namespace Tests\Feature\Livewire;

use App\Contracts\LlmGateway;
use App\Contracts\ProjectDocsGateway;
use App\Livewire\ConversationChat;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectDocsPathsResult;
use App\Services\ProjectDocsResult;
use App\Support\ChatComposer;
use App\Support\CurrentProject;
use App\Support\ProjectDocsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\FakeLlmGateway;
use Tests\Support\FakeProjectDocsGateway;
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
            ->assertSee('Erro LLM')
            ->assertSee('HTTP 401')
            ->assertSee('Missing Authentication header');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Quero um card',
        ]);
    }

    public function test_uses_fallback_model_silently_when_primary_is_rate_limited(): void
    {
        config([
            'services.openrouter.fallback_models' => [
                'nvidia/nemotron-3-ultra-550b-a55b:free',
            ],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => 'Provider returned error',
                        'code' => 429,
                        'metadata' => [
                            'raw' => 'test/model is temporarily rate-limited upstream.',
                            'provider_error_code' => 'rate_limit_exceeded',
                        ],
                    ],
                ], 429)
                ->push([
                    'choices' => [
                        ['message' => ['content' => 'Qual o impacto disso?']],
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
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Qual o impacto disso?')
            ->assertDontSee('Erro LLM')
            ->assertDontSee('rate-limited')
            ->assertDontSee('429');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Qual o impacto disso?',
        ]);
    }

    public function test_uses_fallback_model_silently_when_primary_returns_empty_response(): void
    {
        config([
            'services.openrouter.fallback_models' => [
                'nvidia/nemotron-3-ultra-550b-a55b:free',
            ],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([
                    'id' => 'gen-empty-1',
                    'model' => 'test/model',
                    'provider' => null,
                    'usage' => null,
                    'choices' => [],
                ], 200)
                ->push([
                    'choices' => [
                        ['message' => ['content' => 'Qual o impacto disso?']],
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
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Qual o impacto disso?')
            ->assertDontSee('Erro LLM')
            ->assertDontSee('resposta vazia');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Qual o impacto disso?',
        ]);
    }

    public function test_does_not_expose_rate_limit_when_fallbacks_are_exhausted(): void
    {
        config([
            'services.openrouter.fallback_models' => [],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Provider returned error',
                    'code' => 429,
                    'metadata' => [
                        'raw' => 'test/model is temporarily rate-limited upstream.',
                        'provider_error_code' => 'rate_limit_exceeded',
                    ],
                ],
            ], 429),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Não consegui continuar agora')
            ->assertDontSee('Erro LLM')
            ->assertDontSee('rate-limited')
            ->assertDontSee('HTTP 429');
    }

    public function test_persists_card_and_redirects_when_assistant_returns_card_json(): void
    {
        $reply = <<<'TXT'
Card gerado.

<CARD_JSON>
{"title":"Checkout MVP","objetivo":"Permitir pagamento no checkout.","regras":["Exibir meios de pagamento."],"onde":["Checkout"],"aceite":["Comprador com carrinho: ao pagar, confirma."],"priority":"high"}
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

    public function test_empty_state_orients_pm_to_single_card_and_defined_epic(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->assertSee('único card')
            ->assertSee('épico definido')
            ->assertDontSee('Descreva uma necessidade ou ideia. O assistente conduz a entrevista')
            ->assertSee('Descreva sua necessidade ou ideia')
            ->assertSee('wire:submit="sendMessage"', false)
            ->assertDontSee('Gerar Card');
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

    public function test_captures_interview_summary_and_shows_generate_card_button(): void
    {
        $reply = <<<'TXT'
Entendi. Podemos gerar o card.

<INTERVIEW_COMPLETE>
<INTERVIEW_SUMMARY>
Problema: checkout sem pagamento
Persona: comprador
</INTERVIEW_SUMMARY>
</INTERVIEW_COMPLETE>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
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
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Entendi. Podemos gerar o card.')
            ->assertDontSee('<INTERVIEW_COMPLETE>', false)
            ->assertSee('Gerar Card');

        $conversation->refresh();
        $this->assertSame('card_generation', $conversation->current_step);
        $this->assertStringContainsString('checkout sem pagamento', (string) $conversation->interview_summary);
        $this->assertTrue($conversation->isInterviewComplete());
        $this->assertSame('in_progress', $conversation->status);
    }

    public function test_generate_card_uses_interview_summary_and_persists_card(): void
    {
        $reply = <<<'TXT'
Card gerado.

<CARD_JSON>
{"title":"Checkout MVP","objetivo":"Permitir pagamento no checkout.","regras":["Exibir meios de pagamento."],"onde":["Checkout"],"aceite":["Comprador com carrinho: ao pagar, confirma."],"priority":"high"}
</CARD_JSON>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-card-step',
                'model' => 'test/model',
                'usage' => [
                    'prompt_tokens' => 30,
                    'completion_tokens' => 20,
                    'total_tokens' => 50,
                    'cost' => 0,
                ],
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'prompt_name' => 'interview',
            'prompt_version' => 'v1',
            'current_step' => 'card_generation',
            'interview_summary' => 'Problema: checkout sem pagamento',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->call('generateCard')
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('cards', [
            'conversation_id' => $conversation->id,
            'title' => 'Checkout MVP',
            'status' => 'draft',
        ]);
        $this->assertSame('completed', $conversation->fresh()->status);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'gen-card-step',
            'step' => 'card_generation',
        ]);

        Http::assertSent(function (Request $request) {
            return str_contains((string) $request['messages'][1]['content'], 'Problema: checkout sem pagamento')
                && count($request['messages']) === 2;
        });
    }

    public function test_generate_card_explains_when_interview_is_not_ready(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->call('generateCard')
            ->assertSee('Ainda não fechei a entrevista');

        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);
    }

    public function test_shows_generate_card_button_when_assistant_closes_interview_without_tags(): void
    {
        $reply = 'Perfeito, tenho tudo que preciso. Vou fazer um resumo do entendimento. Se sim, a entrevista está fechada e você já pode gerar o card no produto.';

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'LP de ebooks',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero uma LP')
            ->call('sendMessage')
            ->assertSee('Gerar Card');

        $this->assertTrue($conversation->fresh()->isInterviewComplete());
        $this->assertSame('card_generation', $conversation->fresh()->current_step);
    }

    public function test_shows_generate_card_button_when_assistant_says_entrevista_finalizada(): void
    {
        $reply = '### Resumo da entrevista\n\nLP com Stripe.\n\nEntrevista finalizada! O botão "Gerar Card" deve aparecer agora no produto.';

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'LP de ebooks',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero uma LP')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee('wire:click="generateCard"', false);

        $this->assertTrue($conversation->fresh()->isInterviewComplete());
    }

    public function test_user_asking_to_generate_card_uses_history_summary_instead_of_interview(): void
    {
        $reply = <<<'TXT'
Card gerado.

<CARD_JSON>
{"title":"LP de ebooks","objetivo":"Vender ebooks pela LP.","regras":["Exibir oferta do ebook."],"onde":["LP"],"aceite":["Visitante: ao comprar, recebe o ebook."],"priority":"high"}
</CARD_JSON>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'LP de ebooks',
            'prompt_name' => 'interview',
            'prompt_version' => 'v1',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Quero uma LP para vender ebooks',
        ]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Perfeito, tenho tudo que preciso. Resumo do entendimento: LP para ebooks de carreira.',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'gere o card')
            ->call('sendMessage')
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertDatabaseHas('cards', [
            'conversation_id' => $conversation->id,
            'title' => 'LP de ebooks',
        ]);

        Http::assertSent(function (Request $request) {
            return str_contains((string) $request['messages'][0]['content'], 'Gere o card imediatamente.')
                && str_contains((string) $request['messages'][1]['content'], 'LP para vender ebooks')
                && count($request['messages']) === 2;
        });
    }

    public function test_scope_too_broad_tag_blocks_card_generation_and_generate_card_shortcut(): void
    {
        $reply = <<<'TXT'
Isso não cabe em um único card. Volte com o card mais definido para retomarmos.

<INTERVIEW_SCOPE_TOO_BROAD></INTERVIEW_SCOPE_TOO_BROAD>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero o fluxo completo de onboarding com KYC, abertura de conta e primeiro investimento')
            ->call('sendMessage')
            ->assertDispatched('interview-scope-too-broad')
            ->assertSee('Isso não cabe em um único card. Volte com o card mais definido para retomarmos.')
            ->assertDontSee('INTERVIEW_SCOPE_TOO_BROAD')
            ->assertDontSee('Gerar Card')
            ->assertSee('wire:submit="sendMessage"', false);

        $conversation->refresh();
        $this->assertSame('scope_too_broad', $conversation->current_step);
        $this->assertNull($conversation->interview_summary);
        $this->assertFalse($conversation->isInterviewComplete());
        $this->assertTrue($conversation->isScopeTooBroad());
        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);

        $component
            ->set('input', 'gere o card')
            ->call('sendMessage')
            ->assertNoRedirect()
            ->assertSee('Volte com um card mais definido')
            ->assertDontSee('Gerar Card');

        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);
        $this->assertNull($conversation->fresh()->interview_summary);
        $this->assertSame('scope_too_broad', $conversation->fresh()->current_step);
        Http::assertSentCount(1);
    }

    public function test_generate_card_refuses_when_scope_is_too_broad_without_calling_openrouter(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
            'current_step' => 'scope_too_broad',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Quero o onboarding completo',
        ]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Isso não cabe em um único card. <INTERVIEW_SCOPE_TOO_BROAD></INTERVIEW_SCOPE_TOO_BROAD>',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->call('generateCard')
            ->assertNoRedirect()
            ->assertSee('Volte com um card mais definido')
            ->assertDontSee('Gerar Card')
            ->assertSee('wire:submit="sendMessage"', false);

        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);
        $this->assertNull($conversation->fresh()->interview_summary);
        Http::assertNothingSent();
    }

    public function test_scope_too_broad_stays_blocked_when_later_message_signals_interview_complete(): void
    {
        $reply = <<<'TXT'
Entendi. Podemos gerar o card.

<INTERVIEW_COMPLETE>
<INTERVIEW_SUMMARY>
Problema: checkout sem pagamento
</INTERVIEW_SUMMARY>
</INTERVIEW_COMPLETE>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
            'prompt_name' => 'interview',
            'prompt_version' => 'v4',
            'current_step' => 'scope_too_broad',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Pode fechar a entrevista mesmo assim')
            ->call('sendMessage')
            ->assertDispatched('interview-scope-too-broad')
            ->assertSee('Entendi. Podemos gerar o card.')
            ->assertDontSee('Gerar Card')
            ->assertDontSee('<INTERVIEW_COMPLETE>', false);

        $conversation->refresh();
        $this->assertSame('scope_too_broad', $conversation->current_step);
        $this->assertNull($conversation->interview_summary);
        $this->assertFalse($conversation->isInterviewComplete());
        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);
    }

    public function test_scope_too_broad_with_inner_tag_content_still_blocks_generation(): void
    {
        $reply = <<<'TXT'
Isso não cabe em um único card. Volte com o card mais definido.

<INTERVIEW_SCOPE_TOO_BROAD>fluxo de onboarding completo</INTERVIEW_SCOPE_TOO_BROAD>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero o onboarding completo')
            ->call('sendMessage')
            ->assertDispatched('interview-scope-too-broad')
            ->assertSee('Isso não cabe em um único card. Volte com o card mais definido.')
            ->assertDontSee('INTERVIEW_SCOPE_TOO_BROAD')
            ->assertDontSee('Gerar Card');

        $conversation->refresh();
        $this->assertSame('scope_too_broad', $conversation->current_step);
        $this->assertNull($conversation->interview_summary);
        $this->assertFalse($conversation->isInterviewComplete());
        $this->assertTrue($conversation->isScopeTooBroad());
    }

    public function test_conversation_header_does_not_show_ready_when_scope_is_too_broad(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
            'current_step' => 'scope_too_broad',
        ]);

        $this->actingAs($user)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertDontSee('Pronto para gerar o card')
            ->assertSee('Escopo amplo demais')
            ->assertSee('wire:submit="sendMessage"', false);
    }

    public function test_conversation_header_listens_for_scope_too_broad_during_interview(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Onboarding',
        ]);

        $this->actingAs($user)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Interview em andamento')
            ->assertSee('x-on:interview-scope-too-broad.window', false)
            ->assertSee('Escopo amplo demais');
    }

    public function test_closing_interview_without_project_does_not_call_docs_gateway(): void
    {
        $fake = $this->bindFakeProjectDocsGateway();

        $this->fakeInterviewCompleteReply();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertDontSee('Revisão carregada')
            ->assertDontSee('Tentar revisão novamente')
            ->assertDontSee('Não há informações adicionais em /docs');

        $this->assertSame([], $fake->calls);
        $this->assertNull(ProjectDocsReview::get($conversation->id));
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_closing_interview_with_project_stores_ok_review_and_notifies(): void
    {
        $project = Project::factory()->create([
            'repository' => 'acme/checkout',
            'branch' => 'develop',
        ]);
        $fake = $this->bindFakeProjectDocsGateway(
            ProjectDocsResult::ok("# Regras secretas\nPagamento obrigatório.", 3, 1),
        );

        $this->fakeInterviewCompleteReply();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee('Revisão carregada (3 arquivos). Você já pode gerar o card.')
            ->assertDontSee('Tentar revisão novamente')
            ->assertDontSee('Regras secretas')
            ->assertDontSee('Pagamento obrigatório');

        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'develop'],
        ], $fake->calls);
        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame([
            'status' => 'ok',
            'content' => "# Regras secretas\nPagamento obrigatório.",
            'project_id' => $project->id,
            'repository' => 'acme/checkout',
            'branch' => 'develop',
            'file_count' => 3,
            'skipped_non_text' => 1,
            'chars' => mb_strlen("# Regras secretas\nPagamento obrigatório.", 'UTF-8'),
        ], ProjectDocsReview::get($conversation->id));
    }

    public function test_empty_review_shows_retry_and_retry_rereads_without_generating_card(): void
    {
        $project = Project::factory()->create(['repository' => 'acme/checkout']);
        $fake = $this->bindFakeProjectDocsGateway(
            ProjectDocsResult::empty(),
            ProjectDocsResult::ok('# Regras', 1, 0),
        );
        $this->fakeInterviewCompleteReply();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        $component = Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee('Não há informações adicionais em /docs')
            ->assertSee('Tentar revisão novamente');

        $this->assertSame('empty', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertNull(ProjectDocsReview::get($conversation->id)['content']);
        Http::assertSentCount(1);

        $component
            ->call('retryProjectDocsReview')
            ->assertNoRedirect()
            ->assertSee('Revisão carregada (1 arquivo)')
            ->assertDontSee('Tentar revisão novamente')
            ->assertSee('Gerar Card');

        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'main'],
            ['repository' => 'acme/checkout', 'branch' => 'main'],
        ], $fake->calls);
        $this->assertSame('ok', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertSame('# Regras', ProjectDocsReview::get($conversation->id)['content']);
        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);
        Http::assertSentCount(1);
    }

    public function test_too_large_review_notifies_without_retry(): void
    {
        $project = Project::factory()->create(['repository' => 'acme/checkout']);
        $tooLarge = $this->bindFakeProjectDocsGateway(ProjectDocsResult::tooLarge(8, 0, 120_000));
        $this->fakeInterviewCompleteReply();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee('excede o teto')
            ->assertDontSee('Tentar revisão novamente');

        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'main'],
        ], $tooLarge->calls);
        $this->assertSame('too_large', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertNull(ProjectDocsReview::get($conversation->id)['content']);
    }

    public function test_failed_review_shows_retry_and_retry_rereads_without_generating_card(): void
    {
        $project = Project::factory()->create(['repository' => 'acme/checkout']);
        $fake = $this->bindFakeProjectDocsGateway(
            ProjectDocsResult::failed(ProjectDocsResult::ERROR_TIMEOUT),
            ProjectDocsResult::ok('# Regras', 1, 0),
        );

        $this->fakeInterviewCompleteReply();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        $component = Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee('Não foi possível realizar a revisão final')
            ->assertSee('Tentar revisão novamente');

        $this->assertSame('failed', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertNull(ProjectDocsReview::get($conversation->id)['content']);
        Http::assertSentCount(1);

        $component
            ->call('retryProjectDocsReview')
            ->assertNoRedirect()
            ->assertSee('Revisão carregada (1 arquivo)')
            ->assertDontSee('Tentar revisão novamente')
            ->assertSee('Gerar Card');

        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'main'],
            ['repository' => 'acme/checkout', 'branch' => 'main'],
        ], $fake->calls);
        $this->assertSame('ok', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertSame('# Regras', ProjectDocsReview::get($conversation->id)['content']);
        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);
        Http::assertSentCount(1);
    }

    public function test_retry_is_ignored_when_session_status_is_ok(): void
    {
        $project = Project::factory()->create(['repository' => 'acme/checkout']);
        $fake = $this->bindFakeProjectDocsGateway();

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'current_step' => 'card_generation',
            'interview_summary' => 'Problema: checkout',
        ]);

        $payload = ProjectDocsReview::payloadFrom($project, ProjectDocsResult::ok('doc', 1, 0));

        $this->session([
            CurrentProject::SESSION_KEY => $project->id,
            ProjectDocsReview::sessionKey($conversation->id) => $payload,
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->call('retryProjectDocsReview')
            ->assertDontSee('Tentar revisão novamente');

        $this->assertSame([], $fake->calls);
    }

    public function test_generate_card_does_not_reread_project_docs(): void
    {
        $project = Project::factory()->create(['repository' => 'acme/checkout']);
        $fake = $this->bindFakeProjectDocsGateway();

        $reply = <<<'TXT'
Card gerado.

<CARD_JSON>
{"title":"Checkout MVP","objetivo":"Permitir pagamento no checkout.","regras":["Exibir meios de pagamento."],"onde":["Checkout"],"aceite":["Comprador com carrinho: ao pagar, confirma."],"priority":"high"}
</CARD_JSON>
TXT;

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $reply]],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'prompt_name' => 'interview',
            'prompt_version' => 'v1',
            'current_step' => 'card_generation',
            'interview_summary' => 'Problema: checkout sem pagamento',
        ]);

        $this->session([
            CurrentProject::SESSION_KEY => $project->id,
            ProjectDocsReview::sessionKey($conversation->id) => ProjectDocsReview::payloadFrom(
                $project,
                ProjectDocsResult::ok('# Regras', 1, 0),
            ),
        ]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->call('generateCard')
            ->assertRedirect(route('conversations.show', $conversation));

        $this->assertSame([], $fake->calls);
        Http::assertSent(function (Request $request) {
            $docs = (string) $request['messages'][1]['content'];
            $summary = (string) $request['messages'][2]['content'];

            return count($request['messages']) === 3
                && str_contains($docs, 'Regras do projeto (/docs)')
                && str_contains($docs, '# Regras')
                && str_contains($summary, 'Problema: checkout sem pagamento');
        });
    }

    public function test_closing_interview_retrieves_filtered_docs_via_two_turns(): void
    {
        $project = Project::factory()->create([
            'repository' => 'acme/checkout',
            'branch' => 'develop',
        ]);
        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queuePaths(ProjectDocsPathsResult::ok([
            'docs/regras/pagamento.md',
            'docs/adr/0001.md',
        ]));
        $filtered = ProjectDocsResult::ok('# Pagamento obrigatório', 1, 0);
        $docs->queueReadByPaths($filtered);

        $llm = $this->bindFakeLlmGateway();
        $llm->queueChat($this->interviewCompleteReply());
        $llm->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee(ProjectDocsReview::assistantMessage($filtered))
            ->assertDontSee('Tentar revisão novamente')
            ->assertDontSee('# Pagamento obrigatório');

        $this->assertSame([], $docs->calls);
        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'develop'],
        ], $docs->listPathsCalls);
        $this->assertSame([
            [
                'repository' => 'acme/checkout',
                'paths' => ['docs/regras/pagamento.md'],
                'branch' => 'develop',
            ],
        ], $docs->readByPathsCalls);
        $this->assertCount(1, $llm->completeCalls);
        $this->assertSame('docs_retrieval', $llm->completeCalls[0]['step']);
        $this->assertSame($conversation->id, $llm->completeCalls[0]['conversation']?->id);
        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame('ok', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertSame('# Pagamento obrigatório', ProjectDocsReview::get($conversation->id)['content']);
    }

    public function test_docs_retrieval_falls_back_to_full_dump_when_llm_fails(): void
    {
        $project = Project::factory()->create(['repository' => 'acme/checkout']);
        $fallback = ProjectDocsResult::ok('# Dump completo', 4, 0);
        $docs = $this->bindFakeProjectDocsGateway($fallback);
        $docs->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']));

        $llm = $this->bindFakeLlmGateway();
        $llm->queueChat($this->interviewCompleteReply());
        $llm->queueComplete(new RuntimeException('LLM fora do ar'));

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee(ProjectDocsReview::assistantMessage($fallback))
            ->assertDontSee('# Dump completo');

        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'main'],
        ], $docs->calls);
        $this->assertSame([], $docs->readByPathsCalls);
        $this->assertSame('ok', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertSame('# Dump completo', ProjectDocsReview::get($conversation->id)['content']);
        $this->assertSame(3, $conversation->messages()->count());
        $this->assertCount(1, $llm->completeCalls);
        $this->assertSame('docs_retrieval', $llm->completeCalls[0]['step']);
    }

    public function test_retry_uses_docs_retrieval_and_keeps_assistant_message(): void
    {
        $project = Project::factory()->create(['repository' => 'acme/checkout']);
        $empty = ProjectDocsResult::empty();
        $ok = ProjectDocsResult::ok('# Regras', 1, 0);
        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']));
        $docs->queueReadByPaths($empty);
        $docs->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']));
        $docs->queueReadByPaths($ok);

        $llm = $this->bindFakeLlmGateway();
        $llm->queueChat($this->interviewCompleteReply());
        $llm->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');
        $llm->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
        ]);

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        $component = Livewire::actingAs($user)
            ->test(ConversationChat::class, ['conversation' => $conversation])
            ->set('input', 'Quero um checkout')
            ->call('sendMessage')
            ->assertSee('Gerar Card')
            ->assertSee(ProjectDocsReview::assistantMessage($empty))
            ->assertSee('Tentar revisão novamente');

        $component
            ->call('retryProjectDocsReview')
            ->assertNoRedirect()
            ->assertSee(ProjectDocsReview::assistantMessage($ok))
            ->assertDontSee('Tentar revisão novamente')
            ->assertSee('Gerar Card');

        $this->assertSame([], $docs->calls);
        $this->assertCount(2, $docs->listPathsCalls);
        $this->assertCount(2, $docs->readByPathsCalls);
        $this->assertSame('ok', ProjectDocsReview::get($conversation->id)['status']);
        $this->assertSame('# Regras', ProjectDocsReview::get($conversation->id)['content']);
        $this->assertDatabaseMissing('cards', [
            'conversation_id' => $conversation->id,
        ]);
    }

    private function bindFakeProjectDocsGateway(ProjectDocsResult ...$results): FakeProjectDocsGateway
    {
        $fake = new FakeProjectDocsGateway;

        foreach ($results as $result) {
            $fake->queue($result);
        }

        $this->app->instance(ProjectDocsGateway::class, $fake);

        return $fake;
    }

    private function bindFakeLlmGateway(): FakeLlmGateway
    {
        $fake = new FakeLlmGateway;
        $this->app->instance(LlmGateway::class, $fake);

        return $fake;
    }

    private function interviewCompleteReply(): string
    {
        return <<<'TXT'
Entendi. Podemos gerar o card.

<INTERVIEW_COMPLETE>
<INTERVIEW_SUMMARY>
Problema: checkout sem pagamento
Persona: comprador
</INTERVIEW_SUMMARY>
</INTERVIEW_COMPLETE>
TXT;
    }

    private function fakeInterviewCompleteReply(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => $this->interviewCompleteReply()]],
                ],
            ], 200),
        ]);
    }
}
