<?php

namespace Tests\Feature\Services;

use App\Exceptions\LlmTemporarilyUnavailableException;
use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use App\Prompts\SystemPromptCatalog;
use App\Services\Adapters\OpenAiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_bearer_token_and_returns_assistant_content(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Event::fake([MessageLogged::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-1',
                'model' => 'gpt-4o-mini-2024-07-18',
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 4,
                    'total_tokens' => 14,
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

        $content = (new OpenAiAdapter)->chat($conversation);

        $this->assertSame('Olá!', $content);

        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'chatcmpl-test-1',
            'model' => 'gpt-4o-mini-2024-07-18',
            'step' => 'interview',
            'prompt_version' => 'v4',
            'provider' => 'openai',
            'prompt_tokens' => 10,
            'completion_tokens' => 4,
            'total_tokens' => 14,
            'cached_tokens' => 3,
            'finish_reason' => 'stop',
        ]);
        $this->assertEqualsWithDelta(0.00000368, (float) LlmUsage::query()->first()->cost, 0.00000001);
        $this->assertSame('v4', $conversation->fresh()->prompt_version);
        $this->assertSame('interview', $conversation->fresh()->prompt_name);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($conversation) {
            return $log->level === 'info'
                && $log->message === 'OpenAI API response'
                && $log->context['conversation_id'] === $conversation->id
                && $log->context['status'] === 200
                && $log->context['step'] === 'interview'
                && $log->context['id'] === 'chatcmpl-test-1'
                && $log->context['finish_reason'] === 'stop';
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test-key')
                && $request['model'] === 'gpt-4o-mini'
                && $request['messages'][0]['role'] === 'system'
                && $request['messages'][1]['content'] === 'Quero um card';
        });
    }

    public function test_does_not_send_openrouter_specific_headers(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Ok']]],
            ], 200),
        ]);

        $conversation = $this->conversationWithUserMessage();
        (new OpenAiAdapter)->chat($conversation);

        Http::assertSent(function (Request $request) {
            return ! $request->hasHeader('HTTP-Referer')
                && ! $request->hasHeader('X-Title');
        });
    }

    public function test_throws_when_api_key_is_missing(): void
    {
        config(['services.openai.api_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        new OpenAiAdapter;
    }

    public function test_throws_http_status_and_openai_message_when_request_fails(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key',
                ],
            ], 401),
        ]);

        $conversation = $this->conversationWithUserMessage();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI retornou HTTP 401: Incorrect API key provided');

        try {
            (new OpenAiAdapter)->chat($conversation);
        } finally {
            $this->assertDatabaseCount('llm_usages', 0);
        }
    }

    public function test_throws_unavailable_when_rate_limited(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded',
                    'type' => 'requests',
                    'code' => 'rate_limit_exceeded',
                ],
            ], 429),
        ]);

        $this->expectException(LlmTemporarilyUnavailableException::class);
        $this->expectExceptionMessage('Não consegui continuar agora');

        try {
            (new OpenAiAdapter)->chat($this->conversationWithUserMessage());
        } finally {
            $this->assertDatabaseCount('llm_usages', 0);
        }
    }

    public function test_uses_allowed_override_model(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
            'chat.models' => [
                ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'tier' => 'OpenAI'],
            ],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Ok']]],
            ], 200),
        ]);

        $conversation = $this->conversationWithUserMessage();
        (new OpenAiAdapter)->chat($conversation, 'gpt-4o');

        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-4o');
    }

    public function test_falls_back_to_configured_model_when_override_is_unknown(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Ok']]],
            ], 200),
        ]);

        $conversation = $this->conversationWithUserMessage();
        (new OpenAiAdapter)->chat($conversation, 'modelo-desconhecido');

        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-4o-mini');
    }

    public function test_records_usage_with_zeros_when_api_omits_usage_block(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Ok']]],
            ], 200),
        ]);

        $conversation = $this->conversationWithUserMessage();
        (new OpenAiAdapter)->chat($conversation);

        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cached_tokens' => 0,
        ]);
    }

    public function test_generate_card_sends_summary_without_chat_history(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-card-1',
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

        $content = (new OpenAiAdapter)->generateCard(
            $conversation,
            'Problema: checkout sem pagamento',
        );

        $this->assertStringContainsString('<CARD_JSON>', $content);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'chatcmpl-card-1',
            'step' => 'card_generation',
            'prompt_version' => 'v3',
        ]);

        Http::assertSent(function (Request $request) {
            return $request['messages'][0]['role'] === 'system'
                && str_contains((string) $request['messages'][0]['content'], 'Gere o card imediatamente.')
                && $request['messages'][1]['role'] === 'user'
                && str_contains((string) $request['messages'][1]['content'], 'Problema: checkout sem pagamento')
                && count($request['messages']) === 2;
        });
    }

    public function test_complete_prompt_uses_docs_retrieval_step_by_default(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Event::fake([MessageLogged::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-retrieval-1',
                'choices' => [
                    ['message' => ['content' => '{"paths": []}']],
                ],
            ], 200),
        ]);

        $content = (new OpenAiAdapter)->completePrompt([
            ['role' => 'user', 'content' => 'índice'],
        ], 'gpt-4o-mini');

        $this->assertSame('{"paths": []}', $content);
        $this->assertDatabaseCount('llm_usages', 0);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->level === 'info'
                && $log->message === 'OpenAI API response'
                && ($log->context['step'] ?? null) === 'docs_retrieval'
                && ($log->context['prompt'] ?? null) === 'docs_retrieval@v1';
        });
    }

    public function test_complete_prompt_records_docs_retrieval_usage_when_conversation_is_present(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-retrieval-1',
                'model' => 'gpt-4o-mini',
                'usage' => [
                    'prompt_tokens' => 20,
                    'completion_tokens' => 5,
                    'total_tokens' => 25,
                ],
                'choices' => [
                    ['message' => ['content' => '{"paths": ["docs/regras/pagamento.md"]}']],
                ],
            ], 200),
        ]);

        $conversation = $this->conversationWithUserMessage();
        $prompt = (new SystemPromptCatalog)->current('docs_retrieval');

        $content = (new OpenAiAdapter)->completePrompt(
            [['role' => 'user', 'content' => 'índice']],
            'gpt-4o-mini',
            'docs_retrieval',
            $conversation,
        );

        $this->assertSame('{"paths": ["docs/regras/pagamento.md"]}', $content);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'chatcmpl-retrieval-1',
            'step' => 'docs_retrieval',
            'prompt_version' => 'v1',
            'prompt_hash' => $prompt->hash,
            'total_tokens' => 25,
        ]);
    }

    public function test_complete_prompt_propagates_docs_briefing_step(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Event::fake([MessageLogged::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-briefing-1',
                'choices' => [
                    ['message' => ['content' => 'A documentação confirma o pagamento.']],
                ],
            ], 200),
        ]);

        $content = (new OpenAiAdapter)->completePrompt(
            [['role' => 'user', 'content' => 'docs']],
            'gpt-4o-mini',
            'docs_briefing',
        );

        $this->assertSame('A documentação confirma o pagamento.', $content);
        $this->assertDatabaseCount('llm_usages', 0);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->level === 'info'
                && $log->message === 'OpenAI API response'
                && ($log->context['step'] ?? null) === 'docs_briefing'
                && ($log->context['prompt'] ?? null) === 'docs_briefing@v1';
        });
    }

    public function test_complete_prompt_records_docs_briefing_usage_when_conversation_is_present(): void
    {
        config([
            'services.openai.api_key' => 'sk-test-key',
            'services.openai.model' => 'gpt-4o-mini',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-briefing-1',
                'model' => 'gpt-4o-mini',
                'usage' => [
                    'prompt_tokens' => 40,
                    'completion_tokens' => 12,
                    'total_tokens' => 52,
                ],
                'choices' => [
                    ['message' => ['content' => 'A documentação confirma o pagamento.']],
                ],
            ], 200),
        ]);

        $conversation = $this->conversationWithUserMessage();
        $prompt = (new SystemPromptCatalog)->current('docs_briefing');

        $content = (new OpenAiAdapter)->completePrompt(
            [['role' => 'user', 'content' => 'docs']],
            'gpt-4o-mini',
            'docs_briefing',
            $conversation,
        );

        $this->assertSame('A documentação confirma o pagamento.', $content);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'generation_id' => 'chatcmpl-briefing-1',
            'step' => 'docs_briefing',
            'prompt_version' => 'v1',
            'prompt_hash' => $prompt->hash,
            'total_tokens' => 52,
        ]);
    }

    private function conversationWithUserMessage(): Conversation
    {
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

        return $conversation;
    }
}
