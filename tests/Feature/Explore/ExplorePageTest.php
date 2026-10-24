<?php

namespace Tests\Feature\Explore;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExplorePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_explorar(): void
    {
        $this->get(route('explorar.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_lists_explorations_without_creating_one(): void
    {
        $user = User::factory()->create();
        $exploration = Conversation::createExploreFor($user);
        $exploration->update(['title' => 'Checkout do produto']);

        $this->actingAs($user)
            ->get(route('explorar.index'))
            ->assertOk()
            ->assertSee('Suas Explorações')
            ->assertSee('Nova Exploração')
            ->assertSee('Checkout do produto')
            ->assertSee('href="'.route('explorar.show', $exploration).'"', false)
            ->assertDontSee('Assistente de Exploração');

        $this->assertSame(1, $user->conversations()->explore()->count());
    }

    public function test_index_shows_empty_state_when_there_are_no_explorations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('explorar.index'))
            ->assertOk()
            ->assertSee('Nenhuma exploração ainda.')
            ->assertSee('Nova Exploração');

        $this->assertDatabaseMissing('conversations', [
            'user_id' => $user->id,
            'prompt_name' => Conversation::PROMPT_EXPLORE,
        ]);
    }

    public function test_store_creates_a_new_exploration_each_time(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->post(route('explorar.store'));
        $second = $this->actingAs($user)->post(route('explorar.store'));

        $explorations = $user->conversations()->explore()->orderBy('id')->get();

        $this->assertCount(2, $explorations);
        $first->assertRedirect(route('explorar.show', $explorations[0]));
        $second->assertRedirect(route('explorar.show', $explorations[1]));
    }

    public function test_show_opens_the_explore_chat(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);

        $this->actingAs($user)
            ->get(route('explorar.show', $conversation))
            ->assertOk()
            ->assertSee('Assistente de Exploração')
            ->assertSee('O que você quer explorar?');
    }

    public function test_show_rejects_another_users_exploration(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::createExploreFor($owner);

        $this->actingAs($other)
            ->get(route('explorar.show', $conversation))
            ->assertForbidden();
    }

    public function test_interview_show_rejects_explore_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);

        $this->actingAs($user)
            ->get(route('conversations.show', $conversation))
            ->assertNotFound();
    }

    public function test_show_rejects_interview_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = $user->conversations()->create([
            'title' => 'Nova conversa',
            'prompt_name' => 'interview',
        ]);

        $this->actingAs($user)
            ->get(route('explorar.show', $conversation))
            ->assertNotFound();
    }

    public function test_destroy_deletes_only_the_selected_exploration(): void
    {
        $user = User::factory()->create();
        $keep = Conversation::createExploreFor($user);
        $remove = Conversation::createExploreFor($user);

        $this->actingAs($user)
            ->delete(route('explorar.destroy', $remove))
            ->assertRedirect(route('explorar.index'));

        $this->assertDatabaseHas('conversations', ['id' => $keep->id]);
        $this->assertDatabaseMissing('conversations', ['id' => $remove->id]);
    }

    public function test_product_manager_sees_explorar_in_the_main_menu(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('href="'.route('explorar.index').'"', false)
            ->assertSee('Explorar');
    }
}
