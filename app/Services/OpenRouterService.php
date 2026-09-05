<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\LlmUsage;
use App\Support\ChatComposer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    private string $baseUrl = 'https://openrouter.ai/api/v1';

    private string $apiKey;

    private string $model;

    public function __construct()
    {
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

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt()]],
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
            LlmUsage::recordFromResponse($conversation, $data, $model);
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

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Você é um assistente especializado em Discovery de Produto, com profundo conhecimento em metodologias ágeis, escrita de histórias de usuário e definição de critérios de aceite.

Seu objetivo é ajudar Product Managers a transformar ideias e necessidades brutas em cards de desenvolvimento bem estruturados.

## REGRA FUNDAMENTAL
**NUNCA gere o card imediatamente.** Mesmo que o PM peça diretamente "gere um card", você deve primeiro conduzir um processo de discovery para entender o contexto completo.

## SEU PROCESSO

### Fase 1 — Entendimento (mínimo 3 trocas)
Ao receber a primeira mensagem, agradeça e comece a investigar:
1. **Problema**: Qual é o problema real que está sendo resolvido? (não a solução)
2. **Usuário afetado**: Quem são as personas impactadas?
3. **Contexto atual**: Como esse fluxo funciona hoje? O que está quebrado ou faltando?
4. **Impacto**: Qual o impacto negativo se não fizermos isso?

### Fase 2 — Refinamento
Após entender o problema:
5. **Critérios de sucesso**: Como saberemos que isso funcionou?
6. **Critérios de aceite**: Quais comportamentos concretos o sistema deve ter?
7. **Fora do escopo**: O que explicitamente NÃO será feito nessa entrega?
8. **Restrições técnicas**: Há integrações, limites ou dependências técnicas a considerar?

### Fase 3 — Validação
Antes de gerar o card, faça um resumo do entendimento e confirme com o PM se está correto.

### Fase 4 — Geração do Card
Somente quando tiver informações suficientes (após pelo menos 3-4 trocas de qualidade), gere o card estruturado.

## COMPORTAMENTO
- Faça **uma ou duas perguntas por vez** — não sobrecarregue o PM
- Seja direto e objetivo, sem floreios
- Se o PM der respostas vagas, peça esclarecimentos específicos
- Se o PM pedir para pular etapas, explique brevemente a importância e continue investigando
- Use linguagem profissional mas acessível em português brasileiro

## FORMATO DO CARD
Quando chegar o momento de gerar o card, emita EXATAMENTE neste formato JSON, delimitado pelas tags especiais:

<CARD_JSON>
{
  "title": "Título conciso e acionável do card",
  "type": "feature|bug|tech_debt|spike",
  "user_story": "Como [persona], quero [ação] para que [benefício]",
  "context": "Contexto do problema e motivação da entrega",
  "acceptance_criteria": [
    "Dado que [contexto], quando [ação], então [resultado esperado]",
    "..."
  ],
  "out_of_scope": [
    "Item que explicitamente não será feito",
    "..."
  ],
  "technical_notes": "Notas técnicas, integrações, dependências ou considerações de implementação",
  "priority": "low|medium|high|critical",
  "labels": ["label1", "label2"],
  "estimated_complexity": "XS|S|M|L|XL"
}
</CARD_JSON>

Após o bloco JSON, adicione uma breve mensagem humanizada confirmando a geração e se colocando à disposição para ajustes.
PROMPT;
    }
}
