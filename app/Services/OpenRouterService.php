<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Prompts\SystemPrompt;
use App\Prompts\SystemPromptCatalog;
use App\Support\ChatComposer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    private string $baseUrl = 'https://openrouter.ai/api/v1';

    private string $apiKey;

    private string $model;

    public function __construct(
        private readonly SystemPromptCatalog $prompts = new SystemPromptCatalog,
    ) {
        $this->apiKey = trim((string) config('services.openrouter.api_key'));
        $this->model = (string) config('services.openrouter.model', 'anthropic/claude-3.5-sonnet');

        if ($this->apiKey === '') {
            throw new \RuntimeException(
                'OPENROUTER_API_KEY não está configurada. Defina a chave no .env.'
            );
        }
    }

    /**
     * Envia o histórico da conversa para a LLM e retorna a resposta do assistente.
     */
    public function chat(Conversation $conversation, ?string $model = null): string
    {
        $model = $this->resolveModel($model);
        $prompt = $this->resolvePrompt($conversation);

        $messages = array_merge(
            [['role' => 'system', 'content' => $prompt->content]],
            $conversation->toLlmHistory()
        );

        $response = Http::withToken($this->apiKey)
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'PM Card Assistant',
            ])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ]);

        if ($response->failed()) {
            $payload = $response->json();
            $detail = is_array($payload)
                ? ($payload['error']['message'] ?? $response->body())
                : $response->body();

            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $model,
            ]);

            throw new \RuntimeException(
                "OpenRouter retornou HTTP {$response->status()}: {$detail}"
            );
        }

        $data = $response->json();

        Log::info('OpenRouter API response', [
            'conversation_id' => $conversation->id,
            'status' => $response->status(),
            'prompt' => $prompt->identifier(),
            'prompt_hash' => $prompt->hash,
            'model' => is_array($data) ? ($data['model'] ?? $model) : $model,
            'id' => is_array($data) ? ($data['id'] ?? null) : null,
            'provider' => is_array($data) ? ($data['provider'] ?? null) : null,
            'usage' => is_array($data) ? ($data['usage'] ?? null) : null,
            'finish_reason' => is_array($data) ? ($data['choices'][0]['finish_reason'] ?? null) : null,
        ]);

        $content = is_array($data) ? ($data['choices'][0]['message']['content'] ?? '') : '';

        if (empty($content)) {
            throw new \RuntimeException('A LLM retornou uma resposta vazia.');
        }

        if (is_array($data)) {
            LlmUsage::recordFromResponse($conversation, $data, $model, $prompt);
        }

        return $content;
    }

    private function resolveModel(?string $model): string
    {
        if (is_string($model) && $model !== '' && ChatComposer::isAllowedModel($model)) {
            return $model;
        }

        return $this->model;
    }

    private function resolvePrompt(Conversation $conversation): SystemPrompt
    {
        if (is_string($conversation->prompt_version) && $conversation->prompt_version !== '') {
            return $this->prompts->get('discovery', $conversation->prompt_version);
        }

        $prompt = $this->prompts->current('discovery');

        $conversation->update([
            'prompt_version' => $prompt->version,
        ]);

        return $prompt;
    }
}
