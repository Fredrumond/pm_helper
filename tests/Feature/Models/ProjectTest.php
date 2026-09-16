<?php

namespace Tests\Feature\Models;

use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_name_and_repository(): void
    {
        $project = Project::factory()->create([
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'App mobile',
            'repository' => 'octocat/hello-world',
            'deleted_at' => null,
        ]);
    }

    public function test_repository_must_be_unique_among_active_projects(): void
    {
        Project::factory()->create([
            'repository' => 'octocat/hello-world',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Project::factory()->create([
            'repository' => 'octocat/hello-world',
        ]);
    }

    public function test_delete_hides_from_default_query_and_keeps_row_with_trashed(): void
    {
        $project = Project::factory()->create([
            'repository' => 'octocat/hello-world',
        ]);

        $project->delete();

        $this->assertNull(Project::query()->find($project->id));
        $this->assertFalse(Project::query()->where('repository', 'octocat/hello-world')->exists());
        $this->assertTrue(Project::withTrashed()->whereKey($project->id)->exists());
        $this->assertNotNull(Project::withTrashed()->find($project->id)?->deleted_at);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'repository' => 'octocat/hello-world',
        ]);
    }

    public function test_soft_deleted_repository_stays_unique_and_is_restored_instead_of_duplicated(): void
    {
        $project = Project::factory()->create([
            'repository' => 'octocat/hello-world',
        ]);
        $project->delete();

        try {
            Project::factory()->create([
                'repository' => 'octocat/hello-world',
            ]);
            $this->fail('Expected unique constraint to reject a duplicate of a soft-deleted repository.');
        } catch (UniqueConstraintViolationException) {
            // reativar via restore, não duplicar
        }

        $project->restore();

        $this->assertTrue(Project::query()->where('repository', 'octocat/hello-world')->exists());
        $this->assertSame(1, Project::withTrashed()->where('repository', 'octocat/hello-world')->count());
    }
}
