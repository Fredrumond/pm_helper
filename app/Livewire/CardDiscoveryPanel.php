<?php

namespace App\Livewire;

use App\Exceptions\CardDiscoveryNotAllowed;
use App\Models\Card;
use App\Models\CardDiscovery;
use App\Models\User;
use App\Services\CardDiscoveryService;
use App\Support\DiscoveryMarkdown;
use App\Support\DiscoveryQuestions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class CardDiscoveryPanel extends Component
{
    public Card $card;

    public bool $documentOpen = false;

    /** @var list<string> */
    public array $answers = [];

    public string $answersSyncKey = '';

    public function mount(Card $card): void
    {
        $this->card = $card;
        $this->authorizeReader();
    }

    public function generate(CardDiscoveryService $service): void
    {
        $this->authorizeOwner();

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        try {
            $service->request($this->card->fresh() ?? $this->card, $actor);
        } catch (CardDiscoveryNotAllowed $exception) {
            $this->addError('discovery', $exception->getMessage());
        }

        $this->card->refresh();
    }

    public function regenerateWithAnswers(CardDiscoveryService $service): void
    {
        $this->authorizeOwner();

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $card = $this->card->fresh() ?? $this->card;
        $discovery = $card->discovery;
        $markdown = $discovery ? $this->readDocument($discovery) : null;
        $questions = $markdown !== null ? DiscoveryQuestions::fromMarkdown($markdown) : [];
        $pairs = [];
        foreach ($questions as $index => $question) {
            $answer = trim((string) ($this->answers[$index] ?? ''));
            if ($answer === '') {
                continue;
            }
            $pairs[] = ['question' => $question, 'answer' => $answer];
        }

        if ($pairs === []) {
            $this->addError('discovery', 'Informe ao menos uma resposta para regenerar o Discovery.');
            $this->card->refresh();

            return;
        }

        try {
            $service->requestWithAnswers($card, $actor, $pairs);
        } catch (CardDiscoveryNotAllowed $exception) {
            $this->addError('discovery', $exception->getMessage());
        }

        $this->card->refresh();
    }

    public function toggleDocument(): void
    {
        $this->authorizeReader();
        $this->documentOpen = ! $this->documentOpen;
    }

    public function render()
    {
        $this->card->load(['discovery', 'conversation.project']);
        $discovery = $this->card->discovery;
        $markdown = $discovery ? $this->readDocument($discovery) : null;
        $questions = $markdown !== null ? DiscoveryQuestions::fromMarkdown($markdown) : [];
        $this->syncAnswers($questions, $discovery);
        $inProgress = $discovery?->isInProgress() ?? false;
        $owner = $this->isOwner();

        return view('livewire.card-discovery-panel', [
            'discovery' => $discovery,
            'visible' => $this->eligible() || $discovery !== null,
            'canGenerate' => $owner && $this->eligible() && ($discovery === null || ! $inProgress),
            'buttonLabel' => ($discovery !== null && ($discovery->isTerminal() || $markdown !== null))
                ? 'Gerar de novo'
                : 'Gerar Discovery',
            'attachmentTitle' => $markdown !== null ? DiscoveryMarkdown::title($markdown) : null,
            'documentHtml' => ($markdown !== null && $this->documentOpen) ? DiscoveryMarkdown::toHtml($markdown) : null,
            'hasDocument' => $markdown !== null,
            'questions' => $owner ? $questions : [],
            'answersLocked' => $inProgress,
            'canRegenerateWithAnswers' => $owner && $markdown !== null && $questions !== [] && ! $inProgress && $this->hasFilledAnswer(),
        ]);
    }

    /**
     * @param  list<string>  $questions
     */
    private function syncAnswers(array $questions, ?CardDiscovery $discovery): void
    {
        $key = ($discovery?->id ?? '0')
            .'|'.($discovery?->status ?? '')
            .'|'.($discovery?->updated_at?->getTimestamp() ?? '0')
            .'|'.implode("\n", $questions);

        if ($this->answersSyncKey === $key) {
            return;
        }

        $stored = [];
        foreach ($discovery?->pending_answers ?? [] as $pair) {
            if (! is_array($pair)) {
                continue;
            }
            $question = trim((string) ($pair['question'] ?? ''));
            if ($question === '') {
                continue;
            }
            $stored[$question] = (string) ($pair['answer'] ?? '');
        }

        $this->answers = [];
        foreach ($questions as $question) {
            $this->answers[] = $stored[$question] ?? '';
        }
        $this->answersSyncKey = $key;
    }

    private function hasFilledAnswer(): bool
    {
        foreach ($this->answers as $answer) {
            if (trim((string) $answer) !== '') {
                return true;
            }
        }

        return false;
    }

    private function eligible(): bool
    {
        $project = $this->card->conversation?->project;

        return $this->card->isApproved()
            && $project !== null
            && trim((string) $project->repository) !== ''
            && trim((string) $project->branch) !== '';
    }

    private function readDocument(CardDiscovery $discovery): ?string
    {
        if ($discovery->document_disk !== 'discoveries' || ! is_string($discovery->document_path)) {
            return null;
        }

        $path = $discovery->document_path;
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('discoveries');
        if (! $disk->exists($path)) {
            return null;
        }

        $markdown = $disk->get($path);

        return is_string($markdown) && trim($markdown) !== '' ? $markdown : null;
    }

    private function isOwner(): bool
    {
        return $this->card->user_id === Auth::id();
    }

    private function authorizeReader(): void
    {
        if ($this->isOwner() || Auth::user()?->can('admin')) {
            return;
        }

        $this->forbid();
    }

    private function authorizeOwner(): void
    {
        if ($this->isOwner()) {
            return;
        }

        $this->forbid();
    }

    private function forbid(): void
    {
        Log::warning('card_discovery.forbidden', [
            'card_id' => $this->card->id,
            'user_id' => Auth::id(),
        ]);
        abort(403);
    }
}
