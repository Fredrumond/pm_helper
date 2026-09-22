<?php

namespace Tests\Feature;

use App\Livewire\CardPreview;
use App\Models\Card;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CardProjectTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_pages_and_preview_show_the_project_name_beside_the_priority(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'acme/mobile-app',
        ]);
        $card = $this->cardFor($user, $project);

        $this->actingAs($user)
            ->get(route('cards.index'))
            ->assertOk()
            ->assertSee('App mobile')
            ->assertSee('Alta')
            ->assertDontSee('acme/mobile-app');

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk()
            ->assertSee('App mobile')
            ->assertSee('Prioridade Alta')
            ->assertDontSee('acme/mobile-app');

        Livewire::actingAs($user)
            ->test(CardPreview::class, ['card' => $card])
            ->assertSee('App mobile')
            ->assertSee('Alta')
            ->assertDontSee('acme/mobile-app');

        $this->actingAs($user)
            ->get(route('conversations.show', $card->conversation))
            ->assertOk()
            ->assertSee('App mobile')
            ->assertSee('Alta')
            ->assertDontSee('acme/mobile-app');
    }

    public function test_card_pages_and_preview_omit_the_project_tag_when_the_conversation_has_none(): void
    {
        $user = User::factory()->create();
        Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'acme/mobile-app',
        ]);
        $card = $this->cardFor($user, null);

        $this->actingAs($user)
            ->get(route('cards.index'))
            ->assertOk()
            ->assertSee('Alta')
            ->assertDontSee('App mobile')
            ->assertDontSee('acme/mobile-app');

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk()
            ->assertSee('Prioridade Alta')
            ->assertDontSee('App mobile')
            ->assertDontSee('acme/mobile-app');

        Livewire::actingAs($user)
            ->test(CardPreview::class, ['card' => $card])
            ->assertSee('Alta')
            ->assertDontSee('App mobile')
            ->assertDontSee('acme/mobile-app');

        $this->actingAs($user)
            ->get(route('conversations.show', $card->conversation))
            ->assertOk()
            ->assertDontSee('App mobile')
            ->assertDontSee('acme/mobile-app');
    }

    public function test_show_keeps_the_project_name_after_the_project_is_soft_deleted(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'acme/mobile-app',
        ]);
        $card = $this->cardFor($user, $project);

        $project->delete();

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk()
            ->assertSee('App mobile')
            ->assertSee('Prioridade Alta')
            ->assertDontSee('acme/mobile-app');

        $this->assertNull(Project::query()->find($project->id));
    }

    public function test_persisted_card_does_not_gain_a_project_attribute(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'acme/mobile-app',
        ]);
        $card = $this->cardFor($user, $project);
        $before = $card->fresh()->getAttributes();

        $this->actingAs($user)
            ->get(route('cards.show', $card))
            ->assertOk();

        $after = $card->fresh()->getAttributes();

        $this->assertFalse(Schema::hasColumn('cards', 'project_id'));
        $this->assertArrayNotHasKey('project_id', $after);
        $this->assertSame($before, $after);
        $this->assertStringNotContainsString('App mobile', (string) $card->objetivo);
        $this->assertStringNotContainsString('acme/mobile-app', (string) json_encode($after));
    }

    private function cardFor(User $user, ?Project $project): Card
    {
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Checkout',
            'status' => 'completed',
            'project_id' => $project?->id,
        ]);

        return Card::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Checkout MVP',
            'objetivo' => 'Permitir que o comprador pague no checkout.',
            'regras' => ['Exibir meios de pagamento disponíveis.'],
            'onde' => ['Checkout'],
            'aceite' => ['Comprador com carrinho: ao pagar, confirma o pedido.'],
            'priority' => 'high',
            'status' => 'draft',
        ]);
    }
}
