<?php

namespace App\Services\Adapters;

use App\Contracts\ProjectDocsGateway;
use App\Exceptions\GitHubIntegrationException;
use App\Exceptions\ProjectDocsTooLargeException;
use App\Rules\GitHubBranch;
use App\Rules\GitHubRepository;
use App\Services\GitHubAppInstallationTokenMinter;
use App\Services\ProjectDocsPathsResult;
use App\Services\ProjectDocsResult;
use Illuminate\Support\Facades\Log;

class GitHubMcpProjectDocsGateway implements ProjectDocsGateway
{
    private int $filesRead = 0;

    private int $skippedNonText = 0;

    /** @var list<string> */
    private array $chunks = [];

    private int $chars = 0;

    /** @var array<string, true> */
    private array $visited = [];

    private ?string $ref = null;

    public function __construct(
        private readonly GitHubAppInstallationTokenMinter $tokens,
        private readonly GitHubMcpClient $mcp,
    ) {}

    public function readProjectDocs(string $repository, ?string $branch = null): ProjectDocsResult
    {
        $this->reset();
        $this->ref = $this->normalizeRef($branch);

        if (! $this->isValidRepository($repository)) {
            return $this->finish($repository, ProjectDocsResult::failed(ProjectDocsResult::ERROR_INVALID_REPO));
        }

        if ($this->ref !== null && ! GitHubBranch::isValid($this->ref)) {
            return $this->finish($repository, ProjectDocsResult::failed(ProjectDocsResult::ERROR_INVALID_REF));
        }

        if (! $this->tokens->hasCredentials()) {
            return $this->finish($repository, ProjectDocsResult::failed(ProjectDocsResult::ERROR_MISSING_CREDENTIALS));
        }

        $token = null;

        try {
            $token = $this->tokens->mint();
            $this->mcp->initialize($token);

            [$owner, $repo] = explode('/', $repository, 2);
            $this->collect($owner, $repo, $this->docsPath());
        } catch (ProjectDocsTooLargeException $exception) {
            return $this->finish(
                $repository,
                ProjectDocsResult::tooLarge($this->filesRead, $this->skippedNonText, $exception->chars),
            );
        } catch (GitHubIntegrationException $exception) {
            return $this->finish(
                $repository,
                ProjectDocsResult::failed(
                    $exception->errorCode,
                    $this->filesRead,
                    $this->skippedNonText,
                    $this->chars,
                ),
            );
        } finally {
            $this->mcp->close();
            unset($token);
        }

        $content = trim(implode("\n", $this->chunks));

        if ($content === '') {
            return $this->finish($repository, ProjectDocsResult::empty($this->skippedNonText));
        }

        return $this->finish(
            $repository,
            ProjectDocsResult::ok($content, $this->filesRead, $this->skippedNonText),
        );
    }

    public function listDocsPaths(string $repository, ?string $branch = null): ProjectDocsPathsResult
    {
        $this->reset();
        $this->ref = $this->normalizeRef($branch);

        if (! $this->isValidRepository($repository)) {
            return $this->finishListPaths($repository, ProjectDocsPathsResult::failed(ProjectDocsResult::ERROR_INVALID_REPO));
        }

        if ($this->ref !== null && ! GitHubBranch::isValid($this->ref)) {
            return $this->finishListPaths($repository, ProjectDocsPathsResult::failed(ProjectDocsResult::ERROR_INVALID_REF));
        }

        if (! $this->tokens->hasCredentials()) {
            return $this->finishListPaths($repository, ProjectDocsPathsResult::failed(ProjectDocsResult::ERROR_MISSING_CREDENTIALS));
        }

        $token = null;
        $paths = [];

        try {
            $token = $this->tokens->mint();
            $this->mcp->initialize($token);

            [$owner, $repo] = explode('/', $repository, 2);
            $this->collectPaths($owner, $repo, $this->docsPath(), $paths);
        } catch (GitHubIntegrationException $exception) {
            return $this->finishListPaths(
                $repository,
                ProjectDocsPathsResult::failed($exception->errorCode),
            );
        } finally {
            $this->mcp->close();
            unset($token);
        }

        return $this->finishListPaths($repository, ProjectDocsPathsResult::ok($paths));
    }

