<?php

namespace Tests\Feature\Support;

use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_id_returns_the_active_project_without_going_through_select_projects(): void
    {
        $project = Project::factory()->create();

        $this->session([CurrentProject::SESSION_KEY => $project->id]);

        $this->assertSame($project->id, CurrentProject::id());
        $this->assertTrue(session()->has(CurrentProject::SESSION_KEY));
    }

    public function test_id_clears_stale_or_soft_deleted_selection(): void
    {
        $project = Project::factory()->create();
        $this->session([CurrentProject::SESSION_KEY => $project->id]);
        $project->delete();

        $this->assertNull(CurrentProject::id());
        $this->assertFalse(session()->has(CurrentProject::SESSION_KEY));

        $this->session([CurrentProject::SESSION_KEY => 999_999]);

        $this->assertNull(CurrentProject::id());
        $this->assertFalse(session()->has(CurrentProject::SESSION_KEY));
    }

    public function test_select_and_forget_write_the_session_key(): void
    {
        $project = Project::factory()->create();

        $this->startSession();
        CurrentProject::select($project);

        $this->assertSame($project->id, session(CurrentProject::SESSION_KEY));

        CurrentProject::forget();

        $this->assertFalse(session()->has(CurrentProject::SESSION_KEY));
        $this->assertNull(CurrentProject::id());
    }
}
