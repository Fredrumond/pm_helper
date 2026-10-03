<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\LlmUsage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmbeddingService
{
    public const MODEL = RagConfig::DEFAULT_EMBEDDING_MODEL;

    public function __construct(private readonly ?RagConfig $rag = null) {}

    /**
     * @return list<float>
     */
    public function embed(string $text, ?Conversation $conversation = null): array
    {
        $apiKey = trim((string) config('services.openai.api_key'));

        if ($apiKey === '') {
            throw new \RuntimeException(
                'OPENAI_API_KEY não está configurada. Defina a chave no .env.'
            );
        }

        $model = $this->rag()->embeddingModel();

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "OpenAI embeddings retornou HTTP {$response->status()}: {$response->body()}"
            );
        }

        $payload = $response->json();
        $embedding = is_array($payload) ? ($payload['data'][0]['embedding'] ?? null) : null;

        if (! is_array($embedding) || $embedding === []) {
            throw new \RuntimeException('A API de embedding retornou um vetor inválido.');
        }

        $this->recordUsage(is_array($payload) ? $payload : [], $conversation);

        return $embedding;
    }

    private function rag(): RagConfig
    {
        return $this->rag ?? app(RagConfig::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordUsage(array $payload, ?Conversation $conversation): void
    {
        try {
            LlmUsage::recordFromEmbedding(
                $payload,
                $this->rag()->embeddingModel(),
                $conversation,
                Auth::id(),
            );
        } catch (Throwable $exception) {
            Log::error('Falha ao registrar uso de embedding', [
                'model' => $this->rag()->embeddingModel(),
                'conversation_id' => $conversation?->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
