<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminProjects;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.projects.index'))
            ->assertRedirect(route('login'));
    }

    public function test_product_manager_is_forbidden_and_does_not_see_the_admin_projects_link(): void
    {
        $productManager = User::factory()->create();

        $this->actingAs($productManager)
            ->get(route('admin.projects.index'))
            ->assertForbidden();

        Livewire::actingAs($productManager)
            ->test(AdminProjects::class)
            ->assertForbidden();

        $this->actingAs($productManager)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.projects.index').'"', false);
    }

    public function test_admin_accesses_creates_a_valid_project_and_sees_it_in_the_list(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.projects.index').'"', false)
            ->assertSee('Projetos');

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('Nenhum projeto ativo.')
            ->assertSee('Conversas')
            ->assertSee('Novo projeto')
            ->assertSee('window.livewireScriptConfig', false)
            ->assertDontSee('data-update-uri=', false);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App mobile')
            ->set('repository', 'octocat/hello-world')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('octocat/hello-world')
            ->assertSee('Projeto cadastrado.')
            ->assertDontSee('Nenhum projeto ativo.');

        $project = Project::query()->where('repository', 'octocat/hello-world')->first();

        $this->assertNotNull($project);
        $this->assertSame('App mobile', $project->name);
        $this->assertNull($project->deleted_at);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($admin, $project): bool {
            return $log->level === 'info'
                && $log->message === 'Projeto criado.'
                && ($log->context['user_id'] ?? null) === $admin->id
                && ($log->context['project_id'] ?? null) === $project->id
                && ! array_key_exists('request', $log->context)
                && ! array_key_exists('GITHUB_APP_PRIVATE_KEY', $log->context);
        });
    }

    public function test_admin_normalizes_github_url_and_persists_owner_repo(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App mobile')
            ->set('repository', 'https://github.com/octocat/hello-world.git')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('octocat/hello-world')
            ->assertSee('Projeto cadastrado.')
            ->assertDontSee('https://github.com');

        $this->assertDatabaseHas('projects', [
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
            'deleted_at' => null,
        ]);
        $this->assertSame(1, Project::query()->count());
    }

    public function test_admin_rejects_non_github_url_and_does_not_persist(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App mobile')
            ->set('repository', 'https://gitlab.com/org/repo')
            ->call('save')
            ->assertHasErrors(['repository'])
            ->assertSee('O repository deve ser owner/repo ou uma URL do github.com.');

        $this->assertDatabaseMissing('projects', [
            'name' => 'App mobile',
        ]);
        $this->assertSame(0, Project::withTrashed()->count());
    }

    public function test_admin_edits_the_friendly_name(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create([
            'name' => 'Nome antigo',
            'repository' => 'octocat/hello-world',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startEdit', $project->id)
            ->assertSet('name', 'Nome antigo')
            ->assertSet('repository', 'octocat/hello-world')
            ->set('name', 'App mobile')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('Projeto atualizado.')
            ->assertDontSee('Nome antigo');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
            'deleted_at' => null,
        ]);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($admin, $project): bool {
            return $log->level === 'info'
                && $log->message === 'Projeto editado.'
                && ($log->context['user_id'] ?? null) === $admin->id
                && ($log->context['project_id'] ?? null) === $project->id;
        });
    }

    public function test_admin_deactivates_project_it_leaves_the_list_and_keeps_deleted_at(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->assertSee('App mobile')
            ->assertSee('octocat/hello-world')
            ->call('deactivate', $project->id)
            ->assertDontSee('App mobile')
            ->assertDontSee('octocat/hello-world')
            ->assertSee('Nenhum projeto ativo.');

        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
            'repository' => 'octocat/hello-world',
        ]);
        $this->assertNotNull(Project::withTrashed()->find($project->id)?->deleted_at);
        $this->assertSame(0, Project::query()->count());
        $this->assertSame(1, Project::withTrashed()->count());

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($admin, $project): bool {
            return $log->level === 'info'
                && $log->message === 'Projeto desativado.'
                && ($log->context['user_id'] ?? null) === $admin->id
                && ($log->context['project_id'] ?? null) === $project->id;
        });
    }

    public function test_unique_repository_fails_validation_even_after_soft_delete(): void
    {
        $admin = User::factory()->admin()->create();
        Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'Outro app')
            ->set('repository', 'octocat/hello-world')
            ->call('save')
            ->assertHasErrors(['repository']);

        $this->assertSame(1, Project::query()->count());

        Project::query()->where('repository', 'octocat/hello-world')->first()?->delete();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App recriado')
            ->set('repository', 'octocat/hello-world')
            ->call('save')
            ->assertHasErrors(['repository'])
            ->assertSee('Já existe um projeto com este repositório, inclusive desativado');

        $this->assertDatabaseMissing('projects', [
            'name' => 'App recriado',
            'deleted_at' => null,
        ]);
        $this->assertSame(1, Project::withTrashed()->where('repository', 'octocat/hello-world')->count());
    }
}
