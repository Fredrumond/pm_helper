<?php

namespace App\Services;

use App\Contracts\LlmGateway;
use App\Models\Conversation;
use App\Prompts\SystemPromptCatalog;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentRag
{
    public function __construct(
        private readonly DocumentSearch $search,
        private readonly LlmGateway $llm,
        private readonly ?SystemPromptCatalog $prompts = null,
    ) {}

    /**
     * @return array{matches: list<array{document: string, chunk_index: int, content: string, heading_path: ?string, score: float}>, answer: ?string}
     */
    public function ask(string $question, ?string $model = null, ?Conversation $conversation = null, bool $scoped = false): array
    {
        $matches = $this->search->search($question, $scoped ? $conversation?->project_id : null, $scoped, $conversation);

        if ($matches === []) {
            $this->logConsulta($conversation, $matches, false, null);

            return [
                'matches' => [],
                'answer' => null,
            ];
        }

        try {
            $answer = $this->llm->completePrompt([
                ['role' => 'user', 'content' => $this->prompt($question, $matches)],
            ], $model, 'rag', $conversation);
            $calledLlm = true;
        } catch (Throwable $e) {
            Log::error('Falha ao consultar a LLM no RAG', [
                'conversation_id' => $conversation?->id,
                'error' => $e->getMessage(),
            ]);

            $answer = 'Não consegui responder agora: '.$e->getMessage();
            $calledLlm = false;
        }

        $this->logConsulta($conversation, $matches, $calledLlm, $model);

        return [
            'matches' => $matches,
            'answer' => $answer,
        ];
    }

    /**
     * @param  list<array{document: string, chunk_index: int, content: string, heading_path: ?string, score: float}>  $matches
     */
    private function logConsulta(?Conversation $conversation, array $matches, bool $llm, ?string $model): void
    {
        Log::info('rag.consulta', [
            'conversation_id' => $conversation?->id,
            'chunks' => count($matches),
            'documents' => array_values(array_unique(array_column($matches, 'document'))),
            'llm' => $llm,
            'model' => $llm ? $model : null,
        ]);
    }

    /**
     * @param  list<array{document: string, chunk_index: int, content: string, heading_path: ?string, score: float}>  $matches
     */
    private function prompt(string $question, array $matches): string
    {
        $instruction = $this->prompts()->current('rag')->content;
        $blocks = array_map(function (array $match): string {
            $label = $match['document'];
            $path = $match['heading_path'] ?? null;

            if (is_string($path) && $path !== '') {
                $label .= ' › '.$path;
            }

            return '['.$label."]\n".$match['content'];
        }, $matches);
        $context = implode("\n\n", $blocks);

        return <<<TXT
{$instruction}

CONTEXTO:
{$context}

PERGUNTA:
{$question}
TXT;
    }

    private function prompts(): SystemPromptCatalog
    {
        return $this->prompts ?? new SystemPromptCatalog;
    }
}
