<?php

namespace Tests\Feature\Metrics;

use App\Metrics\ConsumptionMetrics;
use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_usage_and_excludes_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Minha',
            'status' => 'completed',
        ]);
        Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Aberta',
            'status' => 'in_progress',
        ]);
        $foreign = Conversation::query()->create([
            'user_id' => $other->id,
            'title' => 'Alheia',
            'status' => 'completed',
        ]);

        LlmUsage::query()->create([
            'conversation_id' => $mine->id,
            'model' => 'test/a',
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'cached_tokens' => 2,
            'cost' => 0.20,
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $mine->id,
            'model' => 'test/b',
            'prompt_tokens' => 4,
            'completion_tokens' => 1,
            'total_tokens' => 5,
            'cached_tokens' => 0,
            'cost' => 0.05,
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $foreign->id,
            'model' => 'test/other',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'cost' => 3,
        ]);

        $metrics = new ConsumptionMetrics;
        $summary = $metrics->summary($user);
        $byModel = $metrics->byModel($user)->keyBy('model');
        $recent = $metrics->recent($user);

        $this->assertSame(2, $summary['calls']);
        $this->assertSame(14, $summary['prompt_tokens']);
        $this->assertSame(6, $summary['completion_tokens']);
        $this->assertSame(20, $summary['total_tokens']);
        $this->assertSame(2, $summary['cached_tokens']);
        $this->assertEqualsWithDelta(0.25, $summary['cost'], 0.0000001);
        $this->assertSame('$0.25', $summary['formatted_cost']);
        $this->assertSame(2, $summary['conversations']);
        $this->assertSame(1, $summary['completed']);

        $this->assertSame(1, $byModel['test/a']['calls']);
        $this->assertSame(15, $byModel['test/a']['total_tokens']);
        $this->assertFalse($byModel->has('test/other'));

        $this->assertCount(2, $recent);
        $this->assertFalse($recent->contains(fn (LlmUsage $usage) => $usage->model === 'test/other'));
    }

    public function test_estimates_paid_openai_models_instead_of_showing_them_as_free(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'LP de ebook',
        ]);

        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'gpt-4o-mini-2024-07-18',
            'prompt_tokens' => 1408,
            'completion_tokens' => 51,
            'total_tokens' => 1459,
            'cached_tokens' => 0,
            'cost' => 0,
        ]);

        $metrics = new ConsumptionMetrics;
        $summary = $metrics->summary($user);
        $byModel = $metrics->byModel($user)->keyBy('model');
        $usage = $metrics->recent($user)->first();

        $this->assertEqualsWithDelta(0.00024180, $summary['cost'], 0.00000001);
        $this->assertSame('$0.0002418', $summary['formatted_cost']);
        $this->assertTrue($byModel['gpt-4o-mini-2024-07-18']['estimated']);
        $this->assertSame('$0.0002418', $byModel['gpt-4o-mini-2024-07-18']['formatted_cost']);
        $this->assertTrue($usage->isEstimatedCost());
        $this->assertSame('$0.0002418', $usage->formattedCost());
    }
}
