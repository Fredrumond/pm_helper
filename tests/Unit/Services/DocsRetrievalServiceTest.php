<?php

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Models\Project;
use App\Prompts\SystemPromptCatalog;
use App\Services\DocsRetrievalService;
use App\Services\ProjectDocsPathsResult;
use App\Services\ProjectDocsResult;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Support\FakeLlmGateway;
use Tests\Support\FakeProjectDocsGateway;
use Tests\TestCase;

class DocsRetrievalServiceTest extends TestCase
{
    private const BRIEFING = 'A documentação confirma pagamento obrigatório e não há conflito com a entrevista.';

    public function test_happy_path_returns_filtered_content_and_logs_retrieval(): void
    {
        Event::fake([MessageLogged::class]);

        $filtered = "## docs/regras/pagamento.md\n\nPagamento obrigatório.";
        $summary = 'Problema: checkout sem pagamento';
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok([
                'docs/regras/pagamento.md',
                'docs/adr/0001.md',
            ]))
            ->queueReadByPaths(ProjectDocsResult::ok($filtered, 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": ["docs/regras/pagamento.md"]}')
            ->queueComplete(self::BRIEFING);

        $conversation = $this->conversation();
        $result = $this->service($docs, $llm)->retrieve(
            $this->project(),
            $summary,
            'test/model',
            $conversation,
        );

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame($filtered, $result->content);
        $this->assertSame(self::BRIEFING, $result->briefing);
        $this->assertSame([], $docs->calls);
        $this->assertSame([
            ['repository' => 'acme/checkout', 'branch' => 'develop'],
        ], $docs->listPathsCalls);
        $this->assertSame([
            [
                'repository' => 'acme/checkout',
                'paths' => ['docs/regras/pagamento.md'],
                'branch' => 'develop',
            ],
        ], $docs->readByPathsCalls);
        $this->assertCount(2, $llm->completeCalls);
        $this->assertSame('docs_retrieval', $llm->completeCalls[0]['step']);
        $this->assertSame($conversation, $llm->completeCalls[0]['conversation']);
        $this->assertSame('docs_briefing', $llm->completeCalls[1]['step']);
        $this->assertSame($conversation, $llm->completeCalls[1]['conversation']);
        $this->assertSame('test/model', $llm->completeCalls[1]['model']);
        $this->assertStringContainsString('docs/regras/pagamento.md', $llm->completeCalls[0]['messages'][1]['content']);
        $this->assertStringContainsString($summary, $llm->completeCalls[0]['messages'][1]['content']);
        $this->assertStringContainsString('filtrador de relevância', $llm->completeCalls[0]['messages'][0]['content']);
        $this->assertStringContainsString($filtered, $llm->completeCalls[1]['messages'][1]['content']);
        $this->assertStringContainsString($summary, $llm->completeCalls[1]['messages'][1]['content']);
        $this->assertStringContainsString('cruzamento', $llm->completeCalls[1]['messages'][0]['content']);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($filtered): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.retrieval'
                && ($log->context['paths_selected'] ?? null) === ['docs/regras/pagamento.md']
                && ($log->context['paths_discarded'] ?? null) === ['docs/adr/0001.md']
                && ($log->context['chars_after_filter'] ?? null) === mb_strlen($filtered, 'UTF-8');
        });

