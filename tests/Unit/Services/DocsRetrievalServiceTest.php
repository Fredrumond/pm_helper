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
            ->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');

        $conversation = $this->conversation();
        $result = $this->service($docs, $llm)->retrieve(
            $this->project(),
            $summary,
            'test/model',
            $conversation,
        );

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame($filtered, $result->content);
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
        $this->assertCount(1, $llm->completeCalls);
        $this->assertSame('docs_retrieval', $llm->completeCalls[0]['step']);
        $this->assertSame($conversation, $llm->completeCalls[0]['conversation']);
        $this->assertStringContainsString('docs/regras/pagamento.md', $llm->completeCalls[0]['messages'][1]['content']);
        $this->assertStringContainsString($summary, $llm->completeCalls[0]['messages'][1]['content']);
        $this->assertStringContainsString('filtrador de relevância', $llm->completeCalls[0]['messages'][0]['content']);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($filtered): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.retrieval'
                && ($log->context['paths_selected'] ?? null) === ['docs/regras/pagamento.md']
                && ($log->context['paths_discarded'] ?? null) === ['docs/adr/0001.md']
                && ($log->context['chars_after_filter'] ?? null) === mb_strlen($filtered, 'UTF-8');
        });
    }

    public function test_llm_failure_falls_back_to_full_dump(): void
    {
        Event::fake([MessageLogged::class]);

        $dump = '# Dump completo';
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queue(ProjectDocsResult::ok($dump, 3, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete(new RuntimeException('LLM fora do ar'));

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame($dump, $result->content);
        $this->assertCount(1, $docs->calls);
        $this->assertSame([], $docs->readByPathsCalls);
        $this->assertCount(1, $llm->completeCalls);
        $this->assertRetrievalLog([], ['docs/regras/pagamento.md'], mb_strlen($dump, 'UTF-8'));
    }

    public function test_invalid_json_falls_back_to_full_dump(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queue(ProjectDocsResult::ok('# Dump', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('não é json');

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
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
            ->queueComplete('{"paths": []}');

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
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
            ]));

        $result = $this->retrieve($docs, $llm);

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
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
            ->queueComplete('{"paths": ["src/secret.php"]}');

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
        $this->assertCount(1, $docs->calls);
        $this->assertSame([], $docs->readByPathsCalls);
    }

    public function test_list_docs_paths_failure_falls_back_without_calling_retrieval_llm(): void
    {
        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::failed(ProjectDocsResult::ERROR_TIMEOUT))
            ->queue(ProjectDocsResult::ok('# Dump', 1, 0));

        $llm = new FakeLlmGateway;

        $result = $this->retrieve($docs, $llm);

        $this->assertSame('# Dump', $result->content);
        $this->assertCount(0, $llm->completeCalls);
        $this->assertCount(1, $docs->calls);
    }

    public function test_uses_configured_retrieval_model_when_set(): void
    {
        config(['chat.prompts.docs_retrieval.model' => 'cheap/fast']);

        $docs = (new FakeProjectDocsGateway)
            ->queuePaths(ProjectDocsPathsResult::ok(['docs/regras/pagamento.md']))
            ->queueReadByPaths(ProjectDocsResult::ok('# Filtrado', 1, 0));

        $llm = (new FakeLlmGateway)
            ->queueComplete('{"paths": ["docs/regras/pagamento.md"]}');

        $this->retrieve($docs, $llm, 'resumo', 'card/model');

        $this->assertSame('cheap/fast', $llm->completeCalls[0]['model']);
        $this->assertSame('docs_retrieval', $llm->completeCalls[0]['step']);
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
}
