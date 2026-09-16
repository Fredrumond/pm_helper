<?php

namespace Tests\Feature\Prompts;

use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use App\Prompts\PromptMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_compares_prompt_versions_by_completion_tokens_and_cost(): void
    {
        $user = User::factory()->create();

        $v1Completed = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'status' => 'completed',
            'prompt_version' => 'v1',
        ]);
        $v1Completed->messages()->create(['role' => 'user', 'content' => 'Quero pagar']);
        $v1Completed->messages()->create(['role' => 'assistant', 'content' => 'Qual o problema?']);
        LlmUsage::query()->create([
            'conversation_id' => $v1Completed->id,
            'model' => 'test/model',
            'prompt_version' => 'v1',
            'prompt_tokens' => 100,
            'completion_tokens' => 20,
            'total_tokens' => 120,
            'cost' => 0.20,
        ]);

        $v1Open = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Login',
            'status' => 'in_progress',
            'prompt_version' => 'v1',
        ]);
        $v1Open->messages()->create(['role' => 'user', 'content' => 'Quero login']);
        LlmUsage::query()->create([
            'conversation_id' => $v1Open->id,
            'model' => 'test/model',
            'prompt_version' => 'v1',
            'prompt_tokens' => 80,
            'completion_tokens' => 10,
            'total_tokens' => 90,
            'cost' => 0.10,
        ]);

        $v2Completed = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Busca',
            'status' => 'completed',
            'prompt_version' => 'v2',
        ]);
        $v2Completed->messages()->create(['role' => 'user', 'content' => 'Quero buscar']);
        $v2Completed->messages()->create(['role' => 'assistant', 'content' => 'Card']);
        $v2Completed->messages()->create(['role' => 'user', 'content' => 'Ok']);
        LlmUsage::query()->create([
            'conversation_id' => $v2Completed->id,
            'model' => 'test/model',
            'prompt_version' => 'v2',
            'prompt_tokens' => 40,
            'completion_tokens' => 10,
            'total_tokens' => 50,
            'cost' => 0.05,
        ]);

        $metrics = (new PromptMetrics)->compare()->keyBy('version');

        $this->assertSame(2, $metrics['v1']['conversations']);
        $this->assertSame(1, $metrics['v1']['completed']);
        $this->assertSame(0.5, $metrics['v1']['completion_rate']);
        $this->assertSame(2, $metrics['v1']['calls']);
        $this->assertSame(105.0, $metrics['v1']['avg_tokens']);
        $this->assertEqualsWithDelta(0.15, $metrics['v1']['avg_cost'], 0.0000001);
        $this->assertSame(1.5, $metrics['v1']['avg_messages']);

        $this->assertSame(1, $metrics['v2']['conversations']);
        $this->assertSame(1, $metrics['v2']['completed']);
        $this->assertSame(1.0, $metrics['v2']['completion_rate']);
        $this->assertSame(50.0, $metrics['v2']['avg_tokens']);
        $this->assertSame(3.0, $metrics['v2']['avg_messages']);
    }

    public function test_compare_can_scope_metrics_to_a_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Minha',
            'status' => 'completed',
            'prompt_version' => 'v1',
        ]);
        Conversation::query()->create([
            'user_id' => $other->id,
            'title' => 'Outra',
            'status' => 'completed',
            'prompt_version' => 'v2',
        ]);

        $metrics = (new PromptMetrics)->compare($user)->keyBy('version');

        $this->assertTrue($metrics->has('v1'));
        $this->assertFalse($metrics->has('v2'));
        $this->assertSame(1, $metrics['v1']['conversations']);
    }

    public function test_compares_usage_by_step_and_prompt_version(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'status' => 'completed',
            'prompt_version' => 'v1',
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'test/model',
            'step' => 'interview',
            'prompt_version' => 'v1',
            'total_tokens' => 100,
            'cost' => 0.20,
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'test/model',
            'step' => 'card_generation',
            'prompt_version' => 'v1',
            'total_tokens' => 40,
            'cost' => 0.05,
        ]);

        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'test/model',
            'step' => 'docs_retrieval',
            'prompt_version' => 'v1',
            'total_tokens' => 30,
            'cost' => 0.02,
        ]);

        $other = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Login',
            'status' => 'in_progress',
            'prompt_version' => 'v1',
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $other->id,
            'model' => 'test/model',
            'step' => 'interview',
            'prompt_version' => 'v1',
            'total_tokens' => 80,
            'cost' => 0.10,
        ]);

        $metrics = (new PromptMetrics)->compareByStep($user)->keyBy('step');

        $this->assertSame(2, $metrics['interview']['conversations']);
        $this->assertSame(2, $metrics['interview']['calls']);
        $this->assertSame(90.0, $metrics['interview']['avg_tokens']);
        $this->assertSame(180, $metrics['interview']['total_tokens']);
        $this->assertSame(1, $metrics['card_generation']['conversations']);
        $this->assertSame(1, $metrics['card_generation']['calls']);
        $this->assertSame(40.0, $metrics['card_generation']['avg_tokens']);
        $this->assertSame(1, $metrics['docs_retrieval']['conversations']);
        $this->assertSame(1, $metrics['docs_retrieval']['calls']);
        $this->assertSame(30.0, $metrics['docs_retrieval']['avg_tokens']);
    }
}
