<?php

namespace Tests\Unit;

use App\Support\ChatComposer;
use Tests\TestCase;

class ChatComposerTest extends TestCase
{
    public function test_includes_configured_default_model_when_missing_from_list(): void
    {
        config([
            'services.openrouter.model' => 'custom/test-model',
            'chat.models' => [
                ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o', 'tier' => 'High'],
            ],
        ]);

        $ids = array_column(ChatComposer::models(), 'id');

        $this->assertSame('custom/test-model', $ids[0]);
        $this->assertTrue(ChatComposer::isAllowedModel('custom/test-model'));
        $this->assertSame('custom/test-model', ChatComposer::defaultModel());
    }

    public function test_catalog_contains_free_openrouter_and_openai_models(): void
    {
        config(['services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free']);

        $this->assertSame([
            'nvidia/nemotron-3-ultra-550b-a55b:free',
            'poolside/laguna-s-2.1:free',
            'nvidia/nemotron-3.5-lightning:free',
            'inclusionai/ling-3.0-flash-fin:free',
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-4.1',
            'o4-mini',
        ], array_column(ChatComposer::models(), 'id'));
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
