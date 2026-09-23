<?php

namespace App\Livewire;

use App\Exceptions\LlmModelInUseException;
use App\Exceptions\LlmModelValidationException;
use App\Models\LlmModel;
use App\Services\LlmModelCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Modelos LLM — PM Helper')]
class AdminLlmModels extends Component
{
    /**
     * @var array<string, string>
     */
    public const PROVIDER_LABELS = [
        LlmModel::PROVIDER_OPENROUTER => 'OpenRouter',
        LlmModel::PROVIDER_OPENAI => 'OpenAI',
    ];

    /**
     * @var array<string, string>
     */
    public const PROMPT_LABELS = AdminPromptModels::LABELS;

    public string $model_id = '';

    public string $name = '';

    public string $tier = LlmModel::TIER_FREE;

    public string $provider = LlmModel::PROVIDER_OPENROUTER;

    public string $price_input = '';

    public string $price_cached = '';

    public string $price_output = '';

    public bool $showForm = false;

    public ?int $editingPriceId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->ensureAdmin();
    }

    public function startCreate(): void
    {
        $this->ensureAdmin();
        $this->resetForm();
        $this->clearMessages();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->ensureAdmin();
        $this->resetForm();
    }

    public function save(LlmModelCatalog $catalog): void
    {
        $this->ensureAdmin();
        $this->clearMessages();

        $this->model_id = trim($this->model_id);
        $this->name = trim($this->name);

        $validated = $this->validate($this->createRules(), $this->createMessages());

        $payload = [
            'model_id' => $validated['model_id'],
            'name' => $validated['name'],
            'tier' => $validated['tier'],
            'provider' => $validated['provider'],
        ];

        if ($validated['tier'] === LlmModel::TIER_PAID) {
            $payload['price_input'] = $validated['price_input'];
            $payload['price_cached'] = $validated['price_cached'] !== '' && $validated['price_cached'] !== null
                ? $validated['price_cached']
                : null;
            $payload['price_output'] = $validated['price_output'];
        }

        try {
            $catalog->register($payload);
        } catch (LlmModelValidationException $e) {
            $this->addError('model_id', $e->getMessage());

            return;
        }

        $this->statusMessage = 'Modelo cadastrado.';
        $this->resetForm();
    }

    public function activate(int $id, LlmModelCatalog $catalog): void
    {
        $this->ensureAdmin();
        $this->clearMessages();

        $catalog->activate($id);
        $this->statusMessage = 'Modelo ativado.';
    }

    public function deactivate(int $id, LlmModelCatalog $catalog): void
    {
        $this->ensureAdmin();
        $this->clearMessages();

        try {
            $catalog->deactivate($id);
        } catch (LlmModelInUseException $e) {
            $this->errorMessage = $this->inUseMessage($e);

            return;
        }

        $this->statusMessage = 'Modelo inativado.';

        if ($this->editingPriceId === $id) {
            $this->cancelEditPrice();
        }
    }

    public function startEditPrice(int $id): void
    {
        $this->ensureAdmin();
        $this->clearMessages();

        $model = LlmModel::query()->findOrFail($id);

        if (! $model->isPaid()) {
            $this->addError('price_input', 'Só modelos pagos têm preço editável.');

            return;
        }

        $this->editingPriceId = $model->id;
        $this->price_input = $model->price_input !== null ? (string) $model->price_input : '';
        $this->price_cached = $model->price_cached !== null ? (string) $model->price_cached : '';
        $this->price_output = $model->price_output !== null ? (string) $model->price_output : '';
        $this->showForm = false;
        $this->resetValidation();
    }

    public function cancelEditPrice(): void
    {
        $this->ensureAdmin();
        $this->reset('editingPriceId', 'price_input', 'price_cached', 'price_output');
        $this->resetValidation();
    }

    public function savePrice(LlmModelCatalog $catalog): void
    {
        $this->ensureAdmin();
        $this->clearMessages();

        if ($this->editingPriceId === null) {
            return;
        }

        $validated = $this->validate($this->priceRules(), $this->priceMessages());

        try {
            $catalog->updatePrice($this->editingPriceId, [
                'price_input' => $validated['price_input'],
                'price_cached' => $validated['price_cached'] !== '' && $validated['price_cached'] !== null
                    ? $validated['price_cached']
                    : null,
                'price_output' => $validated['price_output'],
            ]);
        } catch (LlmModelValidationException $e) {
            $this->addError('price_input', $e->getMessage());

            return;
        }

        $this->statusMessage = 'Preço atualizado.';
        $this->cancelEditPrice();
    }

    public function render(LlmModelCatalog $catalog): View
    {
        $this->ensureAdmin();

        return view('livewire.admin-llm-models', [
            'grouped' => $catalog->list()->groupBy('provider'),
            'providerLabels' => self::PROVIDER_LABELS,
            'providers' => LlmModel::PROVIDERS,
            'tiers' => LlmModel::TIERS,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createRules(): array
    {
        $rules = [
            'model_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string', Rule::in(LlmModel::TIERS)],
            'provider' => ['required', 'string', Rule::in(LlmModel::PROVIDERS)],
            'price_input' => ['nullable'],
            'price_cached' => ['nullable'],
            'price_output' => ['nullable'],
        ];

        if ($this->tier === LlmModel::TIER_PAID) {
            $rules['price_input'] = ['required', 'numeric', 'min:0'];
            $rules['price_cached'] = ['nullable', 'numeric', 'min:0'];
            $rules['price_output'] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function createMessages(): array
    {
        return [
            'model_id.required' => 'O identificador do modelo é obrigatório.',
            'name.required' => 'O nome do modelo é obrigatório.',
            'tier.required' => 'O tier é obrigatório.',
            'tier.in' => 'Tier inválido. Use free ou paid.',
            'provider.required' => 'O provedor é obrigatório.',
            'provider.in' => 'Provedor inválido. Use openrouter ou openai.',
            'price_input.required' => 'Modelo pago exige preço de entrada.',
            'price_input.numeric' => 'O preço de entrada deve ser numérico.',
            'price_cached.numeric' => 'O preço de cache deve ser numérico.',
            'price_output.required' => 'Modelo pago exige preço de saída.',
            'price_output.numeric' => 'O preço de saída deve ser numérico.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceRules(): array
    {
        return [
            'price_input' => ['required', 'numeric', 'min:0'],
            'price_cached' => ['nullable', 'numeric', 'min:0'],
            'price_output' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function priceMessages(): array
    {
        return [
            'price_input.required' => 'Modelo pago exige preço de entrada.',
            'price_input.numeric' => 'O preço de entrada deve ser numérico.',
            'price_cached.numeric' => 'O preço de cache deve ser numérico.',
            'price_output.required' => 'Modelo pago exige preço de saída.',
            'price_output.numeric' => 'O preço de saída deve ser numérico.',
        ];
    }

    private function inUseMessage(LlmModelInUseException $e): string
    {
        $labels = array_map(
            fn (string $prompt): string => self::PROMPT_LABELS[$prompt] ?? $prompt,
            $e->prompts
        );

        return sprintf(
            'O modelo [%s] não pode ser inativado: está em uso pelo(s) prompt(s): %s. Troque o modelo desses prompts em Modelos dos prompts antes de inativar.',
            $e->modelId,
            implode(', ', $labels)
        );
    }

    private function resetForm(): void
    {
        $this->reset('model_id', 'name', 'tier', 'provider', 'price_input', 'price_cached', 'price_output', 'showForm');
        $this->tier = LlmModel::TIER_FREE;
        $this->provider = LlmModel::PROVIDER_OPENROUTER;
        $this->resetValidation();
    }

    private function clearMessages(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->can('admin'), 403);
    }
}
