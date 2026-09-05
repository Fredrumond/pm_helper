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

    public function test_catalog_contains_only_the_free_openrouter_models(): void
    {
        config(['services.openrouter.model' => 'minimax/minimax-m3:free']);

        $this->assertSame([
            'minimax/minimax-m3:free',
            'nvidia/nemotron-3-ultra-550b-a55b:free',
            'poolside/laguna-s-2.1:free',
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
