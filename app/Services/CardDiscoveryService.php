<?php

namespace App\Services;

use App\Contracts\AgentGateway;
use App\Exceptions\CardDiscoveryNotAllowed;
use App\Jobs\AdvanceCardDiscovery;
use App\Models\Card;
use App\Models\CardDiscovery;
use App\Models\User;
use App\Services\Agents\AgentProviderException;
use App\Services\Agents\AgentTransientException;
use App\Services\Agents\DiscoveryAgentHandle;
use App\Services\Agents\DiscoveryAgentRequest;
use App\Services\Agents\DiscoveryPromptBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CardDiscoveryService
{
    public const MESSAGE_FAILED = 'Não foi possível gerar o Discovery.';

    public const MESSAGE_TIMEOUT = 'O tempo da geração esgotou.';

    public const MESSAGE_EMPTY = 'O agente terminou sem devolver o Discovery.';

    public const MESSAGE_AGENT = 'O agente não concluiu o Discovery.';

    public function __construct(
        private readonly AgentGateway $agents,
        private readonly DiscoveryPromptBuilder $prompts,
    ) {}

    public function request(Card $card, User $actor): CardDiscovery
    {
        if ($card->user_id !== $actor->id) {
            Log::warning('card_discovery.forbidden', [
                'card_id' => $card->id,
                'user_id' => $actor->id,
            ]);
            abort(403);
        }

        $card->loadMissing('conversation.project');
        $project = $card->conversation?->project;

        if (! $card->isApproved()) {
            throw new CardDiscoveryNotAllowed('O Discovery só pode ser gerado em card aprovado.');
        }

        if ($project === null || trim((string) $project->repository) === '' || trim((string) $project->branch) === '') {
            throw new CardDiscoveryNotAllowed('O Discovery exige um projeto com repositório na conversa.');
        }

        try {
            $discovery = DB::transaction(function () use ($card): CardDiscovery {
                $current = CardDiscovery::query()->where('card_id', $card->id)->lockForUpdate()->first();

                if ($current !== null && $current->isInProgress()) {
                    throw new CardDiscoveryNotAllowed('Já existe um Discovery em andamento para este card.');
                }

                return CardDiscovery::query()->updateOrCreate(
                    ['card_id' => $card->id],
                    [
                        'provider' => $this->agents->provider(),
                        'status' => CardDiscovery::STATUS_REQUESTED,
                        'external_agent_id' => null,
                        'external_run_id' => null,
                        'error_message' => null,
                        'started_at' => now(),
                        'finished_at' => null,
                        'deadline_at' => now()->addMinutes(max(1, (int) config('services.cursor.timeout_minutes'))),
                    ],
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw new CardDiscoveryNotAllowed('Já existe um Discovery em andamento para este card.');
        }

        Log::info('card_discovery.requested', [
            'card_id' => $card->id,
            'card_discovery_id' => $discovery->id,
            'provider' => $discovery->provider,
            'user_id' => $actor->id,
        ]);

        AdvanceCardDiscovery::dispatch($discovery->id);

        return $discovery->fresh() ?? $discovery;
    }

    public function advance(CardDiscovery $discovery): bool
    {
        $discovery->refresh();

        if ($discovery->isTerminal()) {
            return false;
        }

        if ($this->deadlinePassed($discovery)) {
            $this->markFailed($discovery, self::MESSAGE_TIMEOUT, 'timeout');

            return false;
        }

        try {
            if ($discovery->external_agent_id === null || $discovery->external_run_id === null) {
                $this->startRun($discovery);
                $discovery->refresh();
            }

            $snapshot = $this->agents->fetch(new DiscoveryAgentHandle(
                (string) $discovery->external_agent_id,
                (string) $discovery->external_run_id,
            ));
        } catch (AgentTransientException $exception) {
            Log::warning('card_discovery.transient', [
                'card_discovery_id' => $discovery->id,
                'card_id' => $discovery->card_id,
                'reason' => $exception->getMessage(),
            ]);

            return true;
        } catch (AgentProviderException $exception) {
            $this->markFailed($discovery, self::MESSAGE_FAILED, $exception->getMessage());

            return false;
        } catch (\Throwable $exception) {
            Log::error('card_discovery.failed', [
                'card_discovery_id' => $discovery->id,
                'card_id' => $discovery->card_id,
                'reason' => 'unexpected',
                'exception' => $exception::class,
            ]);
            $this->markFailed($discovery, self::MESSAGE_FAILED, 'unexpected');

            return false;
        }

        if ($snapshot->isRunning()) {
            if ($discovery->status !== CardDiscovery::STATUS_RUNNING) {
                $discovery->forceFill(['status' => CardDiscovery::STATUS_RUNNING])->save();
            }

            return true;
        }

        if ($snapshot->isFinished()) {
            $markdown = trim((string) $snapshot->markdown);
            if ($markdown === '') {
                $this->markFailed($discovery, self::MESSAGE_EMPTY, 'empty_result');

                return false;
            }

            $this->store($discovery, $markdown);

            return false;
        }

        $this->markFailed($discovery, self::MESSAGE_AGENT, $snapshot->detail ?? 'agent_failed');

        return false;
    }

    public function markTimedOut(CardDiscovery $discovery): void
    {
        $discovery->refresh();
        if ($discovery->isTerminal()) {
            return;
        }

        $this->markFailed($discovery, self::MESSAGE_TIMEOUT, 'timeout');
    }

    private function startRun(CardDiscovery $discovery): void
    {
        $card = $discovery->card()->with('conversation.project')->first();
        $project = $card?->conversation?->project;

        if ($card === null || $project === null || trim((string) $project->repository) === '' || trim((string) $project->branch) === '') {
            throw new AgentProviderException('missing_project');
        }

        $handle = $this->agents->start(new DiscoveryAgentRequest(
            name: 'Discovery: '.mb_substr($card->title, 0, 80),
            prompt: $this->prompts->build($card),
            repository: (string) $project->repository,
            branch: (string) $project->branch,
        ));

        $discovery->forceFill([
            'status' => CardDiscovery::STATUS_RUNNING,
            'provider' => $this->agents->provider(),
            'external_agent_id' => $handle->agentId,
            'external_run_id' => $handle->runId,
        ])->save();
    }

    private function store(CardDiscovery $discovery, string $markdown): void
    {
        $path = 'cards/'.$discovery->card_id.'/discovery.md';
        $written = Storage::disk('discoveries')->put($path, $markdown);

        if ($written === false) {
            $this->markFailed($discovery, self::MESSAGE_FAILED, 'store_failed');

            return;
        }

        $discovery->forceFill([
            'status' => CardDiscovery::STATUS_COMPLETED,
            'error_message' => null,
            'document_disk' => 'discoveries',
            'document_path' => $path,
            'finished_at' => now(),
        ])->save();

        Log::info('card_discovery.completed', [
            'card_id' => $discovery->card_id,
            'card_discovery_id' => $discovery->id,
            'provider' => $discovery->provider,
        ]);
    }

    private function markFailed(CardDiscovery $discovery, string $message, string $reason): void
    {
        $discovery->forceFill([
            'status' => CardDiscovery::STATUS_FAILED,
            'error_message' => $message,
            'finished_at' => now(),
        ])->save();

        Log::warning('card_discovery.failed', [
            'card_id' => $discovery->card_id,
            'card_discovery_id' => $discovery->id,
            'provider' => $discovery->provider,
            'reason' => $reason,
        ]);
    }

    private function deadlinePassed(CardDiscovery $discovery): bool
    {
        return $discovery->deadline_at !== null && now()->greaterThan($discovery->deadline_at);
    }
}
