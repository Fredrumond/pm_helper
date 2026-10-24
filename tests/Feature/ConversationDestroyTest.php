<?php

namespace Tests\Feature;

use App\Livewire\CardPreview;
use App\Metrics\ConsumptionMetrics;
use App\Models\Card;
use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_soft_deletes_interview_and_keeps_messages_card_and_usage(): void
    {
        $user = User::factory()->create();
        [$conversation, $card] = $this->interviewWithCardAndUsage($user);
        $messageId = $conversation->messages()->value('id');

        $this->actingAs($user)
            ->delete(route('conversations.destroy', $conversation))
            ->assertRedirect(route('conversations.index'))
            ->assertSessionHas('message', 'Conversa excluída.');

        $this->assertSoftDeleted('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'conversation_id' => $conversation->id,
        ]);
        $this->assertDatabaseHas('cards', [
            'id' => $card->id,
            'conversation_id' => $conversation->id,
        ]);
        $this->assertDatabaseHas('llm_usages', [
            'conversation_id' => $conversation->id,
            'cost' => 0.25,
        ]);
    }

    public function test_soft_deleted_interview_disappears_from_index_and_show(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout excluído',
        ]);

        $this->actingAs($user)
            ->delete(route('conversations.destroy', $conversation));

        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee('Checkout excluído');

        $this->actingAs($user)
            ->get(route('conversations.show', $conversation))
            ->assertNotFound();
    }

    public function test_card_of_soft_deleted_conversation_is_hidden_from_index_show_and_preview(): void
    {
        $user = User::factory()->create();
        [$conversation, $card] = $this->interviewWithCardAndUsage($user, title: 'Card oculto');

        $this->actingAs($user)
            ->delete(route('conversations.destroy', $conversation));

        $this->actingAs($user)
            ->get(route('cards.index'))
            ->assertOk()
            ->assertDontSee('Card oculto');

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertNotFound();

        Livewire::actingAs($user)
            ->test(CardPreview::class, ['card' => $card])
            ->assertNotFound();
    }

    public function test_metrics_still_include_cost_of_soft_deleted_conversation(): void
    {
        $user = User::factory()->create();
        [$conversation] = $this->interviewWithCardAndUsage($user);

        $this->actingAs($user)
            ->delete(route('conversations.destroy', $conversation));

        $summary = (new ConsumptionMetrics)->summary($user);

        $this->assertSame(1, $summary['calls']);
        $this->assertEqualsWithDelta(0.25, $summary['cost'], 0.0000001);
        $this->assertSame(1, $summary['conversations']);
        $this->assertSame(1, $summary['completed']);
    }

    public function test_non_owner_cannot_delete_an_interview(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $owner->id,
            'title' => 'Alheia',
        ]);

        $this->actingAs($other)
            ->delete(route('conversations.destroy', $conversation))
            ->assertForbidden();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'deleted_at' => null,
        ]);
    }

    public function test_explore_is_not_soft_deleted_via_conversations_destroy(): void
    {
        $user = User::factory()->create();
        $explore = Conversation::createExploreFor($user);

        $this->actingAs($user)
            ->delete(route('conversations.destroy', $explore))
            ->assertNotFound();

        $this->assertDatabaseHas('conversations', [
            'id' => $explore->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * @return array{0: Conversation, 1: Card}
     */
    private function interviewWithCardAndUsage(User $user, string $title = 'Checkout MVP'): array
    {
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'status' => 'completed',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Quero pagar',
        ]);

        $card = Card::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'title' => $title,
            'objetivo' => 'Permitir que o comprador pague no checkout.',
            'regras' => ['Exibir meios de pagamento disponíveis.'],
            'onde' => ['Checkout'],
            'aceite' => ['Comprador com carrinho: ao pagar, confirma o pedido.'],
            'priority' => 'high',
            'status' => 'draft',
        ]);

        LlmUsage::query()->create([
            'conversation_id' => $conversation->id,
            'model' => 'test/model',
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'cost' => 0.25,
        ]);

        return [$conversation, $card];
    }
}
