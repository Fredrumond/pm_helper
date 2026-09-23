<?php

namespace Tests\Unit;

use App\Models\LlmModel;
use App\Support\LlmPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class LlmPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CatalogModels::seed([
            [
                'id' => 'gpt-4o-mini',
                'name' => 'GPT-4o Mini',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'price_input' => 0.15,
                'price_cached' => 0.075,
                'price_output' => 0.60,
            ],
            [
                'id' => 'gpt-4o',
                'name' => 'GPT-4o',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'price_input' => 2.50,
                'price_cached' => 1.25,
                'price_output' => 10.00,
            ],
            [
                'id' => 'nvidia/nemotron:free',
                'name' => 'Nemotron',
                'tier' => LlmModel::TIER_FREE,
                'provider' => LlmModel::PROVIDER_OPENROUTER,
            ],
        ]);
    }

    public function test_estimates_openai_cost_from_catalog_price(): void
    {
        config([
            'llm.pricing' => [
                'gpt-4o-mini' => [
                    'provider' => 'openai',
                    'input' => 99,
                    'cached' => 99,
                    'output' => 99,
                ],
            ],
        ]);

        // gpt-4o-mini no catálogo: input $0.15, cached $0.075, output $0.60 / 1M
        // 7 uncached + 3 cached + 4 output
        $cost = LlmPricing::estimateCost('gpt-4o-mini', 10, 4, 3);

        $this->assertEqualsWithDelta(0.00000368, $cost, 0.00000001);
        $this->assertSame('openai', LlmPricing::resolveProvider('gpt-4o-mini'));
    }

    public function test_resolves_snapshot_ids_by_longest_catalog_prefix(): void
    {
        $this->assertSame(
            LlmPricing::ratesFor('gpt-4o-mini'),
            LlmPricing::ratesFor('gpt-4o-mini-2024-07-18'),
        );
        $this->assertNotSame(
            LlmPricing::ratesFor('gpt-4o'),
            LlmPricing::ratesFor('gpt-4o-mini-2024-07-18'),
        );
        $this->assertSame('openai', LlmPricing::resolveProvider('gpt-4o-mini-2024-07-18'));
    }

    public function test_returns_null_for_unknown_or_free_model(): void
    {
        $this->assertNull(LlmPricing::estimateCost('unknown/model', 100, 20));
        $this->assertNull(LlmPricing::resolveProvider('unknown/model'));
        $this->assertNull(LlmPricing::ratesFor('nvidia/nemotron:free'));
    }

    public function test_inactive_paid_model_keeps_its_catalog_price(): void
    {
        LlmModel::query()->where('model_id', 'gpt-4o-mini')->update(['active' => false]);

        $this->assertSame('openai', LlmPricing::resolveProvider('gpt-4o-mini'));
        $this->assertEqualsWithDelta(0.00000368, LlmPricing::estimateCost('gpt-4o-mini', 10, 4, 3), 0.00000001);
    }

    public function test_uses_log_example_tokens_for_gpt_4o_mini_snapshot(): void
    {
        $cost = LlmPricing::estimateCost('gpt-4o-mini-2024-07-18', 1408, 51, 0);

        $this->assertEqualsWithDelta(0.00024180, $cost, 0.00000001);
    }
}
