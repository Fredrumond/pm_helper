<?php

namespace Tests\Feature\Services;

use App\Models\LlmModel;
use App\Models\RagSetting;
use App\Services\RagConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class RagConfigTest extends TestCase
{
    use RefreshDatabase;

    private RagConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        CatalogModels::seed([
            [
                'id' => RagConfig::DEFAULT_EMBEDDING_MODEL,
                'name' => 'Embedding small',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'active' => false,
            ],
            [
                'id' => 'text-embedding-3-large',
                'name' => 'Embedding large',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'active' => true,
            ],
        ]);

        $this->config = new RagConfig;
    }

    public function test_without_record_uses_the_current_defaults(): void
    {
        $this->assertSame(RagConfig::DEFAULT_CHUNK_SIZE, $this->config->chunkSize());
        $this->assertSame(RagConfig::DEFAULT_EMBEDDING_MODEL, $this->config->embeddingModel());
        $this->assertSame(RagConfig::DEFAULT_TOP_K, $this->config->topK());
        $this->assertSame(RagConfig::DEFAULT_STRATEGY, $this->config->strategy());
        $this->assertSame(RagConfig::DEFAULT_MIN_SCORE, $this->config->minScore());
        $this->assertDatabaseCount('rag_settings', 0);
    }

    public function test_save_persists_catalog_model_including_inactive_and_any_integer(): void
    {
        $this->assertTrue($this->config->save(100000, RagConfig::DEFAULT_EMBEDDING_MODEL, 50));

        $this->assertSame(100000, $this->config->chunkSize());
        $this->assertSame(RagConfig::DEFAULT_EMBEDDING_MODEL, $this->config->embeddingModel());
        $this->assertSame(50, $this->config->topK());

        $this->assertTrue($this->config->save(0, 'text-embedding-3-large', -2));

        $this->assertSame(0, $this->config->chunkSize());
        $this->assertSame('text-embedding-3-large', $this->config->embeddingModel());
        $this->assertSame(-2, $this->config->topK());
        $this->assertDatabaseCount('rag_settings', 1);
    }

    public function test_model_outside_catalog_is_not_stored(): void
    {
        $this->assertTrue($this->config->save(800, RagConfig::DEFAULT_EMBEDDING_MODEL, 4));

        $this->assertFalse($this->config->save(900, 'modelo-fora-do-catalogo', 8));

        $this->assertSame(800, $this->config->chunkSize());
        $this->assertSame(RagConfig::DEFAULT_EMBEDDING_MODEL, $this->config->embeddingModel());
        $this->assertSame(4, $this->config->topK());
    }

    public function test_embedding_model_falls_back_to_default_when_it_leaves_the_catalog(): void
    {
        $this->assertTrue($this->config->save(640, 'text-embedding-3-large', 6));

        LlmModel::query()->where('model_id', 'text-embedding-3-large')->delete();

        $this->assertSame(640, $this->config->chunkSize());
        $this->assertSame(RagConfig::DEFAULT_EMBEDDING_MODEL, $this->config->embeddingModel());
        $this->assertSame(6, $this->config->topK());
        $this->assertSame('text-embedding-3-large', RagSetting::query()->value('embedding_model'));
    }

    public function test_save_persists_strategy_and_minimum_score(): void
    {
        $this->assertTrue($this->config->save(
            800,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            4,
            RagConfig::STRATEGY_MARKDOWN,
            0.30,
        ));

        $this->assertSame(RagConfig::STRATEGY_MARKDOWN, $this->config->strategy());
        $this->assertEqualsWithDelta(0.30, $this->config->minScore(), 0.001);

        $this->assertTrue($this->config->save(
            800,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            4,
            RagConfig::STRATEGY_PARAGRAPH,
            0,
        ));
        $this->assertSame(RagConfig::STRATEGY_PARAGRAPH, $this->config->strategy());
        $this->assertSame(0.0, $this->config->minScore());

        $this->assertTrue($this->config->save(
            800,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            4,
            RagConfig::STRATEGY_SIZE,
            1,
        ));
        $this->assertSame(1.0, $this->config->minScore());
    }

    public function test_invalid_strategy_or_score_is_not_stored(): void
    {
        $this->assertTrue($this->config->save(
            800,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            4,
            RagConfig::STRATEGY_MARKDOWN,
            0.30,
        ));

        $this->assertFalse($this->config->save(900, RagConfig::DEFAULT_EMBEDDING_MODEL, 8, 'sentence', 0.30));
        $this->assertFalse($this->config->save(900, RagConfig::DEFAULT_EMBEDDING_MODEL, 8, RagConfig::STRATEGY_MARKDOWN, -0.01));
        $this->assertFalse($this->config->save(900, RagConfig::DEFAULT_EMBEDDING_MODEL, 8, RagConfig::STRATEGY_MARKDOWN, 1.01));

        $this->assertSame(800, $this->config->chunkSize());
        $this->assertSame(4, $this->config->topK());
        $this->assertSame(RagConfig::STRATEGY_MARKDOWN, $this->config->strategy());
        $this->assertEqualsWithDelta(0.30, $this->config->minScore(), 0.001);
    }

    public function test_unknown_strategy_and_missing_score_fall_back_to_defaults(): void
    {
        RagSetting::query()->create([
            'chunk_size' => 12,
            'embedding_model' => RagConfig::DEFAULT_EMBEDDING_MODEL,
            'top_k' => 3,
            'chunk_strategy' => 'frase',
            'min_score' => null,
        ]);

        $this->assertSame(RagConfig::DEFAULT_STRATEGY, $this->config->strategy());
        $this->assertSame(RagConfig::DEFAULT_MIN_SCORE, $this->config->minScore());
    }
}
