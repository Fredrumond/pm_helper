<?php

namespace App\Livewire;

use App\Contracts\LlmGateway;
use App\Exceptions\LlmTemporarilyUnavailableException;
use App\Models\Conversation;
use App\Models\DocumentChunk;
use App\Models\Message;
use App\Models\Project;
use App\Prompts\SystemPromptCatalog;
use App\Services\DocumentRag;
use App\Services\PromptModelConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class ExploreChat extends Component
{
    public const MODEL = PromptModelConfig::EXPLORE_DEFAULT_MODEL;

    public Conversation $conversation;

    public string $input = '';

    #[Locked]
    public ?int $selectedProjectId = null;

    public function mount(Conversation $conversation): void
    {
        $this->ensureAdmin();
        abort_if($conversation->user_id !== Auth::id(), 403);
        abort_unless($conversation->isExplore(), 404);

        $this->conversation = $conversation;
        $this->forgetInactiveProjectIfNotStarted();
    }

    public function selectProject(mixed $projectId): void
    {
        $this->ensureAdmin();
        abort_if($this->conversation->user_id !== Auth::id(), 403);

        if ($this->conversationHasStarted()) {
            $this->hydrateSelectedProject();

            return;
        }

        $projectId = $this->activeProjectId($projectId);

        Conversation::query()
            ->whereKey($this->conversation->id)
            ->where('user_id', Auth::id())
            ->whereDoesntHave('messages')
            ->update(['project_id' => $projectId]);

        $this->conversation->refresh();
        $this->conversation->unsetRelation('project');
        $this->hydrateSelectedProject();
    }

    public function sendMessage(LlmGateway $llm, SystemPromptCatalog $prompts): void
    {
        $this->ensureAdmin();
        abort_if($this->conversation->user_id !== Auth::id(), 403);
        $this->forgetInactiveProjectIfNotStarted();

        if (trim($this->input) === '') {
            return;
        }

        $text = trim($this->input);
        $this->input = '';

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => $text,
        ]);

        if ($this->conversation->title === Conversation::EXPLORE_TITLE) {
            $this->conversation->update([
                'title' => Str::limit($text, 60),
            ]);
        }

        try {
            $this->conversation->refresh();
            $this->conversation->load('messages');

            $model = app(PromptModelConfig::class)->active('explore');
            $fromDocuments = $this->answerFromDocuments($text, $model);

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $fromDocuments['content'] ?? $this->answerFromExplore($llm, $prompts, $model),
                'sources' => $fromDocuments['sources'] ?? null,
            ]);
        } catch (LlmTemporarilyUnavailableException $e) {
            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Falha ao consultar a LLM no explorar', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => '⚠️ Erro LLM: '.$e->getMessage(),
            ]);
        }

        $this->conversation->refresh();
        $this->conversation->load('messages');
    }

    /**
     * @return array{content: string, sources: list<string>|null}|null
     */
    private function answerFromDocuments(string $question, string $model): ?array
    {
        $projectId = $this->conversation->project_id;

        $hasChunks = DocumentChunk::query()
            ->whereNotNull('embedding')
            ->whereHas('document', function ($documents) use ($projectId): void {
                if ($projectId === null) {
                    $documents->whereNull('project_id');
                } else {
                    $documents->where('project_id', $projectId);
                }
            })
            ->exists();

        if (! $hasChunks) {
            return null;
        }

        $result = app(DocumentRag::class)->ask($question, $model, $this->conversation, true);

        if ($result['answer'] === null) {
            return null;
        }

        return [
            'content' => $result['answer'],
            'sources' => $this->savedFilenames($result['matches']),
        ];
    }

    /**
     * @param  list<array{document: string, chunk_index: int, content: string, score: float}>  $matches
     * @return list<string>|null
     */
    private function savedFilenames(array $matches): ?array
    {
        $names = [];

        foreach ($matches as $match) {
            $name = $match['document'];

            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names === [] ? null : $names;
    }

    private function answerFromExplore(LlmGateway $llm, SystemPromptCatalog $prompts, string $model): string
    {
        $prompt = $prompts->current('explore');

        return $llm->completePrompt(
            array_merge(
                [['role' => 'system', 'content' => $prompt->content]],
                $this->conversation->toLlmHistory(),
            ),
            $model,
            'explore',
            $this->conversation,
        );
    }

    public function render()
    {
        $this->forgetInactiveProjectIfNotStarted();

        $this->conversation->unsetRelation('project');
        $this->conversation->load(['messages', 'project']);

        return view('livewire.explore-chat', [
            'messages' => $this->conversation->messages,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'selectedProjectName' => $this->conversation->project?->name,
        ]);
    }

    private function hydrateSelectedProject(): void
    {
        $projectId = $this->conversation->project_id;

        $this->selectedProjectId = $projectId !== null ? (int) $projectId : null;
    }

    private function conversationHasStarted(): bool
    {
        return $this->conversation->messages()->exists();
    }

    private function forgetInactiveProjectIfNotStarted(): void
    {
        if ($this->conversationHasStarted()) {
            $this->hydrateSelectedProject();

            return;
        }

        $projectId = $this->conversation->project_id;

        if ($projectId !== null && ! Project::query()->whereKey($projectId)->exists()) {
            Conversation::query()
                ->whereKey($this->conversation->id)
                ->where('user_id', Auth::id())
                ->whereDoesntHave('messages')
                ->update(['project_id' => null]);

            $this->conversation->refresh();
            $this->conversation->unsetRelation('project');
        }

        $this->hydrateSelectedProject();
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->can('admin'), 403);
    }

    private function activeProjectId(mixed $projectId): ?int
    {
        if ($projectId === null || $projectId === '') {
            return null;
        }

        if (! is_int($projectId) && ! (is_string($projectId) && preg_match('/^\d+$/', $projectId) === 1)) {
            return null;
        }

        $id = (int) $projectId;

        if ($id < 1 || ! Project::query()->whereKey($id)->exists()) {
            return null;
        }

        return $id;
    }
}
