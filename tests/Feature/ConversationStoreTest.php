<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_reuses_the_latest_empty_interview_instead_of_creating_another(): void
    {
        $user = User::factory()->create();
        Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Vazia antiga',
        ]);
        $empty = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Vazia recente',
        ]);

        $this->actingAs($user)
            ->post(route('conversations.store'))
            ->assertRedirect(route('conversations.show', $empty));

        $this->assertSame(2, $user->conversations()->interview()->count());
    }

    public function test_creates_a_conversation_when_the_user_has_none_empty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('conversations.store'));

        $created = $user->conversations()->interview()->first();

        $this->assertNotNull($created);
        $this->assertSame(1, $user->conversations()->count());
        $this->assertSame('Nova conversa', $created->title);
        $response->assertRedirect(route('conversations.show', $created));
    }

    public function test_creates_a_new_conversation_when_the_existing_interview_has_messages(): void
    {
        $user = User::factory()->create();
        $existing = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Em andamento',
        ]);
        $existing->messages()->create([
            'role' => 'user',
            'content' => 'Quero um checkout',
        ]);

        $response = $this->actingAs($user)
            ->post(route('conversations.store'));

        $created = $user->conversations()
            ->interview()
            ->whereKeyNot($existing->id)
            ->first();

        $this->assertNotNull($created);
        $this->assertSame(2, $user->conversations()->interview()->count());
        $response->assertRedirect(route('conversations.show', $created));
    }

    public function test_does_not_reuse_an_empty_explore_conversation(): void
    {
        $user = User::factory()->create();
        $explore = Conversation::createExploreFor($user);

        $this->assertSame(0, $explore->messages()->count());

        $response = $this->actingAs($user)
            ->post(route('conversations.store'));

        $created = $user->conversations()->interview()->first();

        $this->assertNotNull($created);
        $this->assertFalse($created->is($explore));
        $this->assertSame(1, $user->conversations()->interview()->count());
        $this->assertSame(1, $user->conversations()->explore()->count());
        $response->assertRedirect(route('conversations.show', $created));
    }

    public function test_does_not_reuse_another_users_empty_interview(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Conversation::query()->create([
            'user_id' => $other->id,
            'title' => 'Alheia',
        ]);

        $response = $this->actingAs($user)
            ->post(route('conversations.store'));

        $created = $user->conversations()->interview()->first();

        $this->assertNotNull($created);
        $this->assertFalse($created->is($foreign));
        $this->assertSame(1, $other->conversations()->count());
        $response->assertRedirect(route('conversations.show', $created));
    }
}
