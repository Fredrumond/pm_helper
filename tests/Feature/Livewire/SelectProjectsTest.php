<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminProjects;
use App\Livewire\SelectProjects;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SelectProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('projects.index'))
            ->assertRedirect(route('login'));
    }

    public function test_product_manager_and_admin_can_open_the_selection_list(): void
    {
        $productManager = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($productManager)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('href="'.route('projects.index').'"', false)
            ->assertDontSee('href="'.route('admin.projects.index').'"', false);

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('href="'.route('projects.index').'"', false)
            ->assertSee('href="'.route('admin.projects.index').'"', false);
    }

    public function test_product_manager_sees_friendly_name_and_not_the_repository(): void
    {
        $productManager = User::factory()->create();
        Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'acme/mobile-app',
        ]);

        $this->actingAs($productManager)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('App mobile')
            ->assertDontSee('acme/mobile-app');

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->assertSee('App mobile')
            ->assertDontSee('acme/mobile-app');
    }

    public function test_soft_deleted_project_is_not_listed(): void
    {
        $productManager = User::factory()->create();
        $active = Project::factory()->create([
            'name' => 'App ativo',
            'repository' => 'acme/active-app',
        ]);
        $deleted = Project::factory()->create([
            'name' => 'App desativado',
            'repository' => 'acme/mobile-app',
        ]);
        $deleted->delete();

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->assertSee('App ativo')
            ->assertDontSee('App desativado')
            ->assertDontSee('acme/mobile-app');

        $this->assertNotNull(Project::query()->find($active->id));
        $this->assertNull(Project::query()->find($deleted->id));
    }

    public function test_select_persists_in_session_and_a_second_select_replaces_it(): void
    {
        $productManager = User::factory()->create();
        $first = Project::factory()->create(['name' => 'Primeiro']);
        $second = Project::factory()->create(['name' => 'Segundo']);

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->call('select', $first->id)
            ->assertSee('Primeiro')
            ->assertSee('Selecionado');

        $this->assertSame($first->id, session('current_project_id'));

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->call('select', $second->id);

        $this->assertSame($second->id, session('current_project_id'));
    }

    public function test_clear_removes_the_session_key(): void
    {
        $productManager = User::factory()->create();
        $project = Project::factory()->create(['name' => 'App mobile']);

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->call('select', $project->id);

        $this->assertTrue(session()->has('current_project_id'));
        $this->assertSame($project->id, session('current_project_id'));

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->call('clear');

        $this->assertFalse(session()->has('current_project_id'));
        $this->assertNull(session('current_project_id'));
    }

    public function test_selecting_a_deactivated_or_missing_id_does_not_persist(): void
    {
        $productManager = User::factory()->create();
        $active = Project::factory()->create(['name' => 'App ativo']);
        $deactivated = Project::factory()->create(['name' => 'App desativado']);
        $deactivated->delete();

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->call('select', $active->id);

        $this->assertSame($active->id, session('current_project_id'));

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->call('select', $deactivated->id);

        $this->assertFalse(session()->has('current_project_id'));

        Livewire::actingAs($productManager)
            ->test(SelectProjects::class)
            ->call('select', $active->id)
            ->call('select', 999_999);

        $this->assertFalse(session()->has('current_project_id'));
    }

    public function test_stale_session_id_is_cleared_when_the_project_is_no_longer_active(): void
    {
        $productManager = User::factory()->create();
        $project = Project::factory()->create(['name' => 'App mobile']);
        $projectId = $project->id;
        $project->delete();

        $this->actingAs($productManager)
            ->withSession(['current_project_id' => $projectId])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('App mobile');

        $this->assertFalse(session()->has('current_project_id'));
    }

    public function test_admin_still_accesses_the_crud_from_slice_three(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'acme/mobile-app',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('App mobile')
            ->assertSee('acme/mobile-app')
            ->assertSee('Novo projeto');

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->assertSee('App mobile')
            ->assertSee('acme/mobile-app')
            ->assertSee('Novo projeto');
    }

    public function test_conversation_chat_does_not_read_current_project_id(): void
    {
        $chat = file_get_contents(app_path('Livewire/ConversationChat.php'));
        $chatView = file_get_contents(resource_path('views/livewire/conversation-chat.blade.php'));

        $this->assertIsString($chat);
        $this->assertIsString($chatView);
        $this->assertStringNotContainsString('current_project_id', $chat);
        $this->assertStringNotContainsString('current_project_id', $chatView);
        $this->assertStringNotContainsString('SelectProjects', $chat);
        $this->assertStringNotContainsString('CurrentProject', $chat);
        $this->assertStringNotContainsString('CurrentProject', $chatView);
    }
}
