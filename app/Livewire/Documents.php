<?php

namespace App\Livewire;

use App\Models\Document;
use App\Services\DocumentChunker;
use App\Services\DocumentSearch;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Documentos — PM Helper')]
class Documents extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public bool $showForm = false;

    public ?string $statusMessage = null;

    public string $name = '';

    public string $content = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    public string $question = '';

    /** @var list<array{document: string, chunk_index: int, content: string, score: float}> */
    public array $matches = [];

    public bool $searched = false;

    public function startCreate(): void
    {
        $this->resetForm();
        $this->statusMessage = null;
        $this->showForm = true;
    }

    public function startEdit(int $documentId): void
    {
        $document = Document::query()->findOrFail($documentId);

        $this->editingId = $document->id;
        $this->name = $document->name;
        $this->content = $document->content;
        $this->file = null;
        $this->statusMessage = null;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->name = trim($this->name);

        if ($this->editingId === null) {
            $this->validate($this->createRules(), $this->messages());
            $this->applyUploadedFile();
        } else {
            $this->validate($this->updateRules(), $this->messages());
        }

        if ($this->editingId !== null) {
            $document = Document::query()->findOrFail($this->editingId);
            $document->update([
                'name' => $this->name,
                'content' => $this->content,
            ]);

            $this->statusMessage = 'Documento atualizado.';
        } else {
            $document = Document::query()->create([
                'name' => $this->name,
                'content' => $this->content,
            ]);

            app(DocumentChunker::class)->chunk($document);

            $this->statusMessage = 'Documento enviado.';
        }

        $this->resetForm();
    }

    public function search(): void
    {
        $this->question = trim($this->question);
        $this->validate([
            'question' => ['required', 'string'],
        ], [
            'question.required' => 'Digite uma pergunta.',
        ]);

        $this->matches = app(DocumentSearch::class)->search($this->question);
        $this->searched = true;
    }

    public function destroy(int $documentId): void
    {
        $document = Document::query()->findOrFail($documentId);
        $document->delete();

        if ($this->editingId === $documentId) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        return view('livewire.documents', [
            'documents' => Document::query()->withCount('chunks')->latest()->get(),
        ]);
    }

    private function applyUploadedFile(): void
    {
        $originalName = $this->file->getClientOriginalName();
        $this->name = $originalName;
        $this->content = (string) file_get_contents($this->file->getRealPath());
    }

    /**
     * @return array<string, mixed>
     */
    private function createRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_object($value) || ! method_exists($value, 'getClientOriginalName')) {
                        return;
                    }

                    if (! str_ends_with(strtolower($value->getClientOriginalName()), '.md')) {
                        $fail('Envie apenas arquivos com extensão .md.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'file.required' => 'Selecione um arquivo .md.',
            'file.max' => 'O arquivo não pode passar de 5 MB.',
            'name.required' => 'O nome é obrigatório.',
            'content.required' => 'O conteúdo é obrigatório.',
        ];
    }

    private function resetForm(): void
    {
        $this->reset('name', 'content', 'file', 'editingId', 'showForm');
        $this->resetValidation();
    }
}
