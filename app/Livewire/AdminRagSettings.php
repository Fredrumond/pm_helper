<?php

namespace App\Livewire;

use App\Models\LlmModel;
use App\Services\LlmModelCatalog;
use App\Services\RagConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Parâmetros do RAG — PM Helper')]
class AdminRagSettings extends Component
{
    public const REINDEX_NOTICE = 'Os documentos já indexados precisam ser reindexados para a busca usar o valor novo.';

    public string $chunkSize = '';

    public string $embeddingModel = '';

    public string $topK = '';

    public ?string $statusMessage = null;

    public ?string $reindexNotice = null;

    public function mount(RagConfig $rag): void
    {
        $this->ensureAdmin();
        $this->fillFrom($rag);
    }

    public function save(RagConfig $rag): void
    {
        $this->ensureAdmin();

        $validated = $this->validate($this->rules(), $this->messages());

        $previousChunk = $rag->chunkSize();
        $previousModel = $rag->embeddingModel();
        $chunk = (int) $validated['chunkSize'];
        $model = $validated['embeddingModel'];
        $topK = (int) $validated['topK'];

        if (! $rag->save($chunk, $model, $topK)) {
            $this->addError('embeddingModel', 'Escolha um modelo da lista.');

            return;
        }

        $this->chunkSize = (string) $chunk;
        $this->embeddingModel = $model;
        $this->topK = (string) $topK;
        $this->statusMessage = 'Parâmetros atualizados.';
        $this->reindexNotice = ($chunk !== $previousChunk || $model !== $previousModel)
            ? self::REINDEX_NOTICE
            : null;
    }

    public function render(RagConfig $rag, LlmModelCatalog $catalog): View
    {
        $this->ensureAdmin();

        $models = $catalog->list()->map(fn (LlmModel $model): array => [
            'id' => $model->model_id,
            'name' => $model->name,
            'active' => $model->active,
        ])->all();

        return view('livewire.admin-rag-settings', [
            'catalog' => $models,
            'reindexLabel' => $this->indexParametersDiffer($rag)
                ? self::REINDEX_NOTICE
                : $this->reindexNotice,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $ids = array_column($this->catalogOptions(), 'id');

        return [
            'chunkSize' => ['required', 'integer'],
            'embeddingModel' => ['required', 'string', Rule::in($ids)],
            'topK' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'chunkSize.required' => 'Informe o tamanho do chunk.',
            'chunkSize.integer' => 'Informe um número inteiro.',
            'embeddingModel.required' => 'Escolha um modelo da lista.',
            'embeddingModel.in' => 'Escolha um modelo da lista.',
            'topK.required' => 'Informe o top-K.',
            'topK.integer' => 'Informe um número inteiro.',
        ];
    }

    private function fillFrom(RagConfig $rag): void
    {
        $this->chunkSize = (string) $rag->chunkSize();
        $this->embeddingModel = $rag->embeddingModel();
        $this->topK = (string) $rag->topK();
    }

    private function indexParametersDiffer(RagConfig $rag): bool
    {
        $chunkDiffers = is_numeric($this->chunkSize) && (int) $this->chunkSize !== $rag->chunkSize();
        $modelDiffers = $this->embeddingModel !== $rag->embeddingModel();

        return $chunkDiffers || $modelDiffers;
    }

    /**
     * @return list<array{id: string, name: string, active: bool}>
     */
    private function catalogOptions(): array
    {
        return app(LlmModelCatalog::class)->list()->map(fn (LlmModel $model): array => [
            'id' => $model->model_id,
            'name' => $model->name,
            'active' => $model->active,
        ])->all();
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->can('admin'), 403);
    }
}
