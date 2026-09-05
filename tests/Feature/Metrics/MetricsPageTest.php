<?php

namespace Tests\Feature\Metrics;

use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_metrics(): void
    {
        $this->get(route('metrics.index'))
            ->assertRedirect(route('login'));
    }

    public function test_shows_empty_metrics_dashboard_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('metrics.index'))
            ->assertOk()
            ->assertSee('Métricas')
            ->assertSee('Conversas')
            ->assertSee('Cards')
            ->assertSee('discovery@v1')
            ->assertSee('Nenhuma conversa com versão de prompt registrada ainda.')
            ->assertSee('Nenhuma chamada registrada ainda.');
    }

    public function test_shows_consumption_and_prompt_metrics_only_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'status' => 'completed',
            'prompt_version' => 'v1',
        ]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Quero pagar']);
        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'minimax/minimax-m3:free',
            'prompt_version' => 'v1',
            'provider' => 'GMICloud',
            'prompt_tokens' => 100,
            'completion_tokens' => 20,
            'total_tokens' => 120,
            'cached_tokens' => 8,
            'cost' => 0.015,
        ]);

        $foreign = Conversation::query()->create([
            'user_id' => $other->id,
            'title' => 'Segredo de outro usuário',
            'status' => 'completed',
            'prompt_version' => 'v2',
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $foreign->id,
            'model' => 'secret/other-model',
            'prompt_version' => 'v2',
            'prompt_tokens' => 999,
            'completion_tokens' => 999,
            'total_tokens' => 1998,
            'cost' => 9.99,
        ]);

        $this->actingAs($user)
            ->get(route('metrics.index'))
            ->assertOk()
            ->assertSee('minimax/minimax-m3:free')
            ->assertSee('Checkout')
            ->assertSee('prompt v1')
            ->assertSee('GMICloud')
            ->assertSee('$0.015')
            ->assertSee('120')
            ->assertDontSee('Segredo de outro usuário')
            ->assertDontSee('secret/other-model')
            ->assertDontSee('$9.99');
    }
}
