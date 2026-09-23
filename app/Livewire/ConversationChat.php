<?php

namespace App\Livewire;

use App\Contracts\LlmGateway;
use App\Exceptions\LlmTemporarilyUnavailableException;
use App\Models\Card;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Services\CardParserService;
use App\Services\DocsRetrievalService;
use App\Services\ProjectDocsResult;
use App\Services\PromptModelConfig;
use App\Support\ProjectDocsReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class ConversationChat extends Component
{
    public Conversation $conversation;

    public string $input = '';

    #[Locked]
    public ?int $selectedProjectId = null;

    public bool $generatingCard = false;

    public function mount(Conversation $conversation): void
    {
        abort_if($conversation->user_id !== Auth::id(), 403);
        $this->conversation = $conversation;
        $this->forgetInactiveProjectIfNotStarted();
        session()->forget('chat.selected_model');
    }

    public function selectProject(mixed $projectId): void
    {
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

    public function sendMessage(LlmGateway $llm, CardParserService $parser): void
    {
        $this->forgetInactiveProjectIfNotStarted();

        if (trim($this->input) === '' || $this->conversation->isCompleted()) {
            return;
        }

        $text = trim($this->input);
        $this->input = '';

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => $text,
        ]);

        if ($this->conversation->title === 'Nova conversa') {
            $this->conversation->update([
                'title' => Str::limit($text, 60),
            ]);
        }

        if ($parser->isGenerateCardRequest($text)) {
            $this->conversation->refresh();
            $this->conversation->load('messages');

            if ($this->conversation->isScopeTooBroad()) {
                $this->refuseCardGenerationDueToBroadScope();

                return;
            }

            $this->ensureInterviewSummary($parser);
            $this->generateCard($llm, $parser);

            return;
        }

        try {
            $this->conversation->refresh();
            $this->conversation->load('messages');
            $alreadyScopeTooBroad = $this->conversation->isScopeTooBroad();

            $assistantResponse = $llm->chat($this->conversation, $this->activeModel('interview'));

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $assistantResponse,
            ]);

            if ($alreadyScopeTooBroad) {
                $this->persistScopeTooBroad();
            } else {
                $this->captureInterviewSummary($parser, $assistantResponse);
            }

            if ($parser->hasCard($assistantResponse) && ! $this->conversation->isScopeTooBroad()) {
                $this->persistCard($parser, $assistantResponse);
            }
        } catch (LlmTemporarilyUnavailableException $e) {
            $this->recordSoftLlmUnavailable($e);
        } catch (Throwable $e) {
            Log::error('Falha ao consultar a LLM', [
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
        $this->conversation->load(['messages', 'card']);

        if ($this->conversation->card !== null) {
            $this->redirect(route('conversations.show', $this->conversation));
        }
    }

    public function retryProjectDocsReview(DocsRetrievalService $retrieval): void
    {
        if ($this->conversation->isCompleted() || ! ProjectDocsReview::canRetry($this->conversation->id)) {
            return;
        }

        $this->reviewProjectDocs($retrieval);

        $this->conversation->refresh();
        $this->conversation->load(['messages', 'card']);
    }

    public function generateCard(LlmGateway $llm, CardParserService $parser): void
    {
        if ($this->generatingCard) {
            return;
        }

        $this->conversation->refresh();

        if ($this->conversation->isScopeTooBroad()) {
            $this->refuseCardGenerationDueToBroadScope();

            return;
        }

        $this->generatingCard = true;

        $this->conversation->load('messages');
        $this->ensureInterviewSummary($parser);

        $projectDocs = $this->projectDocsContextForCard();
        $hasProjectDocs = is_string($projectDocs) && $projectDocs !== '';

        Log::info('Geração de card solicitada', [
            'conversation_id' => $this->conversation->id,
            'has_project_docs' => $hasProjectDocs,
            'chars' => $hasProjectDocs ? mb_strlen($projectDocs, 'UTF-8') : 0,
        ]);

        if ($this->conversation->isCompleted()) {
            $this->generatingCard = false;

            return;
        }

        if (! $this->conversation->isInterviewComplete()) {
            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => 'Ainda não fechei a entrevista. Complemente o contexto no chat e tente gerar o card de novo.',
            ]);

            $this->conversation->refresh();
            $this->conversation->load(['messages', 'card']);
            $this->generatingCard = false;

            return;
        }

        try {
            $assistantResponse = $llm->generateCard(
                $this->conversation,
                (string) $this->conversation->interview_summary,
                $this->activeModel('card_generation'),
                $projectDocs,
            );

            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $assistantResponse,
            ]);

            if ($parser->hasCard($assistantResponse)) {
                $this->persistCard($parser, $assistantResponse);
            }
        } catch (LlmTemporarilyUnavailableException $e) {
            $this->recordSoftLlmUnavailable($e);
        } catch (Throwable $e) {
            Log::error('Falha ao gerar o card', [
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
        $this->conversation->load(['messages', 'card']);
        $this->generatingCard = false;

        if ($this->conversation->card !== null) {
            $this->redirect(route('conversations.show', $this->conversation));
        }
    }

    private function projectDocsContextForCard(): ?string
    {
        $payload = ProjectDocsReview::get($this->conversation->id);

        if (($payload['status'] ?? null) !== ProjectDocsResult::STATUS_OK) {
            return null;
        }

        $content = trim((string) ($payload['content'] ?? ''));

        return $content !== '' ? $content : null;
    }

    private function captureInterviewSummary(CardParserService $parser, string $response): void
    {
        $this->conversation->refresh();

        if ($this->conversation->isScopeTooBroad()) {
            return;
        }

        if ($parser->hasInterviewScopeTooBroad($response)) {
            $this->persistScopeTooBroad();

            return;
        }

        $summary = $parser->extractInterviewSummary($response);

        if ($summary === null && $parser->looksInterviewReady($response)) {
            $summary = $parser->extractTextOnly($response);
        }

        if ($summary === null || trim($summary) === '') {
            return;
        }

        $this->markInterviewReady($summary);
    }

    private function ensureInterviewSummary(CardParserService $parser): void
    {
        if ($this->conversation->isInterviewComplete() || $this->conversation->isScopeTooBroad()) {
            return;
        }

        $summary = $parser->summaryFromMessages($this->conversation->messages);

        if ($summary === null) {
            return;
        }

        $this->markInterviewReady($summary);
        $this->conversation->refresh();
    }

    private function markInterviewReady(string $summary): void
    {
        $this->conversation->update([
            'interview_summary' => $summary,
            'current_step' => 'card_generation',
        ]);

        $this->reviewProjectDocs();

        $this->dispatch('interview-ready');
    }

    private function reviewProjectDocs(?DocsRetrievalService $retrieval = null): void
    {
        $this->conversation->unsetRelation('project');
        $project = $this->conversation->project;

        if ($project === null) {
            return;
        }

        $retry = ProjectDocsReview::canRetry($this->conversation->id);

        Log::info($retry ? 'Retry da revisão de /docs' : 'Revisão de /docs disparada', [
            'conversation_id' => $this->conversation->id,
            'project_id' => $project->id,
            'repository' => $project->repository,
            'ref' => $project->branch,
        ]);

        try {
            $result = ($retrieval ?? app(DocsRetrievalService::class))
                ->retrieve(
                    $project,
                    (string) $this->conversation->interview_summary,
                    $this->conversation,
                );
        } catch (Throwable $exception) {
            Log::error('Falha inesperada na revisão de /docs', [
                'conversation_id' => $this->conversation->id,
                'project_id' => $project->id,
                'error' => $exception->getMessage(),
            ]);

            $result = ProjectDocsResult::failed(ProjectDocsResult::ERROR_MCP);
        }

        ProjectDocsReview::store($this->conversation->id, $project, $result);

        Log::info('Revisão de /docs concluída', [
            'conversation_id' => $this->conversation->id,
            'project_id' => $project->id,
            'repository' => $project->repository,
            'ref' => $project->branch,
            'status' => $result->status,
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => ProjectDocsReview::assistantMessage($result),
        ]);

        if (is_string($result->briefing) && trim($result->briefing) !== '') {
            Message::create([
                'conversation_id' => $this->conversation->id,
                'role' => 'assistant',
                'content' => $result->briefing,
            ]);
        }
    }

    private function persistScopeTooBroad(): void
    {
        $firstMark = ! $this->conversation->isScopeTooBroad();

        $this->conversation->update([
            'current_step' => 'scope_too_broad',
            'interview_summary' => null,
        ]);

        $this->dispatch('interview-scope-too-broad');

        if ($firstMark) {
            Log::info('Entrevista bloqueada por escopo amplo', [
                'conversation_id' => $this->conversation->id,
                'current_step' => 'scope_too_broad',
            ]);
        }
    }

    private function refuseCardGenerationDueToBroadScope(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => 'Este escopo ainda é amplo demais para um único card. Volte com um card mais definido para retomarmos a entrevista — não vou gerar o card agora.',
        ]);

        $this->conversation->refresh();
        $this->conversation->load(['messages', 'card']);
    }

    private function recordSoftLlmUnavailable(LlmTemporarilyUnavailableException $e): void
    {
        Log::warning('LLM temporariamente indisponível após fallbacks', [
            'conversation_id' => $this->conversation->id,
            'error' => $e->getMessage(),
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'role' => 'assistant',
            'content' => $e->getMessage(),
        ]);
    }

    private function persistCard(CardParserService $parser, string $response): void
    {
        try {
            $cardData = $parser->parse($response);

            Card::updateOrCreate(
                ['conversation_id' => $this->conversation->id],
                array_merge($cardData, [
                    'user_id' => $this->conversation->user_id,
                    'status' => 'draft',
                ])
            );

            $this->conversation->update(['status' => 'completed']);
        } catch (Throwable $e) {
            Log::error('Card parsing failed', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        $this->forgetInactiveProjectIfNotStarted();

        $this->conversation->unsetRelation('project');
        $this->conversation->load(['messages', 'card', 'project']);

        return view('livewire.conversation-chat', [
            'messages' => $this->conversation->messages,
            'card' => $this->conversation->card,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'selectedProjectName' => $this->conversation->project?->name,
            'showGenerateCardButton' => $this->conversation->isInterviewComplete()
                && ! $this->conversation->isCompleted()
                && ! $this->conversation->isScopeTooBroad(),
            'showRetryProjectDocsReview' => ProjectDocsReview::canRetry($this->conversation->id)
                && ! $this->conversation->isCompleted(),
        ]);
    }

    private function activeModel(string $prompt): string
    {
        return app(PromptModelConfig::class)->active($prompt);
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
