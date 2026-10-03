<?php

namespace App\Services;

use App\Contracts\LlmGateway;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentRag
{
    public function __construct(
        private readonly DocumentSearch $search,
        private readonly LlmGateway $llm,
    ) {}

    /**
     * @return array{matches: list<array{document: string, chunk_index: int, content: string, score: float}>, answer: ?string}
     */
    public function ask(string $question, ?string $model = null, ?Conversation $conversation = null): array
    {
        $matches = $this->search->search($question);

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
     * @param  list<array{document: string, chunk_index: int, content: string, score: float}>  $matches
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
     * @param  list<array{document: string, chunk_index: int, content: string, score: float}>  $matches
     */
    private function prompt(string $question, array $matches): string
    {
        $context = implode("\n\n", array_column($matches, 'content'));

        return <<<TXT
Responda usando somente o contexto.

CONTEXTO:
{$context}

PERGUNTA:
{$question}
TXT;
    }
}