    /**
     * @param  list<string>  $paths
     */
    public function readDocsByPaths(string $repository, array $paths, ?string $branch = null): ProjectDocsResult
    {
        $this->reset();
        $this->ref = $this->normalizeRef($branch);

        if (! $this->isValidRepository($repository)) {
            return $this->finish($repository, ProjectDocsResult::failed(ProjectDocsResult::ERROR_INVALID_REPO));
        }

        if ($this->ref !== null && ! GitHubBranch::isValid($this->ref)) {
            return $this->finish($repository, ProjectDocsResult::failed(ProjectDocsResult::ERROR_INVALID_REF));
        }

        if (! $this->tokens->hasCredentials()) {
            return $this->finish($repository, ProjectDocsResult::failed(ProjectDocsResult::ERROR_MISSING_CREDENTIALS));
        }

        $token = null;

        try {
            $token = $this->tokens->mint();
            $this->mcp->initialize($token);

            [$owner, $repo] = explode('/', $repository, 2);

            foreach ($paths as $path) {
                if (! is_string($path)) {
                    continue;
                }

                $this->collectListedFile($owner, $repo, $path);
            }
        } catch (ProjectDocsTooLargeException $exception) {
            return $this->finish(
                $repository,
                ProjectDocsResult::tooLarge($this->filesRead, $this->skippedNonText, $exception->chars),
            );
        } catch (GitHubIntegrationException $exception) {
            return $this->finish(
                $repository,
                ProjectDocsResult::failed(
                    $exception->errorCode,
                    $this->filesRead,
                    $this->skippedNonText,
                    $this->chars,
                ),
            );
        } finally {
            $this->mcp->close();
            unset($token);
        }

        $content = trim(implode("\n", $this->chunks));

        if ($content === '') {
            return $this->finish($repository, ProjectDocsResult::empty($this->skippedNonText));
        }

        return $this->finish(
            $repository,
            ProjectDocsResult::ok($content, $this->filesRead, $this->skippedNonText),
        );
    }

