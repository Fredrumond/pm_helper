<?php

namespace App\Livewire;

use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Projetos — PM Helper')]
class SelectProjects extends Component
{
    public function select(int $projectId): void
    {
        $project = Project::query()->find($projectId);

        if ($project === null) {
            CurrentProject::forget();

            return;
        }

        CurrentProject::select($project);
    }

    public function clear(): void
    {
        CurrentProject::forget();
    }

    public function render(): View
    {
        return view('livewire.select-projects', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'selectedProjectId' => CurrentProject::id(),
        ]);
    }
}
