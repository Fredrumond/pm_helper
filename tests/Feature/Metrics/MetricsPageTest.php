<?php

namespace Tests\Feature\Metrics;

use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class MetricsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_metrics(): void
    {
        $this->get(route('metrics.index'))
            ->assertRedirect(route('login'));
    }

    public function test_product_manager_cannot_access_metrics_or_see_the_nav_link(): void
    {
        $productManager = User::factory()->create();

        $this->actingAs($productManager)
            ->get(route('metrics.index'))
            ->assertForbidden();

        $this->actingAs($productManager)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('metrics.index').'"', false)
            ->assertDontSee('href="'.route('versoes.index').'"', false);
    }

    public function test_admin_sees_metrics_and_versoes_in_the_user_dropdown(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('href="'.route('metrics.index').'"', false)
            ->assertSee('href="'.route('versoes.index').'"', false)
            ->assertSee('Métricas')
            ->assertSee('Versões');
    }

    public function test_shows_empty_metrics_dashboard_for_authenticated_user(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('metrics.index'))
            ->assertOk()
            ->assertSee('Métricas')
            ->assertSee('Conversas')
            ->assertSee('Cards')
            ->assertSee('interview@v4')
            ->assertSee('Pipeline por step')
            ->assertSee('docs_retrieval')
            ->assertSee('docs_briefing')
            ->assertSee('Nenhuma conversa com versão de prompt registrada ainda.')
            ->assertSee('Nenhuma chamada registrada ainda.');
    }

    public function test_shows_consumption_and_prompt_metrics_only_for_the_authenticated_user(): void
    {
        $user = User::factory()->admin()->create();
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
            'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
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
            ->assertSee('nvidia/nemotron-3-ultra-550b-a55b:free')
            ->assertSee('Checkout')
            ->assertSee('prompt v1')
            ->assertSee('GMICloud')
            ->assertSee('$0.015')
            ->assertSee('120')
            ->assertDontSee('Segredo de outro usuário')
            ->assertDontSee('secret/other-model')
            ->assertDontSee('$9.99');
    }

    public function test_shows_estimated_openai_cost_and_pricing_table_note(): void
    {
        CatalogModels::seedGpt4oMiniPrice();

        $user = User::factory()->admin()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Fazer minha LP de ebook vender mais',
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'gpt-4o-mini-2024-07-18',
            'step' => 'interview',
            'prompt_version' => 'v4',
            'prompt_tokens' => 1408,
            'completion_tokens' => 51,
            'total_tokens' => 1459,
            'cost' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('metrics.index'))
            ->assertOk()
            ->assertSee('config/llm.php')
            ->assertSee('tabela de preços')
            ->assertSee('gpt-4o-mini-2024-07-18')
            ->assertSee('$0.0002418');
    }

    public function test_shows_docs_retrieval_and_docs_briefing_in_the_pipeline(): void
    {
        $user = User::factory()->admin()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'prompt_version' => 'v4',
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'test/model',
            'step' => 'docs_retrieval',
            'prompt_version' => 'v1',
            'prompt_tokens' => 20,
            'completion_tokens' => 5,
            'total_tokens' => 25,
            'cost' => 0.002,
        ]);
        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'test/model',
            'step' => 'docs_briefing',
            'prompt_version' => 'v1',
            'prompt_tokens' => 40,
            'completion_tokens' => 12,
            'total_tokens' => 52,
            'cost' => 0.004,
        ]);

        $this->actingAs($user)
            ->get(route('metrics.index'))
            ->assertOk()
            ->assertSee('docs_retrieval')
            ->assertSee('docs_briefing')
            ->assertSee('$0.002')
            ->assertSee('$0.004')
            ->assertSee('25')
            ->assertSee('52');
    }
}
