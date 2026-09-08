<?php

namespace Tests\Unit;

use App\Support\LlmPricing;
use Tests\TestCase;

class LlmPricingTest extends TestCase
{
    public function test_estimates_openai_cost_from_tokens_and_cached_input(): void
    {
        // gpt-4o-mini: input $0.15, cached $0.075, output $0.60 / 1M
        // 7 uncached + 3 cached + 4 output
        $cost = LlmPricing::estimateCost('gpt-4o-mini', 10, 4, 3);

        $this->assertEqualsWithDelta(0.00000368, $cost, 0.00000001);
    }

    public function test_resolves_snapshot_ids_by_longest_prefix(): void
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

    public function test_returns_null_for_unknown_model(): void
    {
        $this->assertNull(LlmPricing::estimateCost('unknown/model', 100, 20));
        $this->assertNull(LlmPricing::resolveProvider('unknown/model'));
    }

    public function test_uses_log_example_tokens_for_gpt_4o_mini(): void
    {
        $cost = LlmPricing::estimateCost('gpt-4o-mini-2024-07-18', 1408, 51, 0);

        $this->assertEqualsWithDelta(0.00024180, $cost, 0.00000001);
    }
}