        $this->assertBriefingLog(
            'ok',
            mb_strlen("Documentação de /docs:\n\n{$filtered}\n\nResumo da entrevista:\n\n{$summary}", 'UTF-8'),
            mb_strlen(self::BRIEFING, 'UTF-8'),
        );
    }

    public function test_llm_failure_falls_back_to_full_dump_and_briefs(): void
    {
        Event::fake([MessageLogged::class]);

        $dump = '# Dump completo';
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queue(ProjectDocsResult::ok($dump, 3, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete(new RuntimeException('LLM fora do ar'))
            ->queueComplete(self::BRIEFING);

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame($dump, $result->content);
        $this->assertSame(self::BRIEFING, $result->briefing);
        $this->assertCount(1, $docs->calls);
        $this->assertSame([], $docs->readByPathsCalls);
        $this->assertCount(2, $llm->completeCalls);
        $this->assertSame('docs_briefing', $llm->completeCalls[1]['step']);
        $this->assertStringContainsString($dump, $llm->completeCalls[1]['messages'][1]['content']);
        $this->assertRetrievalLog([], ['docs/regras/pagamento.md'], mb_strlen($dump, 'UTF-8'));
    }

    public function test_invalid_json_falls_back_to_full_dump(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queue(ProjectDocsResult::ok('# Dump', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('não é json')
            ->queueComplete(self::BRIEFING);

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
        $this->assertSame(self::BRIEFING, $result->briefing);
        $this->assertCount(1, $docs->calls);
        $this->assertSame([], $docs->readByPathsCalls);
    }

    public function test_empty_path_list_falls_back_to_full_dump(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok([
                'docs/regras/pagamento.md',
                'docs/adr/0001.md',
            ]))
            ->queue(ProjectDocsResult::ok('# Dump', 2, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": []}')
            ->queueComplete(self::BRIEFING);

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
        $this->assertSame(self::BRIEFING, $result->briefing);
        $this->assertCount(1, $docs->calls);
        $this->assertSame([], $docs->readByPathsCalls);
    }

    public function test_paths_outside_docs_are_ignored(): void
    {
        Event::fake([MessageLogged::class]);

        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok([
                'docs/regras/pagamento.md',
                'docs/adr/0001.md',
            ]))
            ->queueReadByPaths(ProjectDocsResult::ok('## docs/regras/pagamento.md\n\nOk.', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete(json_encode([
                'paths' => [
                    'src/secret.php',
                    '../.env',
                    'docs/regras/pagamento.md',
                ],
            ]))
            ->queueComplete(self::BRIEFING);

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame(self::BRIEFING, $result->briefing);
        $this->assertSame([], $docs->calls);
        $this->assertSame([
            [
                'repository' => 'acme/checkout',
                'paths' => ['docs/regras/pagamento.md'],
                'branch' => 'develop',
            ],
        ], $docs->readByPathsCalls);
        $this->assertRetrievalLog(
            ['docs/regras/pagamento.md'],
            ['src/secret.php', '../.env', 'docs/adr/0001.md'],
            $result->chars,
        );
    }

    public function test_only_paths_outside_docs_fall_back_to_full_dump(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queue(ProjectDocsResult::ok('# Dump', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": ["src/secret.php"]}')
            ->queueComplete(self::BRIEFING);

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
        $this->assertSame(self::BRIEFING, $result->briefing);
        $this->assertCount(1, $docs->calls);
        $this->assertSame([], $docs->readByPathsCalls);
    }

    public function test_list_docs_paths_failure_falls_back_without_calling_retrieval_llm(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::failed(ProjectDocsResult::ERROR_TIMEOUT))
            ->queue(ProjectDocsResult::ok('# Dump', 1, 0));

        $llm = (new FakeLlmGateway)->queueComplete(self::BRIEFING);

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
        $this->assertSame(self::BRIEFING, $result->briefing);
        $this->assertCount(1, $llm->completeCalls);
        $this->assertSame('docs_briefing', $llm->completeCalls[0]['step']);
        $this->assertCount(1, $docs->calls);
    }

    public function test_uses_configured_retrieval_model_when_set(): void
    {
        config(['chat.prompts.docs_retrieval.model' => 'cheap/fast']);

        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::ok('# Filtrado', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": ["docs/regras/pagamento.md"]}')
            ->queueComplete(self::BRIEFING);

        $this->retrieve($docs, $llm, 'resumo', 'card/model');

        $this->assertSame('cheap/fast', $llm->completeCalls[0]['model']);
        $this->assertSame('docs_retrieval', $llm->completeCalls[0]['step']);
        $this->assertSame('card/model', $llm->completeCalls[1]['model']);
        $this->assertSame('docs_briefing', $llm->completeCalls[1]['step']);
    }

    public function test_uses_configured_briefing_model_when_set(): void
    {
        config(['chat.prompts.docs_briefing.model' => 'brief/cheap']);

        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::ok('# Filtrado', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": ["docs/regras/pagamento.md"]}')
            ->queueComplete(self::BRIEFING);

        $this->retrieve($docs, $llm, 'resumo', 'card/model');

        $this->assertSame('card/model', $llm->completeCalls[0]['model']);
        $this->assertSame('brief/cheap', $llm->completeCalls[1]['model']);
        $this->assertSame('docs_briefing', $llm->completeCalls[1]['step']);
    }

    public function test_briefing_llm_failure_keeps_ok_result_without_rereading_github(): void
    {
        Event::fake([MessageLogged::class]);

        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::ok('# Filtrado', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": ["docs/regras/pagamento.md"]}')
            ->queueComplete(new RuntimeException('timeout'));

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame('# Filtrado', $result->content);
        $this->assertNull($result->briefing);
        $this->assertSame([], $docs->calls);
        $this->assertCount(1, $docs->readByPathsCalls);
        $this->assertCount(2, $llm->completeCalls);
        $this->assertBriefingLog('failed', $this->briefingCharsInput('# Filtrado', 'resumo'), 0);
    }

    public function test_empty_briefing_keeps_ok_result_without_rereading_github(): void
    {
        Event::fake([MessageLogged::class]);

        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::ok('# Filtrado', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": ["docs/regras/pagamento.md"]}')
            ->queueComplete('   ');

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertNull($result->briefing);
        $this->assertSame([], $docs->calls);
        $this->assertCount(1, $docs->readByPathsCalls);
        $this->assertBriefingLog('empty', $this->briefingCharsInput('# Filtrado', 'resumo'), 0);
    }

    public function test_empty_status_does_not_call_briefing(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::empty());

        $llm = (new FakeLlmGateway)->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_EMPTY, $result->status);
        $this->assertNull($result->briefing);
        $this->assertCount(1, $llm->completeCalls);
        $this->assertSame('docs_retrieval', $llm->completeCalls[0]['step']);
    }

    public function test_too_large_status_does_not_call_briefing(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::tooLarge(2, 0, 90_000));

        $llm = (new FakeLlmGateway)->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_TOO_LARGE, $result->status);
        $this->assertNull($result->briefing);
        $this->assertCount(1, $llm->completeCalls);
    }

    public function test_failed_status_does_not_call_briefing(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::failed(ProjectDocsResult::ERROR_TIMEOUT));

        $llm = (new FakeLlmGateway)->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_FAILED, $result->status);
        $this->assertNull($result->briefing);
        $this->assertCount(1, $llm->completeCalls);
    }

    private function retrieve(
        FakeProjectDocsGateway $docs,
        FakeLlmGateway $llm,
        string $summary = 'resumo',
        string $model = 'test/model',
        ?Conversation $conversation = null,
    ): ProjectDocsResult {
        return $this->service($docs, $llm)->retrieve(
            $this->project(),
            $summary,
            $model,
            $conversation ?? $this->conversation(),
        );
    }

    private function service(FakeProjectDocsGateway $docs, FakeLlmGateway $llm): DocsRetrievalService
    {
        return new DocsRetrievalService($docs, $llm, new SystemPromptCatalog);
    }

    private function conversation(): Conversation
    {
        return new Conversation;
    }

    private function project(): Project
    {
        return new Project([
            'repository' => 'acme/checkout',
            'branch' => 'develop',
        ]);
    }

    /**
     * @param  list<string>  $selected
     * @param  list<string>  $discarded
     */
    private function assertRetrievalLog(array $selected, array $discarded, int $chars): void
    {
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($selected, $discarded, $chars): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.retrieval'
                && ($log->context['paths_selected'] ?? null) === $selected
                && ($log->context['paths_discarded'] ?? null) === $discarded
                && ($log->context['chars_after_filter'] ?? null) === $chars;
        });
    }

    private function assertBriefingLog(string $status, int $charsInput, int $charsOutput): void
    {
        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($status, $charsInput, $charsOutput): bool {
            if ($log->level !== 'info' || $log->message !== 'project_docs.briefing') {
                return false;
            }

            $this->assertSame(['status', 'chars_input', 'chars_output'], array_keys($log->context));
            $this->assertSame($status, $log->context['status']);
            $this->assertSame($charsInput, $log->context['chars_input']);
            $this->assertSame($charsOutput, $log->context['chars_output']);
            $this->assertStringNotContainsString(self::BRIEFING, json_encode($log->context));

            return true;
        });
    }

    private function briefingCharsInput(string $docsContent, string $summary): int
    {
        return mb_strlen("Documentação de /docs:\n\n{$docsContent}\n\nResumo da entrevista:\n\n{$summary}", 'UTF-8');
    }
}
