<?php

namespace Tests\Feature\Models;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_persists_with_null_project_id(): void
    {
        $user = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'title' => 'Nova conversa',
        ]);

        $this->assertNull($conversation->project_id);
        $this->assertNull($conversation->project);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'project_id' => null,
        ]);
    }

    public function test_project_relation_resolves_active_project_by_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'name' => 'Checkout',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        $this->assertSame('Checkout', $conversation->project?->name);
        $this->assertTrue($conversation->project->is($project));
    }

    public function test_project_relation_resolves_soft_deleted_project_by_name(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'name' => 'Checkout',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        $project->delete();

        $this->assertNull(Project::query()->find($project->id));
        $this->assertSame('Checkout', $conversation->fresh()->project?->name);
    }

    public function test_create_explore_opens_a_new_thread_each_time(): void
    {
        $user = User::factory()->create();

        $first = Conversation::createExploreFor($user);
        $second = Conversation::createExploreFor($user);

        $this->assertFalse($first->is($second));
        $this->assertTrue($first->isExplore());
        $this->assertTrue($second->isExplore());
        $this->assertSame(Conversation::PROMPT_EXPLORE, $first->prompt_name);
        $this->assertSame(2, $user->conversations()->explore()->count());
    }

    public function test_interview_scope_excludes_explore_conversations(): void
    {
        $user = User::factory()->create();
        $interview = $user->conversations()->create(['title' => 'Interview']);
        Conversation::createExploreFor($user);

        $this->assertSame([$interview->id], $user->conversations()->interview()->pluck('id')->all());
    }

    public function test_cards_table_has_no_project_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('cards', 'project_id'));
        $this->assertTrue(Schema::hasColumn('conversations', 'project_id'));
    }
}
