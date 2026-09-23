<?php

namespace App\Livewire;

use App\Services\PromptModelConfig;
use App\Support\ChatComposer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Modelos dos prompts — PM Helper')]
class AdminPromptModels extends Component
{
    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'interview' => 'Entrevista',
        'docs_retrieval' => 'Retrieval de docs',
        'docs_briefing' => 'Briefing de docs',
        'card_generation' => 'Geração do card',
    ];

    /**
     * @var array<string, string>
     */
    public array $models = [];

    public ?string $statusMessage = null;

    public function mount(PromptModelConfig $promptModels): void
    {
        $this->ensureAdmin();
        $this->models = $this->currentModels($promptModels);
    }

    public function save(PromptModelConfig $promptModels): void
    {
        $this->ensureAdmin();

        $validated = $this->validate($this->rules(), $this->messages());

        /** @var array<string, string> $chosen */
        $chosen = $validated['models'];

        foreach (PromptModelConfig::PROMPTS as $prompt) {
            if (! $promptModels->save($prompt, $chosen[$prompt])) {
                $this->addError("models.{$prompt}", 'Escolha um modelo da lista.');

                return;
            }
        }

        $this->models = $chosen;
        $this->statusMessage = 'Modelos atualizados.';
    }

    public function render(): View
    {
        $this->ensureAdmin();

        return view('livewire.admin-prompt-models', [
            'prompts' => PromptModelConfig::PROMPTS,
            'labels' => self::LABELS,
            'catalog' => ChatComposer::models(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function currentModels(PromptModelConfig $promptModels): array
    {
        $models = [];

        foreach (PromptModelConfig::PROMPTS as $prompt) {
            $models[$prompt] = $promptModels->active($prompt);
        }

        return $models;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $ids = array_column(ChatComposer::models(), 'id');
        $rules = [];

        foreach (PromptModelConfig::PROMPTS as $prompt) {
            $rules["models.{$prompt}"] = ['required', 'string', Rule::in($ids)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        $messages = [];

        foreach (PromptModelConfig::PROMPTS as $prompt) {
            $messages["models.{$prompt}.required"] = 'Escolha um modelo da lista.';
            $messages["models.{$prompt}.in"] = 'Escolha um modelo da lista.';
        }

        return $messages;
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->can('admin'), 403);
    }
}
