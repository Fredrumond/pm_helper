<?php

namespace Tests\Unit;

use App\Models\LlmModel;
use App\Support\ChatComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class ChatComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_inject_env_model_when_it_is_absent_from_the_catalog(): void
    {
        CatalogModels::seed([
            ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o'],
        ]);

        config([
            'services.openrouter.model' => 'custom/test-model',
            'chat.models' => [
                ['id' => 'custom/test-model', 'name' => 'Injetado', 'tier' => 'High'],
            ],
        ]);

        $ids = array_column(ChatComposer::models(), 'id');

        $this->assertSame(['openai/gpt-4o'], $ids);
        $this->assertFalse(ChatComposer::isAllowedModel('custom/test-model'));
        $this->assertSame('openai/gpt-4o', ChatComposer::defaultModel());
    }

    public function test_only_active_catalog_models_are_accepted(): void
    {
        CatalogModels::seed([
            ['id' => 'openai/alpha', 'name' => 'Alpha', 'provider' => LlmModel::PROVIDER_OPENAI, 'tier' => LlmModel::TIER_PAID, 'price_input' => 1, 'price_cached' => 1, 'price_output' => 1],
            ['id' => 'openrouter/zeta', 'name' => 'Zeta'],
            ['id' => 'openrouter/hidden', 'name' => 'Hidden', 'active' => false],
        ]);

        config(['services.openrouter.model' => 'openrouter/hidden']);

        $this->assertSame([
            'openai/alpha',
            'openrouter/zeta',
        ], array_column(ChatComposer::models(), 'id'));

        $this->assertTrue(ChatComposer::isAllowedModel('openai/alpha'));
        $this->assertTrue(ChatComposer::isAllowedModel('openrouter/zeta'));
        $this->assertFalse(ChatComposer::isAllowedModel('openrouter/hidden'));
        $this->assertSame('openai/alpha', ChatComposer::defaultModel());
        $this->assertSame('Alpha', ChatComposer::findModel('openai/alpha')['name']);
        $this->assertNull(ChatComposer::findModel('openrouter/hidden'));
    }

    public function test_default_model_uses_env_only_when_that_model_is_active(): void
    {
        CatalogModels::seed([
            ['id' => 'openai/alpha', 'name' => 'Alpha', 'provider' => LlmModel::PROVIDER_OPENAI, 'tier' => LlmModel::TIER_FREE],
            ['id' => 'openrouter/zeta', 'name' => 'Zeta'],
        ]);

        config(['services.openrouter.model' => 'openrouter/zeta']);

        $this->assertSame('openrouter/zeta', ChatComposer::defaultModel());
    }

    public function test_exposes_configured_skills(): void
    {
        $skills = ChatComposer::skills();
        $slashes = array_column($skills, 'slash');

        $this->assertContains('discovery', $slashes);
        $this->assertContains('card', $slashes);
        $this->assertNotEmpty($skills[0]['prompt']);
    }
}
