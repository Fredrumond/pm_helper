<?php

namespace Tests\Feature\Livewire;

use App\Contracts\ProjectDocsGateway;
use App\Livewire\AdminProjects;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectIndexableFilesResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Tests\Support\FakeProjectDocsGateway;
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
            ->assertDontSee('href="'.route('admin.projects.index').'"', false)
            ->assertDontSee('href="'.url('/projetos').'"', false);
    }

    public function test_admin_accesses_creates_a_valid_project_and_sees_it_in_the_list(): void
    {
        Event::fake([MessageLogged::class]);
        config(['audit.console' => true]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.projects.index').'"', false)
            ->assertDontSee('href="'.url('/projetos').'"', false)
            ->assertSee('Gerenciar Projetos')
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
            ->set('branch', 'develop')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('octocat/hello-world')
            ->assertSee('develop')
            ->assertSee('Projeto cadastrado.')
            ->assertDontSee('Nenhum projeto ativo.');

        $project = Project::query()->where('repository', 'octocat/hello-world')->first();

        $this->assertNotNull($project);
        $this->assertSame('App mobile', $project->name);
        $this->assertSame('develop', $project->branch);
        $this->assertNull($project->deleted_at);

        $this->assertProjectAudit('created', $admin->id, $project->id);
        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->message === 'Projeto criado.';
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
            ->set('branch', 'refs/heads/main')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('octocat/hello-world')
            ->assertSee('Projeto cadastrado.')
            ->assertDontSee('https://github.com');

        $this->assertDatabaseHas('projects', [
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
            'branch' => 'main',
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
            ->set('branch', 'main')
            ->call('save')
            ->assertHasErrors(['repository'])
            ->assertSee('O repository deve ser owner/repo ou uma URL do github.com.');

        $this->assertDatabaseMissing('projects', [
            'name' => 'App mobile',
        ]);
        $this->assertSame(0, Project::withTrashed()->count());
    }

    public function test_admin_rejects_invalid_branch_and_does_not_persist(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App mobile')
            ->set('repository', 'octocat/hello-world')
            ->set('branch', 'feat*')
            ->call('save')
            ->assertHasErrors(['branch'])
            ->assertSee('A branch deve ser o nome da branch no GitHub');

        $this->assertSame(0, Project::query()->count());
    }

    public function test_admin_edits_the_friendly_name(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create([
            'name' => 'Nome antigo',
            'repository' => 'octocat/hello-world',
        ]);

        config(['audit.console' => true]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startEdit', $project->id)
            ->assertSet('name', 'Nome antigo')
            ->assertSet('repository', 'octocat/hello-world')
            ->assertSet('branch', 'main')
            ->set('name', 'App mobile')
            ->set('branch', 'release-1.0')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('Projeto atualizado.')
            ->assertDontSee('Nome antigo');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
            'branch' => 'release-1.0',
            'deleted_at' => null,
        ]);

        $this->assertProjectAudit('updated', $admin->id, $project->id);
        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->message === 'Projeto editado.';
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

        config(['audit.console' => true]);

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

        $this->assertProjectAudit('deleted', $admin->id, $project->id);
        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->message === 'Projeto desativado.';
        });
    }

    private function assertProjectAudit(string $event, int $userId, int $projectId): void
    {
        $audits = Audit::query()
            ->where('auditable_type', Project::class)
            ->get();

        $this->assertCount(1, $audits);

        $audit = $audits->first();
        $this->assertSame($event, $audit->event);
        $this->assertSame($userId, $audit->user_id);
        $this->assertSame($projectId, $audit->auditable_id);
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
            ->set('branch', 'main')
            ->call('save')
            ->assertHasErrors(['repository']);

        $this->assertSame(1, Project::query()->count());

        Project::query()->where('repository', 'octocat/hello-world')->first()?->delete();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App recriado')
            ->set('repository', 'octocat/hello-world')
            ->set('branch', 'main')
            ->call('save')
            ->assertHasErrors(['repository'])
            ->assertSee('Já existe um projeto com este repositório, inclusive desativado');

        $this->assertDatabaseMissing('projects', [
            'name' => 'App recriado',
            'deleted_at' => null,
        ]);
        $this->assertSame(1, Project::withTrashed()->where('repository', 'octocat/hello-world')->count());
    }

    public function test_create_form_warns_that_indexing_requires_staying_on_the_page(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->assertSee('Ao cadastrar, os arquivos .md do README e da pasta /docs serão indexados')
            ->assertSee('Permaneça nesta tela até a operação terminar.')
            ->assertSee('Indexação em andamento. Permaneça nesta tela; o botão fica bloqueado até concluir.');
    }

    public function test_edit_form_does_not_warn_about_create_indexing(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'App mobile']);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startEdit', $project->id)
            ->assertDontSee('Ao cadastrar, os arquivos .md do README e da pasta /docs serão indexados');
    }

    public function test_first_save_indexes_repo_files_and_hides_the_empty_label(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.21, -0.43, 0.72]],
                ],
            ], 200),
        ]);

        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queueIndexable(ProjectIndexableFilesResult::ok([
            ['path' => 'README.md', 'content' => '# App'],
            ['path' => 'docs/regras.md', 'content' => 'Regra 1'],
            ['path' => 'docs/regras.txt', 'content' => 'Ignorar'],
        ]));

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App mobile')
            ->set('repository', 'octocat/hello-world')
            ->set('branch', 'develop')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('Sincronizar')
            ->assertDontSee('Sem documento sincronizado');

        $project = Project::query()->where('repository', 'octocat/hello-world')->first();
        $this->assertNotNull($project);
        $this->assertSame([
            ['repository' => 'octocat/hello-world', 'branch' => 'develop'],
        ], $docs->indexableCalls);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => '# App',
        ]);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'docs/regras.md',
            'content' => 'Regra 1',
        ]);
        $this->assertDatabaseMissing('documents', [
            'project_id' => $project->id,
            'name' => 'docs/regras.txt',
        ]);
        $this->assertSame(2, $project->documents()->count());
    }

    public function test_first_save_shows_empty_label_when_nothing_is_indexed(): void
    {
        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queueIndexable(ProjectIndexableFilesResult::empty());

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App mobile')
            ->set('repository', 'octocat/hello-world')
            ->set('branch', 'main')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('Sem documento sincronizado');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_first_save_warns_when_indexing_fails_and_keeps_the_project(): void
    {
        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queueIndexable(ProjectIndexableFilesResult::failed('mcp'));

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('startCreate')
            ->set('name', 'App mobile')
            ->set('repository', 'octocat/hello-world')
            ->set('branch', 'main')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('App mobile')
            ->assertSee('Projeto cadastrado.')
            ->assertSee('A sincronização falhou. Tente novamente mais tarde.')
            ->assertSee('Sem documento sincronizado');

        $this->assertDatabaseHas('projects', [
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
        ]);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_sync_replaces_documents_and_hides_the_empty_label(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.21, -0.43, 0.72]],
                ],
            ], 200),
        ]);

        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queueIndexable(ProjectIndexableFilesResult::ok([
            ['path' => 'README.md', 'content' => '# Novo'],
            ['path' => 'docs/regras.txt', 'content' => 'Ignorar'],
        ]));

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
            'branch' => 'main',
        ]);
        Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'docs/antigo.md',
            'content' => 'Obsoleto',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->assertSee('Sincronizar')
            ->assertDontSee('Sem documento sincronizado')
            ->call('syncDocuments', $project->id)
            ->assertSee('Sincronização concluída.')
            ->assertDontSee('A sincronização falhou. Tente novamente mais tarde.')
            ->assertDontSee('Sem documento sincronizado');

        $this->assertSame([
            ['repository' => 'octocat/hello-world', 'branch' => 'main'],
        ], $docs->indexableCalls);
        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => '# Novo',
        ]);
        $this->assertDatabaseMissing('documents', [
            'project_id' => $project->id,
            'name' => 'docs/antigo.md',
        ]);
        $this->assertDatabaseMissing('documents', [
            'project_id' => $project->id,
            'name' => 'docs/regras.txt',
        ]);
        $this->assertSame(1, $project->documents()->count());
    }

    public function test_sync_keeps_documents_and_warns_when_indexing_fails(): void
    {
        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queueIndexable(ProjectIndexableFilesResult::failed('mcp'));

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'App mobile']);
        Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'README.md',
            'content' => 'Atual',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('syncDocuments', $project->id)
            ->assertSee('A sincronização falhou. Tente novamente mais tarde.')
            ->assertDontSee('Sincronização concluída.')
            ->assertDontSee('Sem documento sincronizado');

        $this->assertDatabaseHas('documents', [
            'project_id' => $project->id,
            'content' => 'Atual',
        ]);
        $this->assertSame(1, $project->documents()->count());
    }

    public function test_sync_clears_documents_when_read_is_empty(): void
    {
        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queueIndexable(ProjectIndexableFilesResult::empty());

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'App mobile']);
        Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'docs/antigo.md',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->call('syncDocuments', $project->id)
            ->assertSee('Sincronização concluída.')
            ->assertSee('Sem documento sincronizado');

        $this->assertSame(0, $project->documents()->count());
    }

    public function test_edit_does_not_index_documents(): void
    {
        $docs = $this->bindFakeProjectDocsGateway();
        $docs->queueIndexable(ProjectIndexableFilesResult::ok([
            ['path' => 'README.md', 'content' => '# Nao deveria indexar'],
        ]));

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create([
            'name' => 'Nome antigo',
            'repository' => 'octocat/hello-world',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->assertSee('Sem documento sincronizado')
            ->call('startEdit', $project->id)
            ->set('name', 'App mobile')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Sem documento sincronizado');

        $this->assertSame([], $docs->indexableCalls);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_label_is_hidden_when_project_already_has_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['name' => 'App mobile']);
        Document::factory()->create([
            'project_id' => $project->id,
            'name' => 'README.md',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminProjects::class)
            ->assertSee('App mobile')
            ->assertDontSee('Sem documento sincronizado');
    }

    private function bindFakeProjectDocsGateway(): FakeProjectDocsGateway
    {
        $fake = new FakeProjectDocsGateway;
        $this->app->instance(ProjectDocsGateway::class, $fake);

        return $fake;
    }
}
