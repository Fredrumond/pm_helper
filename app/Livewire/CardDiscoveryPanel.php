<?php

namespace App\Livewire;

use App\Exceptions\CardDiscoveryNotAllowed;
use App\Models\Card;
use App\Models\CardDiscovery;
use App\Models\User;
use App\Services\CardDiscoveryService;
use App\Support\DiscoveryMarkdown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class CardDiscoveryPanel extends Component
{
    public Card $card;

    public bool $documentOpen = false;

    public function mount(Card $card): void
    {
        $this->card = $card;
        $this->authorizeOwner();
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

    public function toggleDocument(): void
    {
        $this->authorizeOwner();
        $this->documentOpen = ! $this->documentOpen;
    }

    public function render()
    {
        $this->card->load(['discovery', 'conversation.project']);
        $discovery = $this->card->discovery;
        $markdown = $discovery ? $this->readDocument($discovery) : null;

        return view('livewire.card-discovery-panel', [
            'discovery' => $discovery,
            'visible' => $this->eligible() || $discovery !== null,
            'canGenerate' => $this->eligible() && ($discovery === null || ! $discovery->isInProgress()),
            'buttonLabel' => ($discovery !== null && ($discovery->isTerminal() || $markdown !== null))
                ? 'Gerar de novo'
                : 'Gerar Discovery',
            'attachmentTitle' => $markdown !== null ? DiscoveryMarkdown::title($markdown) : null,
            'documentHtml' => ($markdown !== null && $this->documentOpen) ? DiscoveryMarkdown::toHtml($markdown) : null,
            'hasDocument' => $markdown !== null,
        ]);
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

    private function authorizeOwner(): void
    {
        if ($this->card->user_id === Auth::id()) {
            return;
        }

        Log::warning('card_discovery.forbidden', [
            'card_id' => $this->card->id,
            'user_id' => Auth::id(),
        ]);
        abort(403);
    }
}
