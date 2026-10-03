<?php

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Models\LlmModel;
use App\Models\LlmUsage;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\RagConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_text_to_openai_and_returns_the_vector(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.21, -0.43, 0.72]],
                ],
                'model' => EmbeddingService::MODEL,
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ], 200),
        ]);

        $vector = (new EmbeddingService)->embed('Laravel usa Eloquent');

        $this->assertSame([0.21, -0.43, 0.72], $vector);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/embeddings'
                && $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['model'] === EmbeddingService::MODEL
                && $request['input'] === 'Laravel usa Eloquent';
        });
    }

    public function test_records_usage_and_estimated_cost_from_the_catalog(): void
    {
        CatalogModels::seedEmbeddingPrice();
        config(['services.openai.api_key' => 'sk-test']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'id' => 'embd-test-1',
                'data' => [
                    ['embedding' => [0.21, -0.43, 0.72]],
                ],
                'model' => EmbeddingService::MODEL,
                'usage' => [
                    'prompt_tokens' => 1000,
                    'total_tokens' => 1000,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $conversation = Conversation::createExploreFor($user);

        $this->actingAs($user);
        (new EmbeddingService)->embed('texto para vetor', $conversation);

        $usage = LlmUsage::query()->first();
        $this->assertNotNull($usage);
        $this->assertSame($conversation->id, $usage->conversation_id);
        $this->assertSame($user->id, $usage->user_id);
        $this->assertSame(EmbeddingService::MODEL, $usage->model);
        $this->assertSame(LlmUsage::STEP_EMBEDDING, $usage->step);
        $this->assertSame('openai', $usage->provider);
        $this->assertSame(1000, $usage->prompt_tokens);
        $this->assertSame(0, $usage->completion_tokens);
        $this->assertSame(1000, $usage->total_tokens);
        $this->assertEqualsWithDelta(0.00002, (float) $usage->cost, 0.00000001);
    }

    public function test_sends_the_configured_embedding_model(): void
    {
        CatalogModels::seed([[
            'id' => 'text-embedding-3-large',
            'name' => 'Embedding large',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'active' => false,
        ]]);
        $this->assertTrue(app(RagConfig::class)->save(
            RagConfig::DEFAULT_CHUNK_SIZE,
            'text-embedding-3-large',
            RagConfig::DEFAULT_TOP_K,
        ));

        config(['services.openai.api_key' => 'sk-test']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2]],
                ],
            ], 200),
        ]);

        (new EmbeddingService)->embed('texto configurado');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/embeddings'
                && $request['model'] === 'text-embedding-3-large'
                && $request['input'] === 'texto configurado';
        });
    }

    public function test_fails_without_api_key(): void
    {
        config(['services.openai.api_key' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        (new EmbeddingService)->embed('texto');
    }
}
