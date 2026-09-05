<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_session_usage_on_conversation_and_card_after_card_is_generated(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'status' => 'completed',
        ]);

        $card = Card::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Checkout MVP',
            'type' => 'feature',
            'user_story' => 'Como comprador, quero pagar',
            'context' => 'Fluxo atual sem pagamento',
            'acceptance_criteria' => ['Dado o carrinho, quando pagar, então confirma'],
            'priority' => 'high',
            'status' => 'draft',
        ]);

        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'generation_id' => 'gen-1',
            'model' => 'minimax/minimax-m3:free',
            'provider' => 'GMICloud',
            'prompt_tokens' => 850,
            'completion_tokens' => 131,
            'total_tokens' => 981,
            'cached_tokens' => 128,
            'cost' => 0,
            'finish_reason' => 'stop',
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'generation_id' => 'gen-2',
            'model' => 'minimax/minimax-m3:free',
            'provider' => 'GMICloud',
            'prompt_tokens' => 200,
            'completion_tokens' => 50,
            'total_tokens' => 250,
            'cached_tokens' => 0,
            'cost' => 0.0015,
            'finish_reason' => 'stop',
        ]);

        $this->actingAs($user)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Consumo da sessão')
            ->assertSee('1,231')
            ->assertSee('minimax/minimax-m3:free')
            ->assertSee('GMICloud')
            ->assertSee('Grátis')
            ->assertSee('$0.0015');

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk()
            ->assertSee('Consumo da sessão')
            ->assertSee('1,231')
            ->assertSee('#1 minimax/minimax-m3:free')
            ->assertSee('#2 minimax/minimax-m3:free');
    }

    public function test_hides_usage_block_when_session_has_no_records(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'status' => 'completed',
        ]);

        $card = Card::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Checkout MVP',
            'type' => 'feature',
            'user_story' => 'Como comprador, quero pagar',
            'acceptance_criteria' => ['Dado o carrinho'],
            'priority' => 'high',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk()
            ->assertDontSee('Consumo da sessão');
    }

    public function test_aggregates_usage_totals_for_the_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Sessão',
        ]);

        LlmUsage::recordFromResponse($conversation, [
            'id' => 'gen-a',
            'model' => 'test/model',
            'provider' => 'Test',
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
                'cost' => 0.2,
                'prompt_tokens_details' => ['cached_tokens' => 2],
            ],
            'choices' => [['finish_reason' => 'stop']],
        ], 'fallback');

        LlmUsage::recordFromResponse($conversation, [
            'usage' => [
                'prompt_tokens' => 4,
                'completion_tokens' => 1,
                'total_tokens' => 5,
                'cost' => 0.05,
            ],
        ], 'fallback');

        $summary = $conversation->fresh()->usageSummary();

        $this->assertSame(2, $summary['calls']);
        $this->assertSame(14, $summary['prompt_tokens']);
        $this->assertSame(6, $summary['completion_tokens']);
        $this->assertSame(20, $summary['total_tokens']);
        $this->assertSame(2, $summary['cached_tokens']);
        $this->assertEqualsWithDelta(0.25, $summary['cost'], 0.0000001);
        $this->assertSame('$0.25', $conversation->fresh()->formattedUsageCost());
    }
}
