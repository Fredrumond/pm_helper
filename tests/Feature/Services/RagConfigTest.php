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
}
