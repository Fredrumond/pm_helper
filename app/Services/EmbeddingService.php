<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    public const MODEL = 'text-embedding-3-small';

    /**
     * @return list<float>
     */
    public function embed(string $text): array
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

        $embedding = $response->json('data.0.embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new \RuntimeException('A API de embedding retornou um vetor inválido.');
        }

        return $embedding;
    }
}
