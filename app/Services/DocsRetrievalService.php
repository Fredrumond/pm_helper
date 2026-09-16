<?php

namespace App\Services;

use App\Contracts\LlmGateway;
use App\Contracts\ProjectDocsGateway;
use App\Models\Conversation;
use App\Models\Project;
use App\Prompts\SystemPromptCatalog;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocsRetrievalService
{
    private const MAX_PATHS = 10;

    public function __construct(
        private readonly ProjectDocsGateway $docs,
        private readonly LlmGateway $llm,
        private readonly SystemPromptCatalog $prompts,
    ) {}

    public function retrieve(
        Project $project,
        string $interviewSummary,
        string $model,
        Conversation $conversation,
    ): ProjectDocsResult {
        try {
            return $this->retrieveOrFallback($project, $interviewSummary, $model, $conversation);
        } catch (Throwable) {
            return $this->fallback($project, [], []);
        }
    }

    private function retrieveOrFallback(
        Project $project,
        string $interviewSummary,
        string $model,
        Conversation $conversation,
    ): ProjectDocsResult {
        $index = $this->listIndex($project);

        if ($index === null || $index === []) {
            return $this->fallback($project, [], $index ?? []);
        }

        try {
            $raw = $this->llm->completePrompt(
                $this->retrievalMessages($index, $interviewSummary),
                $this->retrievalModel($model),
                'docs_retrieval',
                $conversation,
            );
        } catch (Throwable) {
            return $this->fallback($project, [], $index);
        }

        $parsed = $this->parsePaths($raw);

        if ($parsed === null || $parsed === []) {
            return $this->fallback($project, [], $index);
        }

        [$selected, $discarded] = $this->filterPaths($parsed, $index);

        if ($selected === []) {
            return $this->fallback($project, [], $discarded);
        }

        try {
            $result = $this->docs->readDocsByPaths(
                (string) $project->repository,
                $selected,
                $project->branch,
            );
        } catch (Throwable) {
            return $this->fallback($project, $selected, $discarded);
        }

        return $this->logged($result, $selected, $discarded);
    }

    /**
     * @return list<string>|null
     */
    private function listIndex(Project $project): ?array
    {
        try {
            $listed = $this->docs->listDocsPaths(
                (string) $project->repository,
                $project->branch,
            );
        } catch (Throwable) {
            return null;
        }

        if ($listed->status !== ProjectDocsPathsResult::STATUS_OK) {
            return null;
        }

        return $listed->paths;
    }

    /**
     * @param  list<string>  $index
     * @return list<array{role: string, content: string}>
     */
    private function retrievalMessages(array $index, string $interviewSummary): array
    {
        $prompt = $this->prompts->current('docs_retrieval');
        $paths = implode("\n", $index);

        return [
            ['role' => 'system', 'content' => $prompt->content],
            ['role' => 'user', 'content' => "Índice de /docs:\n\n{$paths}\n\nResumo da entrevista:\n\n{$interviewSummary}"],
        ];
    }

    /**
     * @return list<string>|null
     */
    private function parsePaths(string $raw): ?array
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $trimmed, $matches) === 1) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded) || ! array_key_exists('paths', $decoded) || ! is_array($decoded['paths'])) {
            return null;
        }

        $paths = [];

        foreach ($decoded['paths'] as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);

            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $candidates
     * @param  list<string>  $index
     * @return array{0: list<string>, 1: list<string>}
     */
    private function filterPaths(array $candidates, array $index): array
    {
        $allowed = [];

        foreach ($index as $path) {
            $normalized = $this->normalizeDocsPath($path);

            if ($normalized !== null) {
                $allowed[$normalized] = true;
            }
        }

        $selected = [];
        $discarded = [];
        $seen = [];

        foreach ($candidates as $path) {
            $normalized = $this->normalizeDocsPath($path);

            if ($normalized === null || ! isset($allowed[$normalized]) || isset($seen[$normalized])) {
                $discarded[] = $path;

                continue;
            }

            if (count($selected) >= self::MAX_PATHS) {
                $discarded[] = $path;

                continue;
            }

            $seen[$normalized] = true;
            $selected[] = $normalized;
        }

        foreach ($index as $path) {
            $normalized = $this->normalizeDocsPath($path) ?? $path;

            if (! isset($seen[$normalized])) {
                $discarded[] = $path;
            }
        }

        return [array_values($selected), array_values(array_unique($discarded))];
    }

    private function normalizeDocsPath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $root = $this->docsPath();

        if ($path === $root || ! str_starts_with($path, $root.'/')) {
            return null;
        }

        return $path;
    }

    private function docsPath(): string
    {
        $path = trim((string) config('mcp.github.docs_path', 'docs'), '/');

        return $path !== '' ? $path : 'docs';
    }

    private function retrievalModel(string $fallback): string
    {
        $configured = config('chat.prompts.docs_retrieval.model');

        return is_string($configured) && $configured !== '' ? $configured : $fallback;
    }

    /**
     * @param  list<string>  $selected
     * @param  list<string>  $discarded
     */
    private function fallback(Project $project, array $selected, array $discarded): ProjectDocsResult
    {
        try {
            $result = $this->docs->readProjectDocs(
                (string) $project->repository,
                $project->branch,
            );
        } catch (Throwable) {
            $result = ProjectDocsResult::failed(ProjectDocsResult::ERROR_MCP);
        }

        return $this->logged($result, $selected, $discarded);
    }

    /**
     * @param  list<string>  $selected
     * @param  list<string>  $discarded
     */
    private function logged(ProjectDocsResult $result, array $selected, array $discarded): ProjectDocsResult
    {
        Log::info('project_docs.retrieval', [
            'paths_selected' => $selected,
            'paths_discarded' => $discarded,
            'chars_after_filter' => $result->chars,
        ]);

        return $result;
    }
}
