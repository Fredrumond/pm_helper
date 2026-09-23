<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminLlmModels;
use App\Models\LlmModel;
use App\Models\User;
use App\Services\PromptModelConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class AdminLlmModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_product_manager_is_forbidden(): void
    {
        $this->get(route('admin.models.index'))
            ->assertRedirect(route('login'));

        $productManager = User::factory()->create();

        $this->actingAs($productManager)
            ->get(route('admin.models.index'))
            ->assertForbidden();

        Livewire::actingAs($productManager)
            ->test(AdminLlmModels::class)
            ->assertForbidden();
    }

    public function test_admin_registers_a_valid_free_model_and_sees_it_grouped_by_provider(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.models.index'))
            ->assertOk()
            ->assertSee('Modelos LLM')
            ->assertSee('Nenhum modelo no catálogo.');

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('startCreate')
            ->set('model_id', 'nvidia/nemotron-free')
            ->set('name', 'Nemotron Free')
            ->set('tier', LlmModel::TIER_FREE)
            ->set('provider', LlmModel::PROVIDER_OPENROUTER)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Modelo cadastrado.')
            ->assertSee('Nemotron Free')
            ->assertSee('nvidia/nemotron-free')
            ->assertSee('OpenRouter')
            ->assertSee('Ativo')
            ->assertDontSee('Nenhum modelo no catálogo.')
            ->assertDontSee('id="llm-model-id"', false);

        $this->assertDatabaseHas('llm_models', [
            'model_id' => 'nvidia/nemotron-free',
            'name' => 'Nemotron Free',
            'tier' => 'free',
            'provider' => 'openrouter',
            'active' => 1,
            'price_input' => null,
            'price_output' => null,
        ]);
    }

    public function test_admin_registers_a_valid_paid_model_with_price(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('startCreate')
            ->set('model_id', 'gpt-4o-mini')
            ->set('name', 'GPT-4o Mini')
            ->set('tier', LlmModel::TIER_PAID)
            ->set('provider', LlmModel::PROVIDER_OPENAI)
            ->set('price_input', '0.15')
            ->set('price_cached', '0.075')
            ->set('price_output', '0.60')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Modelo cadastrado.')
            ->assertSee('GPT-4o Mini')
            ->assertSee('OpenAI')
            ->assertSee('Ajustar preço');

        $model = LlmModel::query()->where('model_id', 'gpt-4o-mini')->firstOrFail();

        $this->assertSame('paid', $model->tier);
        $this->assertSame('openai', $model->provider);
        $this->assertSame('0.1500', (string) $model->price_input);
        $this->assertSame('0.0750', (string) $model->price_cached);
        $this->assertSame('0.6000', (string) $model->price_output);
    }

    public function test_register_rejects_invalid_provider_tier_and_paid_without_price(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('startCreate')
            ->set('model_id', 'anthropic/claude')
            ->set('name', 'Claude')
            ->set('tier', LlmModel::TIER_FREE)
            ->set('provider', 'anthropic')
            ->call('save')
            ->assertHasErrors(['provider']);

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('startCreate')
            ->set('model_id', 'openai/gpt-4o')
            ->set('name', 'GPT-4o')
            ->set('tier', 'premium')
            ->set('provider', LlmModel::PROVIDER_OPENROUTER)
            ->call('save')
            ->assertHasErrors(['tier']);

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('startCreate')
            ->set('model_id', 'gpt-4o')
            ->set('name', 'GPT-4o')
            ->set('tier', LlmModel::TIER_PAID)
            ->set('provider', LlmModel::PROVIDER_OPENAI)
            ->set('price_input', '')
            ->set('price_output', '')
            ->call('save')
            ->assertHasErrors(['price_input', 'price_output']);

        $this->assertDatabaseCount('llm_models', 0);
    }

    public function test_admin_can_activate_and_deactivate_when_model_is_not_in_use(): void
    {
        config(['services.openrouter.model' => 'model/keeper']);

        CatalogModels::seed([
            ['id' => 'model/keeper', 'name' => 'Keeper'],
            ['id' => 'model/a', 'name' => 'Modelo A'],
        ]);

        $model = LlmModel::query()->where('model_id', 'model/a')->firstOrFail();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('deactivate', $model->id)
            ->assertHasNoErrors()
            ->assertSee('Modelo inativado.')
            ->assertSee('Inativo')
            ->assertSet('errorMessage', null);

        $this->assertFalse($model->fresh()->active);

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('activate', $model->id)
            ->assertHasNoErrors()
            ->assertSee('Modelo ativado.')
            ->assertSee('Ativo');

        $this->assertTrue($model->fresh()->active);
    }

    public function test_deactivate_is_blocked_when_model_is_used_by_a_prompt_and_shows_which_prompt(): void
    {
        config(['services.openrouter.model' => 'model/keeper']);

        CatalogModels::seed([
            ['id' => 'model/keeper', 'name' => 'Keeper'],
            ['id' => 'model/a', 'name' => 'Modelo A'],
        ]);

        $promptModels = app(PromptModelConfig::class);
        $promptModels->save('interview', 'model/a');
        $promptModels->save('docs_retrieval', 'model/keeper');
        $promptModels->save('docs_briefing', 'model/keeper');
        $promptModels->save('card_generation', 'model/keeper');

        $model = LlmModel::query()->where('model_id', 'model/a')->firstOrFail();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('deactivate', $model->id)
            ->assertSee('não pode ser inativado')
            ->assertSee('Entrevista')
            ->assertSee('Modelos dos prompts')
            ->assertDontSee('Modelo inativado.');

        $this->assertTrue($model->fresh()->active);
    }

    public function test_price_can_be_updated_only_for_paid_models(): void
    {
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
                'id' => 'free/model',
                'name' => 'Free Model',
                'tier' => LlmModel::TIER_FREE,
                'provider' => LlmModel::PROVIDER_OPENROUTER,
            ],
        ]);

        $paid = LlmModel::query()->where('model_id', 'gpt-4o-mini')->firstOrFail();
        $free = LlmModel::query()->where('model_id', 'free/model')->firstOrFail();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->assertSee('Ajustar preço')
            ->call('startEditPrice', $paid->id)
            ->set('price_input', '0.20')
            ->set('price_cached', '0.10')
            ->set('price_output', '0.80')
            ->call('savePrice')
            ->assertHasNoErrors()
            ->assertSee('Preço atualizado.');

        $paid->refresh();
        $this->assertSame('0.2000', (string) $paid->price_input);
        $this->assertSame('0.1000', (string) $paid->price_cached);
        $this->assertSame('0.8000', (string) $paid->price_output);

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->call('startEditPrice', $free->id)
            ->assertHasErrors(['price_input']);

        $this->assertNull($free->fresh()->price_input);
    }

    public function test_list_ui_has_no_editable_fields_for_immutable_attributes_after_create(): void
    {
        CatalogModels::seed([
            [
                'id' => 'gpt-4o-mini',
                'name' => 'GPT-4o Mini',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'price_input' => 0.15,
                'price_output' => 0.60,
            ],
        ]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminLlmModels::class)
            ->assertSee('GPT-4o Mini')
            ->assertSee('gpt-4o-mini')
            ->assertSee('paid')
            ->assertSee('Ajustar preço')
            ->assertDontSee('id="llm-model-id"', false)
            ->assertDontSee('id="llm-model-name"', false)
            ->assertDontSee('id="llm-model-provider"', false)
            ->assertDontSee('id="llm-model-tier"', false)
            ->assertDontSee('wire:model="model_id"', false)
            ->assertDontSee('wire:model="name"', false)
            ->assertDontSee('wire:model.live="provider"', false)
            ->assertDontSee('wire:model.live="tier"', false);
    }

    public function test_link_shows_for_admin_and_hides_from_product_manager_in_both_menus(): void
    {
        $href = 'href="'.route('admin.models.index').'"';
        $productManager = User::factory()->create();

        $pmPage = $this->actingAs($productManager)
            ->get(route('conversations.index'));

        $pmPage->assertOk()->assertDontSee($href, false);
        $this->assertSame(0, substr_count($pmPage->getContent(), 'Modelos LLM'));

        $adminPage = $this->actingAs(User::factory()->admin()->create())
            ->get(route('conversations.index'));

        $adminPage->assertOk()->assertSee($href, false);
        $html = $adminPage->getContent();
        $this->assertSame(2, substr_count($html, $href));
        $this->assertSame(2, substr_count($html, 'Modelos LLM'));
    }
}
