<?php

namespace App\Livewire;

use App\Models\Project;
use App\Rules\GitHubRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        $validated = $this->validate($this->rules(), $this->messages());

        try {
            if ($this->editingId !== null) {
                $project = Project::query()->findOrFail($this->editingId);
                $project->update($validated);

                Log::info('Projeto editado.', [
                    'user_id' => Auth::id(),
                    'project_id' => $project->id,
                ]);

                $this->statusMessage = 'Projeto atualizado.';
            } else {
                $project = Project::query()->create($validated);

                Log::info('Projeto criado.', [
                    'user_id' => Auth::id(),
                    'project_id' => $project->id,
                ]);

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

        Log::info('Projeto desativado.', [
            'user_id' => Auth::id(),
            'project_id' => $project->id,
        ]);

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
        ];
    }

    private function repositoryConflictMessage(): string
    {
        return 'Já existe um projeto com este repositório, inclusive desativado. Edite o registro existente; não é possível cadastrar o mesmo owner/repo de novo.';
    }

    private function resetForm(): void
    {
        $this->reset('name', 'repository', 'editingId', 'showForm');
        $this->resetValidation();
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->can('admin'), 403);
    }
}
