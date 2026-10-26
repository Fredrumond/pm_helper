<?php

namespace Tests\Feature\Services;

use App\Contracts\AgentGateway;
use App\Exceptions\CardDiscoveryNotAllowed;
use App\Jobs\AdvanceCardDiscovery;
use App\Models\Card;
use App\Models\CardDiscovery;
use App\Models\Project;
use App\Models\User;
use App\Services\Agents\AgentProviderException;
use App\Services\Agents\AgentTransientException;
use App\Services\Agents\DiscoveryAgentSnapshot;
use App\Services\CardDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesCards;
use Tests\Support\FakeAgentGateway;
use Tests\TestCase;

class CardDiscoveryServiceTest extends TestCase
{
    use CreatesCards, RefreshDatabase;

    public function test_request_dispatches_a_job_for_an_approved_card_with_a_project(): void
    {
        Queue::fake();
        Event::fake([MessageLogged::class]);
        $gateway = $this->fakeAgents();
        [$user, $card] = $this->approvedCard();

        $discovery = app(CardDiscoveryService::class)->request($card, $user);

        $this->assertSame(CardDiscovery::STATUS_REQUESTED, $discovery->status);
        $this->assertSame('cursor', $discovery->provider);
        $this->assertNull($discovery->document_path);
        $this->assertSame(0, $gateway->starts);
        Queue::assertPushed(AdvanceCardDiscovery::class, 1);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($card, $user): bool {
            return $log->message === 'card_discovery.requested'
                && $log->context['card_id'] === $card->id
                && $log->context['user_id'] === $user->id;
        });
    }

    public function test_draft_card_without_project_or_a_running_discovery_does_not_dispatch_again(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $service = app(CardDiscoveryService::class);
        $draft = $this->createCard($user, Project::factory()->create(), 'draft');

        try {
            $service->request($draft, $user);
            $this->fail('Rascunho não pode disparar.');
        } catch (CardDiscoveryNotAllowed $exception) {
            $this->assertSame('O Discovery só pode ser gerado em card aprovado.', $exception->getMessage());
        }

        $withoutProject = $this->createCard($user, null);
        try {
            $service->request($withoutProject, $user);
            $this->fail('Card sem projeto não pode disparar.');
        } catch (CardDiscoveryNotAllowed $exception) {
            $this->assertSame('O Discovery exige um projeto com repositório na conversa.', $exception->getMessage());
        }

        $project = Project::factory()->create(['branch' => '']);
        $emptyBranch = $this->createCard($user, $project);
        try {
            $service->request($emptyBranch, $user);
            $this->fail('Branch vazia não pode disparar.');
        } catch (CardDiscoveryNotAllowed) {
        }

        [$owner, $card] = $this->approvedCard();
        $this->fakeAgents();
        app(CardDiscoveryService::class)->request($card, $owner);

        try {
            app(CardDiscoveryService::class)->request($card->fresh(), $owner);
            $this->fail('Não pode disparar de novo enquanto está em andamento.');
        } catch (CardDiscoveryNotAllowed $exception) {
            $this->assertSame('Já existe um Discovery em andamento para este card.', $exception->getMessage());
        }

        Queue::assertPushed(AdvanceCardDiscovery::class, 1);
    }

    public function test_another_user_cannot_request_a_discovery(): void
    {
        Queue::fake();
        Event::fake([MessageLogged::class]);
        [$owner, $card] = $this->approvedCard();
        $other = User::factory()->create();

        try {
            app(CardDiscoveryService::class)->request($card, $other);
            $this->fail('Outro usuário deveria receber 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Queue::assertNothingPushed();
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($card, $other): bool {
            return $log->level === 'warning'
                && $log->message === 'card_discovery.forbidden'
                && $log->context['card_id'] === $card->id
                && $log->context['user_id'] === $other->id;
        });
    }

    public function test_finished_run_stores_the_markdown_on_the_private_disk(): void
    {
        Storage::fake('discoveries');
        Event::fake([MessageLogged::class]);
        config(['services.cursor.api_key' => 'cursor-test-key']);
        $gateway = $this->fakeAgents();
        $gateway->snapshots = [DiscoveryAgentSnapshot::finished("# Aviso de duplicata\n\nTexto do discovery.")];
        [$user, $card] = $this->approvedCard();

        $discovery = app(CardDiscoveryService::class)->request($card, $user);

        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $discovery->status);
        $this->assertSame('discoveries', $discovery->document_disk);
        $this->assertSame('cards/'.$card->id.'/discovery.md', $discovery->document_path);
        $this->assertSame('agent-1', $discovery->external_agent_id);
        $this->assertSame(
            "# Aviso de duplicata\n\nTexto do discovery.",
            Storage::disk('discoveries')->get($discovery->document_path),
        );
        $this->assertStringContainsString('Avisar card parecido', (string) $gateway->lastRequest?->prompt);
        $this->assertSame('acme/mobile-app', $gateway->lastRequest?->repository);
        $this->assertSame('develop', $gateway->lastRequest?->branch);
        $this->assertFalse(config('filesystems.disks.discoveries.serve'));
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $log): bool => $log->message === 'card_discovery.completed');
        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            $encoded = json_encode($log->context);

            return str_contains($log->message, 'cursor-test-key')
                || (is_string($encoded) && str_contains($encoded, 'cursor-test-key'));
        });
    }

    public function test_failure_empty_result_and_timeout_keep_the_previous_document(): void
    {
        Storage::fake('discoveries');
        $gateway = $this->fakeAgents();
        $gateway->snapshots = [DiscoveryAgentSnapshot::finished("# Antigo\n\nVersao boa.")];
        [$user, $card] = $this->approvedCard();
        $service = app(CardDiscoveryService::class);
        $path = 'cards/'.$card->id.'/discovery.md';

        $service->request($card, $user);
        $this->assertSame("# Antigo\n\nVersao boa.", Storage::disk('discoveries')->get($path));

        $gateway->snapshots = [DiscoveryAgentSnapshot::failed('ERROR')];
        $failed = $service->request($card->fresh(), $user);
        $this->assertSame(CardDiscovery::STATUS_FAILED, $failed->status);
        $this->assertSame(CardDiscoveryService::MESSAGE_AGENT, $failed->error_message);
        $this->assertSame($path, $failed->document_path);
        $this->assertSame("# Antigo\n\nVersao boa.", Storage::disk('discoveries')->get($path));

        $gateway->snapshots = [DiscoveryAgentSnapshot::finished('   ')];
        $empty = $service->request($card->fresh(), $user);
        $this->assertSame(CardDiscoveryService::MESSAGE_EMPTY, $empty->error_message);
        $this->assertSame("# Antigo\n\nVersao boa.", Storage::disk('discoveries')->get($path));

        $running = CardDiscovery::query()->where('card_id', $card->id)->firstOrFail();
        $running->forceFill([
            'status' => CardDiscovery::STATUS_RUNNING,
            'external_agent_id' => 'agent-1',
            'external_run_id' => 'run-1',
            'deadline_at' => now()->subMinute(),
            'error_message' => null,
        ])->save();
        $starts = $gateway->starts;

        $this->assertFalse($service->advance($running->fresh()));
        $this->assertSame($starts, $gateway->starts);
        $this->assertSame(CardDiscovery::STATUS_FAILED, $running->fresh()->status);
        $this->assertSame(CardDiscoveryService::MESSAGE_TIMEOUT, $running->fresh()->error_message);
        $this->assertSame("# Antigo\n\nVersao boa.", Storage::disk('discoveries')->get($path));
    }

    public function test_a_later_success_replaces_the_previous_document(): void
    {
        Storage::fake('discoveries');
        $gateway = $this->fakeAgents();
        $gateway->snapshots = [DiscoveryAgentSnapshot::finished("# Antigo\n\nVersao boa.")];
        [$user, $card] = $this->approvedCard();
        $service = app(CardDiscoveryService::class);

        $service->request($card, $user);

        $gateway->startException = new AgentProviderException('http_401');
        $denied = $service->request($card->fresh(), $user);
        $this->assertSame(CardDiscoveryService::MESSAGE_FAILED, $denied->error_message);
        $this->assertStringContainsString('Versao boa.', Storage::disk('discoveries')->get('cards/'.$card->id.'/discovery.md'));

        $gateway->startException = null;
        $gateway->snapshots = [DiscoveryAgentSnapshot::finished("# Novo\n\nSegunda versao.")];
        $replaced = $service->request($card->fresh(), $user);

        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $replaced->status);
        $this->assertNull($replaced->error_message);
        $stored = Storage::disk('discoveries')->get('cards/'.$card->id.'/discovery.md');
        $this->assertStringContainsString('Segunda versao.', $stored);
        $this->assertStringNotContainsString('Versao boa.', $stored);
    }

    public function test_transient_error_keeps_the_run_open_until_a_later_attempt(): void
    {
        Storage::fake('discoveries');
        $gateway = $this->fakeAgents();
        $gateway->fetchException = new AgentTransientException('http_503');
        [$user, $card] = $this->approvedCard();
        $service = app(CardDiscoveryService::class);

        $discovery = $service->request($card, $user);

        $this->assertSame(CardDiscovery::STATUS_RUNNING, $discovery->status);
        $this->assertSame('agent-1', $discovery->external_agent_id);
        $this->assertNull($discovery->document_path);

        $gateway->fetchException = null;
        $gateway->snapshots = [DiscoveryAgentSnapshot::finished("# Ok\n\nPronto.")];

        $this->assertFalse($service->advance($discovery->fresh()));
        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $discovery->fresh()->status);
        $this->assertSame("# Ok\n\nPronto.", Storage::disk('discoveries')->get('cards/'.$card->id.'/discovery.md'));
    }

    public function test_real_adapter_saves_a_finished_http_result(): void
    {
        Storage::fake('discoveries');
        config([
            'services.cursor.api_key' => 'cursor-test-key',
            'services.cursor.base_url' => 'https://api.cursor.com/v1',
            'services.cursor.model' => '',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'agent' => ['id' => 'agent_http'],
                'run' => ['id' => 'run_http'],
            ]),
            'https://api.cursor.com/v1/agents/agent_http/runs/run_http' => Http::response([
                'status' => 'FINISHED',
                'result' => "# Discovery de teste\n\nConteudo gerado.",
            ]),
        ]);
        $this->app->forgetInstance(AgentGateway::class);
        $this->app->forgetInstance(CardDiscoveryService::class);
        [$user, $card] = $this->approvedCard();

        $discovery = app(CardDiscoveryService::class)->request($card, $user);

        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $discovery->status);
        $this->assertSame('agent_http', $discovery->external_agent_id);
        $this->assertSame(
            "# Discovery de teste\n\nConteudo gerado.",
            Storage::disk('discoveries')->get($discovery->document_path),
        );
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request['autoCreatePR'] === false
                && $request['repos'][0]['url'] === 'https://github.com/acme/mobile-app'
                && str_contains((string) $request['prompt']['text'], 'Somente leitura')
                && ! str_contains((string) json_encode($request->data()), 'cursor-test-key');
        });
    }

    public function test_job_releases_itself_only_when_the_queue_is_not_sync(): void
    {
        Queue::fake();
        config([
            'queue.default' => 'database',
            'services.cursor.poll_seconds' => 10,
        ]);
        $gateway = $this->fakeAgents();
        $gateway->snapshots = [DiscoveryAgentSnapshot::running()];
        [$user, $card] = $this->approvedCard();
        $discovery = app(CardDiscoveryService::class)->request($card, $user);

        $job = (new AdvanceCardDiscovery($discovery->id))->withFakeQueueInteractions();
        $job->handle(app(CardDiscoveryService::class));

        $this->assertTrue($job->job->isReleased());
        $this->assertSame(10, $job->job->releaseDelay);
        $this->assertSame(CardDiscovery::STATUS_RUNNING, $discovery->fresh()->status);

        config(['queue.default' => 'sync']);
        $gateway->snapshots = [DiscoveryAgentSnapshot::running()];
        $syncJob = (new AdvanceCardDiscovery($discovery->id))->withFakeQueueInteractions();
        $syncJob->handle(app(CardDiscoveryService::class));

        $this->assertFalse($syncJob->job->isReleased());
    }

    public function test_request_with_one_answer_persists_the_pairs_and_dispatches_one_job(): void
    {
        Queue::fake();
        Event::fake([MessageLogged::class]);
        Storage::fake('discoveries');
        $this->fakeAgents();
        [$user, $card] = $this->approvedCard();
        $this->storePreviousDiscovery($card, "# Antigo\n\n## 6. Dúvidas\n\n- Qual o prazo? — importa");

        $discovery = app(CardDiscoveryService::class)->requestWithAnswers($card, $user, [
            ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
            ['question' => 'Cobre a API?', 'answer' => '   '],
        ]);

        $this->assertSame(CardDiscovery::STATUS_REQUESTED, $discovery->status);
        $this->assertSame([
            ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
        ], $discovery->pending_answers);
        Queue::assertPushed(AdvanceCardDiscovery::class, 1);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            $encoded = json_encode($log->context);

            return $log->message === 'card_discovery.requested'
                && ($log->context['answers_count'] ?? null) === 1
                && is_string($encoded)
                && ! str_contains($encoded, 'Duas semanas');
        });
    }

    public function test_empty_answers_do_not_dispatch_a_regeneration(): void
    {
        Queue::fake();
        Storage::fake('discoveries');
        [$user, $card] = $this->approvedCard();
        $existing = $this->storePreviousDiscovery($card, "# Antigo\n\nTexto.");
        $service = app(CardDiscoveryService::class);

        foreach ([[], [['question' => 'Qual o prazo?', 'answer' => '  ']]] as $answers) {
            try {
                $service->requestWithAnswers($card->fresh(), $user, $answers);
                $this->fail('Resposta vazia não pode disparar.');
            } catch (CardDiscoveryNotAllowed $exception) {
                $this->assertSame('Informe ao menos uma resposta para regenerar o Discovery.', $exception->getMessage());
            }
        }

        Queue::assertNothingPushed();
        $fresh = $existing->fresh();
        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $fresh->status);
        $this->assertNull($fresh->pending_answers);

        $withoutDocument = $this->createCard($user, Project::factory()->create([
            'repository' => 'acme/other-app',
            'branch' => 'develop',
        ]));
        try {
            $service->requestWithAnswers($withoutDocument, $user, [
                ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
            ]);
            $this->fail('Sem Discovery anterior não pode disparar.');
        } catch (CardDiscoveryNotAllowed $exception) {
            $this->assertSame('A regeneração com respostas exige o Discovery anterior.', $exception->getMessage());
        }

        Queue::assertNothingPushed();
        $this->assertNull($withoutDocument->fresh()->discovery);
    }

    public function test_another_user_cannot_regenerate_with_answers(): void
    {
        Queue::fake();
        Event::fake([MessageLogged::class]);
        Storage::fake('discoveries');
        [$owner, $card] = $this->approvedCard();
        $this->storePreviousDiscovery($card, "# Antigo\n\nTexto.");
        $other = User::factory()->create();

        try {
            app(CardDiscoveryService::class)->requestWithAnswers($card, $other, [
                ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
            ]);
            $this->fail('Outro usuário deveria receber 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Queue::assertNothingPushed();
        $this->assertNull($card->fresh()->discovery->pending_answers);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($card, $other): bool {
            return $log->level === 'warning'
                && $log->message === 'card_discovery.forbidden'
                && $log->context['card_id'] === $card->id
                && $log->context['user_id'] === $other->id;
        });
    }

    public function test_admin_cannot_request_or_regenerate_another_users_discovery(): void
    {
        Queue::fake();
        Event::fake([MessageLogged::class]);
        Storage::fake('discoveries');
        [, $card] = $this->approvedCard();
        $discovery = $this->storePreviousDiscovery($card, "# Antigo\n\nTexto.");
        $admin = User::factory()->admin()->create();
        $service = app(CardDiscoveryService::class);

        try {
            $service->request($card, $admin);
            $this->fail('Admin em card alheio deveria receber 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $service->requestWithAnswers($card, $admin, [
                ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
            ]);
            $this->fail('Admin em card alheio deveria receber 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Queue::assertNothingPushed();
        $fresh = $discovery->fresh();
        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $fresh->status);
        $this->assertNull($fresh->pending_answers);
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($card, $admin): bool {
            return $log->level === 'warning'
                && $log->message === 'card_discovery.forbidden'
                && $log->context['card_id'] === $card->id
                && $log->context['user_id'] === $admin->id;
        });
    }

    public function test_regeneration_with_answers_is_rejected_while_one_is_in_progress(): void
    {
        Queue::fake();
        Storage::fake('discoveries');
        [$user, $card] = $this->approvedCard();
        $this->storePreviousDiscovery($card, "# Antigo\n\nTexto.");
        $service = app(CardDiscoveryService::class);
        $service->requestWithAnswers($card, $user, [
            ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
        ]);

        try {
            $service->requestWithAnswers($card->fresh(), $user, [
                ['question' => 'Qual o prazo?', 'answer' => 'Três semanas'],
            ]);
            $this->fail('Não pode disparar de novo enquanto está em andamento.');
        } catch (CardDiscoveryNotAllowed $exception) {
            $this->assertSame('Já existe um Discovery em andamento para este card.', $exception->getMessage());
        }

        Queue::assertPushed(AdvanceCardDiscovery::class, 1);
        $this->assertSame('Duas semanas', $card->fresh()->discovery->pending_answers[0]['answer']);
    }

    public function test_successful_regeneration_replaces_the_document_and_clears_the_answers(): void
    {
        Storage::fake('discoveries');
        $gateway = $this->fakeAgents();
        $gateway->snapshots = [DiscoveryAgentSnapshot::finished("# Novo\n\nPremissa: duas semanas.")];
        [$user, $card] = $this->approvedCard();
        $previous = "# Antigo\n\n## 6. Dúvidas\n\n- Qual o prazo? — importa";
        $this->storePreviousDiscovery($card, $previous);
        $path = 'cards/'.$card->id.'/discovery.md';

        $discovery = app(CardDiscoveryService::class)->requestWithAnswers($card, $user, [
            ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
        ]);

        $prompt = (string) $gateway->lastRequest?->prompt;
        $answerAt = mb_strpos($prompt, 'Duas semanas');
        $this->assertNotFalse($answerAt);
        $this->assertLessThan($answerAt, mb_strpos($prompt, 'OVERRIDE OBRIGATÓRIO'));
        $this->assertStringContainsString('Avisar card parecido', $prompt);
        $this->assertStringContainsString($previous, $prompt);
        $this->assertStringContainsString('Qual o prazo?', $prompt);
        $this->assertStringContainsString('Somente leitura', $prompt);
        $this->assertStringContainsString('NÃO faça commit', $prompt);
        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $discovery->status);
        $this->assertNull($discovery->pending_answers);
        $this->assertSame($path, $discovery->document_path);
        $this->assertSame("# Novo\n\nPremissa: duas semanas.", Storage::disk('discoveries')->get($path));
    }

    public function test_failed_regeneration_keeps_the_answers_and_the_previous_document(): void
    {
        Storage::fake('discoveries');
        $gateway = $this->fakeAgents();
        [$user, $card] = $this->approvedCard();
        $previous = "# Antigo\n\nVersao boa.";
        $this->storePreviousDiscovery($card, $previous);
        $path = 'cards/'.$card->id.'/discovery.md';
        $service = app(CardDiscoveryService::class);
        $answers = [['question' => 'Qual o prazo?', 'answer' => 'Duas semanas']];

        $gateway->snapshots = [DiscoveryAgentSnapshot::failed('ERROR')];
        $failed = $service->requestWithAnswers($card, $user, $answers);
        $this->assertSame(CardDiscovery::STATUS_FAILED, $failed->status);
        $this->assertSame($answers, $failed->pending_answers);
        $this->assertSame($previous, Storage::disk('discoveries')->get($path));

        $gateway->snapshots = [DiscoveryAgentSnapshot::finished('   ')];
        $empty = $service->requestWithAnswers($card->fresh(), $user, $answers);
        $this->assertSame(CardDiscoveryService::MESSAGE_EMPTY, $empty->error_message);
        $this->assertSame($answers, $empty->pending_answers);
        $this->assertSame($previous, Storage::disk('discoveries')->get($path));

        $running = CardDiscovery::query()->where('card_id', $card->id)->firstOrFail();
        $running->forceFill([
            'status' => CardDiscovery::STATUS_RUNNING,
            'external_agent_id' => 'agent-1',
            'external_run_id' => 'run-1',
            'deadline_at' => now()->subMinute(),
            'error_message' => null,
            'pending_answers' => $answers,
        ])->save();

        $this->assertFalse($service->advance($running->fresh()));
        $timedOut = $running->fresh();
        $this->assertSame(CardDiscovery::STATUS_FAILED, $timedOut->status);
        $this->assertSame(CardDiscoveryService::MESSAGE_TIMEOUT, $timedOut->error_message);
        $this->assertSame($answers, $timedOut->pending_answers);
        $this->assertSame($previous, Storage::disk('discoveries')->get($path));
    }

    public function test_request_without_answers_does_not_send_pending_answers(): void
    {
        Storage::fake('discoveries');
        $gateway = $this->fakeAgents();
        $gateway->snapshots = [DiscoveryAgentSnapshot::finished("# Novo\n\nSem respostas.")];
        [$user, $card] = $this->approvedCard();
        $discovery = $this->storePreviousDiscovery($card, "# Antigo\n\nTexto.");
        $discovery->forceFill([
            'pending_answers' => [
                ['question' => 'Qual o prazo?', 'answer' => 'Duas semanas'],
            ],
        ])->save();

        $fresh = app(CardDiscoveryService::class)->request($card, $user);

        $prompt = (string) $gateway->lastRequest?->prompt;
        $this->assertStringNotContainsString('Duas semanas', $prompt);
        $this->assertStringNotContainsString('Contexto da regeneração', $prompt);
        $this->assertStringContainsString('Somente leitura', $prompt);
        $this->assertNull($fresh->pending_answers);
    }

    public function test_worker_give_up_marks_timeout_and_keeps_the_file(): void
    {
        Storage::fake('discoveries');
        [$user, $card] = $this->approvedCard();
        $path = 'cards/'.$card->id.'/discovery.md';
        Storage::disk('discoveries')->put($path, '# Antigo');
        $discovery = CardDiscovery::query()->create([
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_RUNNING,
            'document_disk' => 'discoveries',
            'document_path' => $path,
            'deadline_at' => now()->subMinute(),
        ]);

        (new AdvanceCardDiscovery($discovery->id))->failed(new \RuntimeException('expired'));

        $fresh = $discovery->fresh();
        $this->assertSame(CardDiscovery::STATUS_FAILED, $fresh->status);
        $this->assertSame(CardDiscoveryService::MESSAGE_TIMEOUT, $fresh->error_message);
        $this->assertSame('# Antigo', Storage::disk('discoveries')->get($path));

        $fresh->forceFill([
            'status' => CardDiscovery::STATUS_COMPLETED,
            'error_message' => null,
        ])->save();
        (new AdvanceCardDiscovery($discovery->id))->failed(new \RuntimeException('expired'));
        $this->assertSame(CardDiscovery::STATUS_COMPLETED, $discovery->fresh()->status);
    }

    private function storePreviousDiscovery(Card $card, string $markdown): CardDiscovery
    {
        $path = 'cards/'.$card->id.'/discovery.md';
        Storage::disk('discoveries')->put($path, $markdown);

        return CardDiscovery::query()->create([
            'card_id' => $card->id,
            'provider' => 'cursor',
            'status' => CardDiscovery::STATUS_COMPLETED,
            'document_disk' => 'discoveries',
            'document_path' => $path,
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array{0: User, 1: Card}
     */
    private function approvedCard(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'repository' => 'acme/mobile-app',
            'branch' => 'develop',
        ]);

        return [$user, $this->createCard($user, $project)];
    }

    private function fakeAgents(): FakeAgentGateway
    {
        $gateway = new FakeAgentGateway;
        $this->app->instance(AgentGateway::class, $gateway);

        return $gateway;
    }
}
