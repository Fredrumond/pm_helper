<?php

namespace Tests\Feature\Services;

use App\Models\Conversation;
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

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($conversation) {
            return $log->level === 'info'
                && $log->message === 'OpenRouter API response'
                && $log->context['conversation_id'] === $conversation->id
                && $log->context['status'] === 200
                && $log->context['model'] === 'test/model'
                && $log->context['id'] === 'gen-test-1'
                && $log->context['provider'] === 'TestProvider'
                && $log->context['usage']['total_tokens'] === 14
                && $log->context['finish_reason'] === 'stop'
                && $log->context['payload']['choices'][0]['message']['content'] === 'Olá!';
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'test/model'
                && $request['messages'][1]['content'] === 'Quero um card';
        });
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

        (new OpenRouterService)->chat($conversation);
    }
}
