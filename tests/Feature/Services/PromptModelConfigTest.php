<?php

namespace Tests\Feature\Services;

use App\Models\LlmModel;
use App\Models\PromptModel;
use App\Services\PromptModelConfig;
use App\Support\ChatComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class PromptModelConfigTest extends TestCase
{
    use RefreshDatabase;

    private PromptModelConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.model' => 'openai/gpt-4o-mini',
            'chat.prompts.docs_retrieval.model' => null,
            'chat.prompts.docs_briefing.model' => null,
        ]);

        CatalogModels::seed([
            ['id' => 'openai/gpt-4o-mini', 'name' => 'Mini'],
            ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o'],
            ['id' => 'anthropic/claude', 'name' => 'Claude'],
        ]);

        $this->config = new PromptModelConfig;
    }

    public function test_without_record_interview_and_card_generation_use_default_model(): void
    {
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('interview'));
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('card_generation'));
        $this->assertContains($this->config->active('interview'), array_column(ChatComposer::models(), 'id'));
    }

    public function test_without_record_docs_prompts_use_env_when_allowed_otherwise_default(): void
    {
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('docs_retrieval'));
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('docs_briefing'));

        config([
            'chat.prompts.docs_retrieval.model' => 'openai/gpt-4o',
            'chat.prompts.docs_briefing.model' => 'anthropic/claude',
        ]);

        $this->assertSame('openai/gpt-4o', $this->config->active('docs_retrieval'));
        $this->assertSame('anthropic/claude', $this->config->active('docs_briefing'));

        config([
            'chat.prompts.docs_retrieval.model' => '',
            'chat.prompts.docs_briefing.model' => 'modelo-fora-do-catalogo',
        ]);

        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('docs_retrieval'));
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('docs_briefing'));
    }

    public function test_active_returns_stored_model_even_when_env_differs(): void
    {
        config([
            'chat.prompts.docs_retrieval.model' => 'anthropic/claude',
            'chat.prompts.docs_briefing.model' => 'openai/gpt-4o-mini',
        ]);

        $this->assertTrue($this->config->save('interview', 'openai/gpt-4o'));
        $this->assertTrue($this->config->save('card_generation', 'anthropic/claude'));
        $this->assertTrue($this->config->save('docs_retrieval', 'openai/gpt-4o-mini'));
        $this->assertTrue($this->config->save('docs_briefing', 'openai/gpt-4o'));

        $this->assertSame('openai/gpt-4o', $this->config->active('interview'));
        $this->assertSame('anthropic/claude', $this->config->active('card_generation'));
        $this->assertSame('openai/gpt-4o-mini', $this->config->active('docs_retrieval'));
        $this->assertSame('openai/gpt-4o', $this->config->active('docs_briefing'));

        $this->assertTrue($this->config->save('interview', 'anthropic/claude'));
        $this->assertSame(1, PromptModel::query()->where('prompt', 'interview')->count());
        $this->assertSame('anthropic/claude', $this->config->active('interview'));
    }

    public function test_two_prompts_can_store_the_same_model_id(): void
    {
        $this->assertTrue($this->config->save('interview', 'openai/gpt-4o'));
        $this->assertTrue($this->config->save('docs_retrieval', 'openai/gpt-4o'));

        $this->assertSame(2, PromptModel::query()->where('model', 'openai/gpt-4o')->count());
        $this->assertSame('openai/gpt-4o', $this->config->active('interview'));
        $this->assertSame('openai/gpt-4o', $this->config->active('docs_retrieval'));
    }

    public function test_unknown_prompt_and_model_outside_catalog_are_not_stored(): void
    {
        $this->assertTrue($this->config->save('interview', 'openai/gpt-4o'));

        $this->assertFalse($this->config->save('discovery', 'openai/gpt-4o'));
        $this->assertFalse($this->config->save('interview', 'modelo-fora-do-catalogo'));
        $this->assertFalse($this->config->save('', 'openai/gpt-4o'));
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('discovery'));

        $this->assertSame(1, PromptModel::query()->count());
        $this->assertDatabaseHas('prompt_models', [
            'prompt' => 'interview',
            'model' => 'openai/gpt-4o',
        ]);
        $this->assertDatabaseMissing('prompt_models', [
            'prompt' => 'discovery',
        ]);
    }

    public function test_stored_model_removed_from_catalog_falls_back_to_step_default(): void
    {
        $this->config->save('interview', 'anthropic/claude');
        $this->config->save('card_generation', 'anthropic/claude');
        $this->config->save('docs_retrieval', 'anthropic/claude');
        $this->config->save('docs_briefing', 'anthropic/claude');

        LlmModel::query()->where('model_id', 'anthropic/claude')->update(['active' => false]);

        config([
            'services.openrouter.model' => 'openai/gpt-4o-mini',
            'chat.prompts.docs_retrieval.model' => 'openai/gpt-4o',
            'chat.prompts.docs_briefing.model' => 'modelo-fora-do-catalogo',
        ]);

        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('interview'));
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('card_generation'));
        $this->assertSame('openai/gpt-4o', $this->config->active('docs_retrieval'));
        $this->assertSame(ChatComposer::defaultModel(), $this->config->active('docs_briefing'));
        $this->assertNotSame('openai/gpt-4o', ChatComposer::defaultModel());
    }
}
