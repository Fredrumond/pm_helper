<?php

namespace App\Livewire;

use App\Models\Project;
use App\Rules\GitHubBranch;
use App\Rules\GitHubRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Projetos — PM Helper')]
class AdminProjects extends Component
{
    public string $name = '';

    public string $repository = '';

    public string $branch = 'main';

    public ?int $editingId = null;

    public bool $showForm = false;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->ensureAdmin();
    }

    public function startCreate(): void
    {
        $this->ensureAdmin();
        $this->resetForm();
        $this->statusMessage = null;
        $this->showForm = true;
    }

    public function startEdit(int $projectId): void
    {
        $this->ensureAdmin();

        $project = Project::query()->findOrFail($projectId);

        $this->editingId = $project->id;
        $this->name = $project->name;
        $this->repository = $project->repository;
        $this->branch = (string) $project->branch;
        $this->statusMessage = null;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function cancelForm(): void
    {
        $this->ensureAdmin();
        $this->resetForm();
    }

    public function save(): void
    {
        $this->ensureAdmin();

        $this->name = trim($this->name);
        $this->repository = GitHubRepository::normalize($this->repository);
        $this->branch = GitHubBranch::normalize($this->branch);

        $validated = $this->validate($this->rules(), $this->messages());

        try {
            if ($this->editingId !== null) {
                $project = Project::query()->findOrFail($this->editingId);
                $project->update($validated);

                $this->statusMessage = 'Projeto atualizado.';
            } else {
                $project = Project::query()->create($validated);

                $this->statusMessage = 'Projeto cadastrado.';
            }
        } catch (UniqueConstraintViolationException) {
            $this->addError('repository', $this->repositoryConflictMessage());

            return;
        }

        $this->resetForm();
    }

    public function deactivate(int $projectId): void
    {
        $this->ensureAdmin();

        $project = Project::query()->findOrFail($projectId);
        $project->delete();

        if ($this->editingId === $projectId) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        $this->ensureAdmin();

        return view('livewire.admin-projects', [
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'repository' => [
                'required',
                'string',
                'max:255',
                new GitHubRepository,
                Rule::unique('projects', 'repository')->ignore($this->editingId),
            ],
            'branch' => ['required', 'string', 'max:255', new GitHubBranch],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'repository.required' => 'O repositório é obrigatório.',
            'repository.unique' => $this->repositoryConflictMessage(),
            'branch.required' => 'A branch é obrigatória.',
        ];
    }

    private function repositoryConflictMessage(): string
    {
        return 'Já existe um projeto com este repositório, inclusive desativado. Edite o registro existente; não é possível cadastrar o mesmo owner/repo de novo.';
    }

    private function resetForm(): void
    {
        $this->reset('name', 'repository', 'branch', 'editingId', 'showForm');
        $this->branch = 'main';
        $this->resetValidation();
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->can('admin'), 403);
    }
}
