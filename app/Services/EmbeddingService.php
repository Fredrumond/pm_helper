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
    public const MODEL = 'text-embedding-3-small';

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

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => self::MODEL,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordUsage(array $payload, ?Conversation $conversation): void
    {
        try {
            LlmUsage::recordFromEmbedding(
                $payload,
                self::MODEL,
                $conversation,
                Auth::id(),
            );
        } catch (Throwable $exception) {
            Log::error('Falha ao registrar uso de embedding', [
                'model' => self::MODEL,
                'conversation_id' => $conversation?->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
