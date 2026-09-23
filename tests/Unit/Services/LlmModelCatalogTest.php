<?php

namespace Tests\Unit\Services;

use App\Console\Commands\ImportLlmModelsFromConfig;
use App\Exceptions\LlmModelInUseException;
use App\Exceptions\LlmModelValidationException;
use App\Models\LlmModel;
use App\Models\PromptModel;
use App\Services\LlmModelCatalog;
use App\Services\PromptModelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LlmModelCatalogTest extends TestCase
{
    use RefreshDatabase;

    private LlmModelCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new LlmModelCatalog(new PromptModelConfig);
    }

    public function test_register_creates_a_free_openrouter_model(): void
    {
        $model = $this->catalog->register([
            'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'name' => 'Nemotron 3 Ultra',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $this->assertDatabaseHas('llm_models', [
            'id' => $model->id,
            'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'name' => 'Nemotron 3 Ultra',
            'tier' => 'free',
            'provider' => 'openrouter',
            'active' => 1,
            'price_input' => null,
            'price_cached' => null,
            'price_output' => null,
        ]);
    }

    public function test_register_creates_a_paid_openai_model_with_price(): void
    {
        $model = $this->catalog->register([
            'model_id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 0.15,
            'price_cached' => 0.075,
            'price_output' => 0.60,
        ]);

        $this->assertSame('0.1500', (string) $model->price_input);
        $this->assertSame('0.0750', (string) $model->price_cached);
        $this->assertSame('0.6000', (string) $model->price_output);
    }

    public function test_register_rejects_provider_outside_the_allowed_list(): void
    {
        $this->expectException(LlmModelValidationException::class);

        $this->catalog->register([
            'model_id' => 'anthropic/claude',
            'name' => 'Claude',
            'tier' => LlmModel::TIER_FREE,
            'provider' => 'anthropic',
        ]);
    }

    public function test_register_rejects_invalid_tier(): void
    {
        $this->expectException(LlmModelValidationException::class);

        $this->catalog->register([
            'model_id' => 'openai/gpt-4o',
            'name' => 'GPT-4o',
            'tier' => 'premium',
            'provider' => LlmModel::PROVIDER_OPENAI,
        ]);
    }

    public function test_register_rejects_paid_model_without_price(): void
    {
        $this->expectException(LlmModelValidationException::class);

        $this->catalog->register([
            'model_id' => 'gpt-4o',
            'name' => 'GPT-4o',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
        ]);
    }

    public function test_register_rejects_duplicate_model_id(): void
    {
        $this->catalog->register([
            'model_id' => 'openai/gpt-4o',
            'name' => 'GPT-4o',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $this->expectException(LlmModelValidationException::class);

        $this->catalog->register([
            'model_id' => 'openai/gpt-4o',
            'name' => 'GPT-4o duplicado',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);
    }

    public function test_list_returns_every_model_and_active_returns_only_active_ones(): void
    {
        $active = $this->catalog->register([
            'model_id' => 'model/a',
            'name' => 'A',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $inactive = $this->catalog->register([
            'model_id' => 'model/b',
            'name' => 'B',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $this->catalog->deactivate($inactive->id);

        $this->assertSame(2, $this->catalog->list()->count());

        $activeIds = $this->catalog->active()->pluck('model_id')->all();
        $this->assertSame(['model/a'], $activeIds);
        $this->assertNotNull($this->catalog->findActive('model/a'));
        $this->assertNull($this->catalog->findActive('model/b'));

        $this->assertSame($active->id, $this->catalog->findActive('model/a')->id);
    }

    public function test_activate_turns_an_inactive_model_active_again(): void
    {
        config(['services.openrouter.model' => 'model/keeper']);

        $this->catalog->register([
            'model_id' => 'model/keeper',
            'name' => 'Keeper',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $model = $this->catalog->register([
            'model_id' => 'model/a',
            'name' => 'A',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $this->catalog->deactivate($model->id);
        $this->assertNull($this->catalog->findActive('model/a'));

        $this->catalog->activate($model->id);
        $this->assertNotNull($this->catalog->findActive('model/a'));
    }

    public function test_deactivate_is_allowed_when_model_is_not_used_by_any_prompt(): void
    {
        config(['services.openrouter.model' => 'model/keeper']);

        $this->catalog->register([
            'model_id' => 'model/keeper',
            'name' => 'Keeper',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $model = $this->catalog->register([
            'model_id' => 'model/a',
            'name' => 'A',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $this->catalog->deactivate($model->id);

        $this->assertDatabaseHas('llm_models', [
            'id' => $model->id,
            'active' => 0,
        ]);
    }

    public function test_deactivate_is_blocked_when_model_is_the_active_model_of_a_prompt(): void
    {
        config([
            'services.openrouter.model' => 'model/keeper',
        ]);

        $this->catalog->register([
            'model_id' => 'model/keeper',
            'name' => 'Keeper',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $model = $this->catalog->register([
            'model_id' => 'model/a',
            'name' => 'A',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        PromptModel::query()->create(['prompt' => 'interview', 'model' => 'model/a']);

        try {
            $this->catalog->deactivate($model->id);
            $this->fail('Esperava LlmModelInUseException.');
        } catch (LlmModelInUseException $exception) {
            $this->assertSame('model/a', $exception->modelId);
            $this->assertContains('interview', $exception->prompts);
            $this->assertStringContainsString('interview', $exception->getMessage());
        }

        $this->assertDatabaseHas('llm_models', [
            'id' => $model->id,
            'active' => 1,
        ]);
    }

    public function test_update_price_is_allowed_only_for_paid_models(): void
    {
        $paid = $this->catalog->register([
            'model_id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'tier' => LlmModel::TIER_PAID,
            'provider' => LlmModel::PROVIDER_OPENAI,
            'price_input' => 0.15,
            'price_cached' => 0.075,
            'price_output' => 0.60,
        ]);

        $updated = $this->catalog->updatePrice($paid->id, [
            'price_input' => 0.20,
            'price_cached' => 0.10,
            'price_output' => 0.80,
        ]);

        $this->assertSame('0.2000', (string) $updated->price_input);
        $this->assertSame('0.1000', (string) $updated->price_cached);
        $this->assertSame('0.8000', (string) $updated->price_output);

        $free = $this->catalog->register([
            'model_id' => 'model/free',
            'name' => 'Free',
            'tier' => LlmModel::TIER_FREE,
            'provider' => LlmModel::PROVIDER_OPENROUTER,
        ]);

        $this->expectException(LlmModelValidationException::class);
        $this->catalog->updatePrice($free->id, [
            'price_input' => 0.1,
            'price_cached' => 0.1,
            'price_output' => 0.1,
        ]);
    }

    public function test_there_is_no_method_to_edit_model_id_name_tier_or_provider(): void
    {
        $methods = get_class_methods(LlmModelCatalog::class);

        foreach (['update', 'edit', 'rename', 'setModelId', 'setName', 'setTier', 'setProvider'] as $forbidden) {
            $this->assertNotContains($forbidden, $methods);
        }

        $this->assertContains('activate', $methods);
        $this->assertContains('deactivate', $methods);
        $this->assertContains('updatePrice', $methods);
    }

    public function test_initial_import_loads_the_legacy_snapshot_with_price_for_paid_models(): void
    {
        config([
            'chat.models' => [
                ['id' => 'custom/should-not-import', 'name' => 'Custom', 'tier' => 'Free'],
            ],
            'llm.pricing' => [
                'gpt-4o-mini' => [
                    'provider' => 'openai',
                    'input' => 99,
                    'cached' => 99,
                    'output' => 99,
                ],
            ],
        ]);

        Artisan::call(ImportLlmModelsFromConfig::class);

        $this->assertDatabaseMissing('llm_models', [
            'model_id' => 'custom/should-not-import',
        ]);

        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'tier' => 'free',
            'provider' => 'openrouter',
            'price_input' => null,
        ]);
        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'poolside/laguna-s-2.1:free',
            'tier' => 'free',
            'provider' => 'openrouter',
        ]);
        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'nvidia/nemotron-3.5-lightning:free',
            'tier' => 'free',
            'provider' => 'openrouter',
        ]);
        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'inclusionai/ling-3.0-flash-fin:free',
            'tier' => 'free',
            'provider' => 'openrouter',
        ]);
        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'gpt-4o-mini',
            'tier' => 'paid',
            'provider' => 'openai',
            'price_input' => 0.15,
            'price_cached' => 0.075,
            'price_output' => 0.60,
        ]);
        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'gpt-4o',
            'tier' => 'paid',
            'provider' => 'openai',
            'price_input' => 2.50,
            'price_cached' => 1.25,
            'price_output' => 10.00,
        ]);
        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'gpt-4.1',
            'tier' => 'paid',
            'provider' => 'openai',
            'price_input' => 2.00,
            'price_cached' => 0.50,
            'price_output' => 8.00,
        ]);
        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'o4-mini',
            'tier' => 'paid',
            'provider' => 'openai',
            'price_input' => 0.55,
            'price_cached' => 0.14,
            'price_output' => 2.20,
        ]);

        Artisan::call(ImportLlmModelsFromConfig::class);

        $this->assertSame(8, LlmModel::query()->count());
    }
}
