<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\DocumentChunk;

class DocumentSearch
{
    public const LIMIT = RagConfig::DEFAULT_TOP_K;

    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly CosineSimilarity $cosine,
        private readonly ?RagConfig $rag = null,
    ) {}

    /**
     * @return list<array{document: string, chunk_index: int, content: string, score: float}>
     */
    public function search(string $question, ?int $projectId = null, bool $scoped = false, ?Conversation $conversation = null): array
    {
        $query = $this->embeddings->embed($question, $conversation);

        $chunks = DocumentChunk::query()
            ->whereNotNull('embedding')
            ->with('document');

        if ($scoped) {
            $chunks->whereHas('document', function ($documents) use ($projectId): void {
                if ($projectId === null) {
                    $documents->whereNull('project_id');
                } else {
                    $documents->where('project_id', $projectId);
                }
            });
        }

        $ranked = $chunks
            ->get()
            ->filter(fn (DocumentChunk $chunk): bool => is_array($chunk->embedding) && $chunk->embedding !== [])
            ->map(fn (DocumentChunk $chunk): array => [
                'document' => $chunk->document->name,
                'chunk_index' => $chunk->chunk_index,
                'content' => $chunk->content,
                'score' => $this->cosine->score($query, $chunk->embedding),
            ])
            ->sortByDesc('score')
            ->values();

        $limit = $this->rag()->topK();

        if ($limit < 1) {
            return [];
        }

        return $ranked->take($limit)->all();
    }

    private function rag(): RagConfig
    {
        return $this->rag ?? app(RagConfig::class);
    }
}
