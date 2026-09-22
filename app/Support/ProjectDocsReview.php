<?php

namespace App\Support;

use App\Models\Project;
use App\Services\ProjectDocsResult;

class ProjectDocsReview
{
    public const SESSION_PREFIX = 'project_docs.';

    public static function sessionKey(int|string $conversationId): string
    {
        return self::SESSION_PREFIX.$conversationId;
    }

    /**
     * @return array{
     *     status: string,
     *     content: string|null,
     *     project_id: int|null,
     *     repository: string|null,
     *     branch: string|null,
     *     file_count: int,
     *     skipped_non_text: int,
     *     chars: int
     * }|null
     */
    public static function get(int|string $conversationId): ?array
    {
        $payload = session(self::sessionKey($conversationId));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array{
     *     status: string,
     *     content: string|null,
     *     project_id: int|null,
     *     repository: string|null,
     *     branch: string|null,
     *     file_count: int,
     *     skipped_non_text: int,
     *     chars: int
     * }  $payload
     */
    public static function put(int|string $conversationId, array $payload): void
    {
        session([self::sessionKey($conversationId) => $payload]);
    }

    public static function forget(int|string $conversationId): void
    {
        session()->forget(self::sessionKey($conversationId));
    }

    /**
     * @return array{
     *     status: string,
     *     content: string|null,
     *     project_id: int|null,
     *     repository: string|null,
     *     branch: string|null,
     *     file_count: int,
     *     skipped_non_text: int,
     *     chars: int
     * }
     */
    public static function store(int|string $conversationId, Project $project, ProjectDocsResult $result): array
    {
        $payload = self::payloadFrom($project, $result);
        self::put($conversationId, $payload);

        return $payload;
    }

    /**
     * @return array{
     *     status: string,
     *     content: string|null,
     *     project_id: int|null,
     *     repository: string|null,
     *     branch: string|null,
     *     file_count: int,
     *     skipped_non_text: int,
     *     chars: int
     * }
     */
    public static function payloadFrom(Project $project, ProjectDocsResult $result): array
    {
        $ok = $result->status === ProjectDocsResult::STATUS_OK;

        return [
            'status' => $result->status,
            'content' => $ok ? $result->content : null,
            'project_id' => $project->id,
            'repository' => $project->repository,
            'branch' => $project->branch,
            'file_count' => $result->filesRead,
            'skipped_non_text' => $result->skippedNonText,
            'chars' => $result->chars,
        ];
    }

    public static function isFailed(int|string $conversationId): bool
    {
        return (self::get($conversationId)['status'] ?? null) === ProjectDocsResult::STATUS_FAILED;
    }

    public static function canRetry(int|string $conversationId): bool
    {
        $status = self::get($conversationId)['status'] ?? null;

        return in_array($status, [
            ProjectDocsResult::STATUS_FAILED,
            ProjectDocsResult::STATUS_EMPTY,
        ], true);
    }

    public static function assistantMessage(ProjectDocsResult $result): string
    {
        return match ($result->status) {
            ProjectDocsResult::STATUS_OK => sprintf(
                'Revisão carregada (%s). Você já pode gerar o card.',
                self::filesLabel($result->filesRead),
            ),
            ProjectDocsResult::STATUS_EMPTY => 'Não há informações adicionais em /docs. A geração do card segue sem esse contexto.',
            ProjectDocsResult::STATUS_TOO_LARGE => 'A revisão final não pôde ser feita: o conteúdo de /docs excede o teto. A geração do card segue sem /docs.',
            default => 'Não foi possível realizar a revisão final. Você pode tentar novamente antes de gerar o card.',
        };
    }

    private static function filesLabel(int $count): string
    {
        return $count === 1 ? '1 arquivo' : $count.' arquivos';
    }
}
