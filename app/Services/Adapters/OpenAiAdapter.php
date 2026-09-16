<?php

namespace App\Services\Adapters;

use App\Contracts\LlmGateway;
use App\Exceptions\LlmTemporarilyUnavailableException;
use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Prompts\SystemPrompt;
use App\Prompts\SystemPromptCatalog;
use App\Support\ChatComposer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiAdapter implements LlmGateway
{
    private string $baseUrl = 'https://api.openai.com/v1';

    private string $apiKey;

    private string $model;

    public function __construct(
        private readonly SystemPromptCatalog $prompts = new SystemPromptCatalog,
    ) {
        $this->apiKey = trim((string) config('services.openai.api_key'));
        $this->model = (string) config('services.openai.model', 'gpt-4o-mini');

        if ($this->apiKey === '') {
            throw new \RuntimeException(
                'OPENAI_API_KEY não está configurada. Defina a chave no .env.'
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

        return $this->complete($conversation, $messages, $model, $prompt, $prompt->name);
    }

    /**
     * Gera o card a partir do resumo da entrevista, sem reenviar o histórico completo.
     */
    public function generateCard(Conversation $conversation, string $summary, ?string $model = null, ?string $projectDocs = null): string
    {
        $model = $this->resolveModel($model);
        $prompt = $this->prompts->current('card_generation');

        return $this->complete(
            $conversation,
            $this->cardMessages($prompt->content, $summary, $projectDocs),
            $model,
            $prompt,
            'card_generation',
        );
    }

    public function completePrompt(
        array $messages,
        ?string $model = null,
        string $step = 'docs_retrieval',
        ?Conversation $conversation = null,
    ): string {
        $model = $this->resolveModel($model);

        return $this->complete($conversation, $messages, $model, $this->prompts->current($step), $step);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function cardMessages(string $prompt, string $summary, ?string $projectDocs): array
    {
        $messages = [
            ['role' => 'system', 'content' => $prompt],
        ];

        if (is_string($projectDocs) && trim($projectDocs) !== '') {
            $messages[] = [
                'role' => 'user',
                'content' => "Regras do projeto (/docs):\n\n{$projectDocs}",
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => "Resumo da entrevista:\n\n{$summary}",
        ];

        return $messages;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function complete(
        ?Conversation $conversation,
        array $messages,
        string $model,
        ?SystemPrompt $prompt,
        string $step,
    ): string {
        $response = $this->requestChatCompletion($messages, $model);

        if ($this->isRateLimited($response)) {
            Log::warning('OpenAI rate limited', [
                'conversation_id' => $conversation?->id,
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $model,
                'step' => $step,
            ]);

            throw new LlmTemporarilyUnavailableException(
                'Não consegui continuar agora. Envie a mensagem novamente em alguns instantes.'
            );
        }

        if ($response->failed()) {
            $payload = $response->json();
            $detail = is_array($payload)
                ? ($payload['error']['message'] ?? $response->body())
                : $response->body();

            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $model,
                'step' => $step,
            ]);

            throw new \RuntimeException(
                "OpenAI retornou HTTP {$response->status()}: {$detail}"
            );
        }

        $data = $response->json();

        Log::info('OpenAI API response', [
            'conversation_id' => $conversation?->id,
            'status' => $response->status(),
            'prompt' => $prompt?->identifier(),
            'prompt_hash' => $prompt?->hash,
            'step' => $step,
            'model' => is_array($data) ? ($data['model'] ?? $model) : $model,
            'id' => is_array($data) ? ($data['id'] ?? null) : null,
            'usage' => is_array($data) ? ($data['usage'] ?? null) : null,
            'finish_reason' => is_array($data) ? ($data['choices'][0]['finish_reason'] ?? null) : null,
        ]);

        $content = is_array($data) ? ($data['choices'][0]['message']['content'] ?? '') : '';

        if (empty($content)) {
            throw new \RuntimeException('A LLM retornou uma resposta vazia.');
        }

        if (is_array($data) && $conversation !== null && $conversation->id) {
            LlmUsage::recordFromResponse($conversation, $data, $model, $prompt, $step);
        }

        return $content;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function requestChatCompletion(array $messages, string $model): Response
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 4096,
            ]);
    }

    private function isRateLimited(Response $response): bool
    {
        return $response->status() === 429;
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
            return $this->prompts->get(
                $this->resolvePromptName($conversation),
                $conversation->prompt_version,
            );
        }

        $prompt = $this->prompts->current($this->activeMode());

        $conversation->update([
            'prompt_name' => $prompt->name,
            'prompt_version' => $prompt->version,
            'current_step' => $prompt->name === 'discovery' ? 'discovery' : 'interview',
        ]);

        return $prompt;
    }

    private function resolvePromptName(Conversation $conversation): string
    {
        if (is_string($conversation->prompt_name) && $conversation->prompt_name !== '') {
            return $conversation->prompt_name;
        }

        return 'discovery';
    }

    private function activeMode(): string
    {
        $mode = (string) config('chat.mode', 'interview');

        return in_array($mode, ['interview', 'discovery'], true) ? $mode : 'interview';
    }
}