    private function collect(string $owner, string $repo, string $path): void
    {
        $path = $this->normalizePath($path);

        if ($path === null || isset($this->visited[$path])) {
            return;
        }

        $this->visited[$path] = true;

        $result = $this->mcp->getFileContents($owner, $repo, $path, $this->ref);

        match ($result->kind) {
            GitHubMcpFileResult::KIND_MISSING => null,
            GitHubMcpFileResult::KIND_BINARY => $this->skippedNonText++,
            GitHubMcpFileResult::KIND_TOO_LARGE => throw new ProjectDocsTooLargeException($this->maxChars()),
            GitHubMcpFileResult::KIND_DIRECTORY => $this->collectDirectory($owner, $repo, $result->entries),
            GitHubMcpFileResult::KIND_FILE => $this->collectFile($path, $result->text),
            default => throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP returned an unknown file result.',
            ),
        };
    }

    /**
     * @param  list<array{type: string, name: string, path: string}>  $entries
     */
    private function collectDirectory(string $owner, string $repo, array $entries): void
    {
        usort($entries, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        foreach ($entries as $entry) {
            $child = $this->normalizePath($entry['path'] !== '' ? $entry['path'] : $entry['name']);

            if ($child === null) {
                continue;
            }

            if (in_array($entry['type'], ['dir', 'directory'], true)) {
                $this->collect($owner, $repo, $child);

                continue;
            }

            $this->collect($owner, $repo, $child);
        }
    }

    private function collectFile(string $path, string $text): void
    {
        if ($text === '') {
            return;
        }

        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }

        if ($text === '' || str_contains($text, "\0") || ! mb_check_encoding($text, 'UTF-8')) {
            $this->skippedNonText++;

            return;
        }

        $piece = "## {$path}\n\n".rtrim($text, "\n")."\n";
        $join = $this->chunks === [] ? 0 : 1;
        $nextChars = $this->chars + $join + mb_strlen($piece, 'UTF-8');

        if ($nextChars > $this->maxChars()) {
            throw new ProjectDocsTooLargeException($nextChars);
        }

        $this->chunks[] = $piece;
        $this->chars = $nextChars;
        $this->filesRead++;
    }

    /**
     * Caminha a árvore de `/docs` sem ler o conteúdo dos arquivos.
     *
     * @param  list<string>  $paths
     */
    private function collectPaths(string $owner, string $repo, string $path, array &$paths): void
    {
        $path = $this->normalizePath($path);

        if ($path === null || isset($this->visited[$path])) {
            return;
        }

        $this->visited[$path] = true;

        $result = $this->mcp->getFileContents($owner, $repo, $path, $this->ref);

        match ($result->kind) {
            GitHubMcpFileResult::KIND_MISSING, GitHubMcpFileResult::KIND_BINARY, GitHubMcpFileResult::KIND_TOO_LARGE => null,
            GitHubMcpFileResult::KIND_DIRECTORY => $this->collectPathEntries($owner, $repo, $result->entries, $paths),
            GitHubMcpFileResult::KIND_FILE => $paths[] = $path,
            default => throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP returned an unknown file result.',
            ),
        };
    }

    /**
     * @param  list<array{type: string, name: string, path: string}>  $entries
     * @param  list<string>  $paths
     */
    private function collectPathEntries(string $owner, string $repo, array $entries, array &$paths): void
    {
        usort($entries, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        foreach ($entries as $entry) {
            $child = $this->normalizePath($entry['path'] !== '' ? $entry['path'] : $entry['name']);

            if ($child === null || isset($this->visited[$child])) {
                continue;
            }

            if (in_array($entry['type'], ['dir', 'directory'], true)) {
                $this->collectPaths($owner, $repo, $child, $paths);

                continue;
            }

            $this->visited[$child] = true;
            $paths[] = $child;
        }
    }

    private function collectListedFile(string $owner, string $repo, string $path): void
    {
        $path = $this->normalizePath($path);

        if ($path === null || isset($this->visited[$path])) {
            return;
        }

        $this->visited[$path] = true;

        $result = $this->mcp->getFileContents($owner, $repo, $path, $this->ref);

        match ($result->kind) {
            GitHubMcpFileResult::KIND_MISSING, GitHubMcpFileResult::KIND_DIRECTORY => null,
            GitHubMcpFileResult::KIND_BINARY => $this->skippedNonText++,
            GitHubMcpFileResult::KIND_TOO_LARGE => throw new ProjectDocsTooLargeException($this->maxChars()),
            GitHubMcpFileResult::KIND_FILE => $this->collectFile($path, $result->text),
            default => throw new GitHubIntegrationException(
                ProjectDocsResult::ERROR_MCP,
                'MCP returned an unknown file result.',
            ),
        };
    }

    private function finish(string $repository, ProjectDocsResult $result): ProjectDocsResult
    {
        Log::info('project_docs.read', [
            'repository' => $repository,
            'ref' => $this->ref,
            'status' => $result->status,
            'filesRead' => $result->filesRead,
            'skippedNonText' => $result->skippedNonText,
            'chars' => $result->chars,
            'errorCode' => $result->errorCode,
        ]);

        return $result;
    }

    private function finishListPaths(string $repository, ProjectDocsPathsResult $result): ProjectDocsPathsResult
    {
        Log::info('project_docs.list_paths', [
            'repository' => $repository,
            'ref' => $this->ref,
            'status' => $result->status,
            'count' => count($result->paths),
            'errorCode' => $result->errorCode,
        ]);

        return $result;
    }

    private function isValidRepository(string $repository): bool
    {
        $failed = false;

        (new GitHubRepository)->validate('repository', $repository, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }

    private function normalizeRef(?string $branch): ?string
    {
        $normalized = GitHubBranch::normalize($branch);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $root = $this->docsPath();

        if ($path !== $root && ! str_starts_with($path, $root.'/')) {
            return null;
        }

        return $path;
    }

    private function docsPath(): string
    {
        $path = trim((string) config('mcp.github.docs_path', 'docs'), '/');

        return $path !== '' ? $path : 'docs';
    }

    private function maxChars(): int
    {
        $max = (int) config('mcp.github.max_chars', 80000);

        return $max > 0 ? $max : 80000;
    }

    private function reset(): void
    {
        $this->filesRead = 0;
        $this->skippedNonText = 0;
        $this->chunks = [];
        $this->chars = 0;
        $this->visited = [];
        $this->ref = null;
    }
}
