<?php

namespace Tests\Feature\Services;

use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_bearer_token_and_returns_assistant_content(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
        ]);

        Event::fake([MessageLogged::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-test-1',
                'model' => 'test/model',
                'provider' => 'TestProvider',
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 4,
                    'total_tokens' => 14,
                    'cost' => 0.0012,
                    'prompt_tokens_details' => [
                        'cached_tokens' => 3,
                    ],
                ],
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => ['content' => 'Olá!'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Teste',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Quero um card',
        ]);
        $conversation->load('messages');

        $content = (new OpenRouterService)->chat($conversation);

        $this->assertSame('Olá!', $content);

        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'gen-test-1',
            'model' => 'test/model',
            'step' => 'interview',
            'prompt_version' => 'v2',
            'provider' => 'TestProvider',
            'prompt_tokens' => 10,
            'completion_tokens' => 4,
            'total_tokens' => 14,
            'cached_tokens' => 3,
            'finish_reason' => 'stop',
        ]);
        $this->assertSame(0.0012, (float) LlmUsage::query()->first()->cost);
        $this->assertSame(64, strlen((string) LlmUsage::query()->first()->prompt_hash));
        $this->assertSame('v2', $conversation->fresh()->prompt_version);
        $this->assertSame('interview', $conversation->fresh()->prompt_name);
        $this->assertSame('interview', $conversation->fresh()->current_step);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($conversation) {
            return $log->level === 'info'
                && $log->message === 'OpenRouter API response'
                && $log->context['conversation_id'] === $conversation->id
                && $log->context['status'] === 200
                && $log->context['prompt'] === 'interview@v2'
                && $log->context['step'] === 'interview'
                && $log->context['model'] === 'test/model'
                && $log->context['id'] === 'gen-test-1'
                && $log->context['provider'] === 'TestProvider'
                && $log->context['usage']['total_tokens'] === 14
                && $log->context['finish_reason'] === 'stop'
                && ! array_key_exists('payload', $log->context);
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'test/model'
                && $request['messages'][0]['role'] === 'system'
                && str_contains((string) $request['messages'][0]['content'], 'NUNCA gere um card, JSON de card')
                && $request['messages'][1]['content'] === 'Quero um card';
        });
    }

    public function test_pins_prompt_version_for_the_rest_of_the_conversation(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
            'chat.prompts.interview.version' => 'v1',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Ok']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Teste',
        ]);

        (new OpenRouterService)->chat($conversation);
        $this->assertSame('v1', $conversation->fresh()->prompt_version);

        config(['chat.prompts.interview.version' => 'missing']);

        (new OpenRouterService)->chat($conversation->fresh()->load('messages'));

        Http::assertSentCount(2);
        $this->assertSame('v1', $conversation->fresh()->prompt_version);
        $this->assertSame(['v1', 'v1'], LlmUsage::query()->pluck('prompt_version')->all());
    }

    public function test_uses_allowed_override_model(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
            'chat.models' => [
                ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o', 'tier' => 'High'],
            ],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Ok']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Teste',
        ]);

        (new OpenRouterService)->chat($conversation, 'openai/gpt-4o');

        Http::assertSent(fn (Request $request) => $request['model'] === 'openai/gpt-4o');
    }

    public function test_falls_back_to_configured_model_when_override_is_unknown(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Ok']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Teste',
        ]);

        (new OpenRouterService)->chat($conversation, 'unknown/model');

        Http::assertSent(fn (Request $request) => $request['model'] === 'test/model');
    }

    public function test_throws_when_api_key_is_missing(): void
    {
        config(['services.openrouter.api_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENROUTER_API_KEY');

        new OpenRouterService;
    }

    public function test_throws_http_status_and_openrouter_message_when_request_fails(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
        ]);

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
            'title' => 'Teste',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenRouter retornou HTTP 401: Missing Authentication header');

        try {
            (new OpenRouterService)->chat($conversation);
        } finally {
            $this->assertDatabaseCount('llm_usages', 0);
        }
    }

    public function test_records_usage_with_zeros_when_api_omits_usage_block(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Ok']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Teste',
        ]);

        (new OpenRouterService)->chat($conversation);

        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'model' => 'test/model',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cached_tokens' => 0,
        ]);
    }

    public function test_uses_discovery_prompt_when_mode_is_discovery(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
            'chat.mode' => 'discovery',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Ok']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Teste',
        ]);

        (new OpenRouterService)->chat($conversation);

        $this->assertSame('discovery', $conversation->fresh()->prompt_name);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'step' => 'discovery',
        ]);

        Http::assertSent(fn (Request $request) => str_contains(
            (string) $request['messages'][0]['content'],
            'NUNCA gere o card imediatamente.'
        ));
    }

    public function test_keeps_legacy_discovery_prompt_when_only_version_is_pinned(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
            'chat.mode' => 'interview',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Ok']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Legado',
            'prompt_version' => 'v1',
        ]);

        (new OpenRouterService)->chat($conversation);

        Http::assertSent(fn (Request $request) => str_contains(
            (string) $request['messages'][0]['content'],
            'NUNCA gere o card imediatamente.'
        ));
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'step' => 'discovery',
        ]);
    }

    public function test_generate_card_sends_interview_summary_without_chat_history(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'test/model',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-card-1',
                'model' => 'test/model',
                'provider' => 'TestProvider',
                'usage' => [
                    'prompt_tokens' => 40,
                    'completion_tokens' => 20,
                    'total_tokens' => 60,
                    'cost' => 0.002,
                ],
                'choices' => [
                    ['message' => ['content' => '<CARD_JSON>{"title":"Checkout"}</CARD_JSON>']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'prompt_name' => 'interview',
            'prompt_version' => 'v1',
            'interview_summary' => 'Problema: checkout sem pagamento',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Quero um checkout',
        ]);

        $content = (new OpenRouterService)->generateCard(
            $conversation,
            'Problema: checkout sem pagamento',
        );

        $this->assertStringContainsString('<CARD_JSON>', $content);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'gen-card-1',
            'step' => 'card_generation',
            'prompt_version' => 'v1',
        ]);

        Http::assertSent(function (Request $request) {
            return $request['messages'][0]['role'] === 'system'
                && str_contains((string) $request['messages'][0]['content'], 'Gere o card imediatamente.')
                && $request['messages'][1]['role'] === 'user'
                && str_contains((string) $request['messages'][1]['content'], 'Problema: checkout sem pagamento')
                && count($request['messages']) === 2;
        });
    }
}
