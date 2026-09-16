<?php

namespace Tests\Unit\Services;

use App\Services\Adapters\GitHubMcpClient;
use App\Services\Adapters\GitHubMcpFileResult;
use App\Services\Adapters\GitHubMcpProjectDocsGateway;
use App\Services\GitHubAppInstallationTokenMinter;
use App\Services\ProjectDocsPathsResult;
use App\Services\ProjectDocsResult;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GitHubMcpProjectDocsGatewayTest extends TestCase
{
    public function test_list_docs_paths_returns_a_flat_list_without_reading_file_contents(): void
    {
        Event::fake([MessageLogged::class]);

        $mcp = $this->mcpWithTree();
        $result = $this->gateway($mcp)->listDocsPaths('acme/checkout', 'main');

        $this->assertSame(ProjectDocsPathsResult::STATUS_OK, $result->status);
        $this->assertSame([
            'docs/README.md',
            'docs/adr/0001.md',
            'docs/regras/desconto.md',
            'docs/regras/pagamento.md',
        ], $result->paths);
        $this->assertNull($result->errorCode);

        $this->assertSame([
            'docs',
            'docs/adr',
            'docs/regras',
        ], array_column($mcp->calls, 'path'));
        $this->assertSame(1, $mcp->initializeCount);
        $this->assertSame(1, $mcp->closeCount);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->level === 'info'
                && $log->message === 'project_docs.list_paths'
                && ($log->context['status'] ?? null) === ProjectDocsPathsResult::STATUS_OK
                && ($log->context['count'] ?? null) === 4
                && array_key_exists('errorCode', $log->context)
                && $log->context['errorCode'] === null;
        });
    }

    public function test_read_docs_by_paths_reads_only_the_requested_files(): void
    {
        $mcp = $this->mcpWithTree();
        $result = $this->gateway($mcp)->readDocsByPaths(
            'acme/checkout',
            [
                'docs/regras/pagamento.md',
                'docs/README.md',
            ],
            'main',
        );

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame(2, $result->filesRead);
        $this->assertStringContainsString('## docs/regras/pagamento.md', $result->content);
        $this->assertStringContainsString('Pagamento obrigatório.', $result->content);
        $this->assertStringContainsString('## docs/README.md', $result->content);
        $this->assertStringContainsString('Índice de regras', $result->content);
        $this->assertStringNotContainsString('Desconto máximo', $result->content);
        $this->assertStringNotContainsString('ADR 0001', $result->content);

        $this->assertSame([
            'docs/regras/pagamento.md',
            'docs/README.md',
        ], array_column($mcp->calls, 'path'));
    }

    public function test_read_docs_by_paths_applies_the_char_ceiling_to_filtered_content(): void
    {
        config(['mcp.github.max_chars' => 40]);

        $mcp = $this->mcpWithTree();
        $result = $this->gateway($mcp)->readDocsByPaths(
            'acme/checkout',
            [
                'docs/README.md',
                'docs/regras/pagamento.md',
            ],
        );

        $this->assertSame(ProjectDocsResult::STATUS_TOO_LARGE, $result->status);
        $this->assertSame('', $result->content);
        $this->assertGreaterThanOrEqual(1, $result->filesRead);
        $this->assertGreaterThan(40, $result->chars);
    }

    public function test_read_docs_by_paths_ignores_invalid_and_out_of_scope_paths(): void
    {
        $mcp = $this->mcpWithTree();
        $result = $this->gateway($mcp)->readDocsByPaths(
            'acme/checkout',
            [
                'docs/regras/pagamento.md',
                'README.md',
                '../secrets.md',
                'docs/../src/app.php',
                'src/Config.php',
                '/etc/passwd',
                'docs/regras/pagamento.md',
            ],
        );

        $this->assertSame(ProjectDocsResult::STATUS_OK, $result->status);
        $this->assertSame(1, $result->filesRead);
        $this->assertStringContainsString('Pagamento obrigatório.', $result->content);
        $this->assertSame(['docs/regras/pagamento.md'], array_column($mcp->calls, 'path'));
    }

    public function test_list_docs_paths_fails_without_calling_mcp_when_credentials_are_missing(): void
    {
        $mcp = $this->mcpWithTree();
        $result = $this->gateway($mcp, hasCredentials: false)->listDocsPaths('acme/checkout');

        $this->assertSame(ProjectDocsPathsResult::STATUS_FAILED, $result->status);
        $this->assertSame([], $result->paths);
        $this->assertSame(ProjectDocsResult::ERROR_MISSING_CREDENTIALS, $result->errorCode);
        $this->assertSame([], $mcp->calls);
        $this->assertSame(0, $mcp->initializeCount);
    }

    private function gateway(FakeGitHubMcpClient $mcp, bool $hasCredentials = true): GitHubMcpProjectDocsGateway
    {
        return new GitHubMcpProjectDocsGateway(
            new StubGitHubAppInstallationTokenMinter($hasCredentials),
            $mcp,
        );
    }

    private function mcpWithTree(): FakeGitHubMcpClient
    {
        $mcp = new FakeGitHubMcpClient;
        $mcp->results = [
            'docs' => GitHubMcpFileResult::directory([
                ['type' => 'file', 'name' => 'README.md', 'path' => 'docs/README.md'],
                ['type' => 'dir', 'name' => 'adr', 'path' => 'docs/adr'],
                ['type' => 'dir', 'name' => 'regras', 'path' => 'docs/regras'],
            ]),
            'docs/adr' => GitHubMcpFileResult::directory([
                ['type' => 'file', 'name' => '0001.md', 'path' => 'docs/adr/0001.md'],
            ]),
            'docs/regras' => GitHubMcpFileResult::directory([
                ['type' => 'file', 'name' => 'pagamento.md', 'path' => 'docs/regras/pagamento.md'],
                ['type' => 'file', 'name' => 'desconto.md', 'path' => 'docs/regras/desconto.md'],
            ]),
            'docs/README.md' => GitHubMcpFileResult::file('Índice de regras', 'text/plain'),
            'docs/adr/0001.md' => GitHubMcpFileResult::file('ADR 0001', 'text/plain'),
            'docs/regras/pagamento.md' => GitHubMcpFileResult::file('Pagamento obrigatório.', 'text/plain'),
            'docs/regras/desconto.md' => GitHubMcpFileResult::file('Desconto máximo 10%.', 'text/plain'),
        ];

        return $mcp;
    }
}

class FakeGitHubMcpClient extends GitHubMcpClient
{
    /** @var array<string, GitHubMcpFileResult> */
    public array $results = [];

    /** @var list<array{owner: string, repo: string, path: string, ref: ?string}> */
    public array $calls = [];

    public int $initializeCount = 0;

    public int $closeCount = 0;

    public function initialize(string $installationToken): void
    {
        $this->initializeCount++;
    }

    public function getFileContents(string $owner, string $repo, string $path, ?string $ref = null): GitHubMcpFileResult
    {
        $this->calls[] = [
            'owner' => $owner,
            'repo' => $repo,
            'path' => $path,
            'ref' => $ref,
        ];

        return $this->results[$path] ?? GitHubMcpFileResult::missing();
    }

    public function close(): void
    {
        $this->closeCount++;
    }
}

class StubGitHubAppInstallationTokenMinter extends GitHubAppInstallationTokenMinter
{
    public function __construct(private readonly bool $available = true) {}

    public function hasCredentials(): bool
    {
        return $this->available;
    }

    public function mint(): string
    {
        return 'ghs_test_token';
    }
}
