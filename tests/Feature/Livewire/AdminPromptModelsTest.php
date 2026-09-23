<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminPromptModels;
use App\Models\PromptModel;
use App\Models\User;
use App\Services\PromptModelConfig;
use App\Support\ChatComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class AdminPromptModelsTest extends TestCase
{
    use RefreshDatabase;

    private const OPENROUTER_KEY = 'sk-openrouter-slice2-secret';

    private const OPENAI_KEY = 'sk-openai-slice2-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.api_key' => self::OPENROUTER_KEY,
            'services.openai.api_key' => self::OPENAI_KEY,
            'services.openrouter.model' => 'openai/gpt-4o-mini',
            'chat.prompts.docs_retrieval.model' => null,
            'chat.prompts.docs_briefing.model' => null,
        ]);

        CatalogModels::seed([
            ['id' => 'openai/gpt-4o-mini', 'name' => 'Mini Slice2'],
            ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o Slice2'],
            ['id' => 'anthropic/claude-slice2', 'name' => 'Claude Slice2'],
        ]);
    }

    public function test_guest_is_redirected_and_product_manager_is_forbidden(): void
    {
        $this->get(route('admin.prompts.index'))
            ->assertRedirect(route('login'));

        $productManager = User::factory()->create();

        $this->actingAs($productManager)
            ->get(route('admin.prompts.index'))
            ->assertForbidden();

        Livewire::actingAs($productManager)
            ->test(AdminPromptModels::class)
            ->assertForbidden();
    }

    public function test_admin_sees_catalog_for_each_prompt_and_the_current_active_model(): void
    {
        $admin = User::factory()->admin()->create();
        $promptModels = app(PromptModelConfig::class);

        $promptModels->save('interview', 'openai/gpt-4o');
        $promptModels->save('docs_retrieval', 'anthropic/claude-slice2');
        $promptModels->save('docs_briefing', 'openai/gpt-4o-mini');
        $promptModels->save('card_generation', 'openai/gpt-4o');

        $response = $this->actingAs($admin)
            ->get(route('admin.prompts.index'));

        $response->assertOk()
            ->assertSee('Modelos dos prompts')
            ->assertDontSee(self::OPENROUTER_KEY, false)
            ->assertDontSee(self::OPENAI_KEY, false);

        $html = $response->getContent();

        foreach (PromptModelConfig::PROMPTS as $prompt) {
            $this->assertStringContainsString('prompt-model-'.$prompt, $html);
            $this->assertStringContainsString($prompt, $html);
            $active = $promptModels->active($prompt);
            $this->assertStringContainsString('value="'.$active.'" selected', $html);
        }

        foreach (array_column(ChatComposer::models(), 'name') as $name) {
            $this->assertSame(4, substr_count($html, $name));
        }

        Livewire::actingAs($admin)
            ->test(AdminPromptModels::class)
            ->assertSet('models.interview', 'openai/gpt-4o')
            ->assertSet('models.docs_retrieval', 'anthropic/claude-slice2')
            ->assertSet('models.docs_briefing', 'openai/gpt-4o-mini')
            ->assertSet('models.card_generation', 'openai/gpt-4o');
    }

    public function test_admin_save_persists_choices_including_the_same_id_on_two_prompts(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminPromptModels::class)
            ->set('models.interview', 'openai/gpt-4o')
            ->set('models.docs_retrieval', 'openai/gpt-4o')
            ->set('models.docs_briefing', 'anthropic/claude-slice2')
            ->set('models.card_generation', 'openai/gpt-4o-mini')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Modelos atualizados.')
            ->assertDontSee(self::OPENROUTER_KEY, false)
            ->assertDontSee(self::OPENAI_KEY, false);

        $promptModels = app(PromptModelConfig::class);

        $this->assertSame('openai/gpt-4o', $promptModels->active('interview'));
        $this->assertSame('openai/gpt-4o', $promptModels->active('docs_retrieval'));
        $this->assertSame('anthropic/claude-slice2', $promptModels->active('docs_briefing'));
        $this->assertSame('openai/gpt-4o-mini', $promptModels->active('card_generation'));

        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->message === 'Modelos dos prompts atualizados.';
        });
    }

    public function test_admin_save_creates_audit_for_changed_prompt_model(): void
    {
        Event::fake([MessageLogged::class]);
        config(['audit.console' => true]);

        $admin = User::factory()->admin()->create();
        $promptModels = app(PromptModelConfig::class);

        $promptModels->save('interview', 'openai/gpt-4o-mini');
        $promptModels->save('docs_retrieval', 'openai/gpt-4o-mini');
        $promptModels->save('docs_briefing', 'anthropic/claude-slice2');
        $promptModels->save('card_generation', 'openai/gpt-4o-mini');

        Audit::query()->delete();

        Livewire::actingAs($admin)
            ->test(AdminPromptModels::class)
            ->set('models.interview', 'openai/gpt-4o')
            ->set('models.docs_retrieval', 'openai/gpt-4o-mini')
            ->set('models.docs_briefing', 'anthropic/claude-slice2')
            ->set('models.card_generation', 'openai/gpt-4o-mini')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Modelos atualizados.');

        $audits = Audit::query()
            ->where('auditable_type', PromptModel::class)
            ->get();

        $this->assertCount(1, $audits);

        $audit = $audits->first();
        $this->assertSame('updated', $audit->event);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame(['model' => 'openai/gpt-4o-mini'], $audit->old_values);
        $this->assertSame(['model' => 'openai/gpt-4o'], $audit->new_values);
        $this->assertSame(
            PromptModel::query()->where('prompt', 'interview')->value('id'),
            $audit->auditable_id
        );

        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->message === 'Modelos dos prompts atualizados.';
        });
    }

    public function test_model_outside_catalog_is_not_stored(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();
        $promptModels = app(PromptModelConfig::class);
        $promptModels->save('interview', 'openai/gpt-4o');

        Livewire::actingAs($admin)
            ->test(AdminPromptModels::class)
            ->set('models.interview', 'modelo-fora-do-catalogo')
            ->set('models.docs_retrieval', 'anthropic/claude-slice2')
            ->set('models.docs_briefing', 'openai/gpt-4o-mini')
            ->set('models.card_generation', 'openai/gpt-4o')
            ->call('save')
            ->assertHasErrors(['models.interview']);

        $this->assertSame('openai/gpt-4o', $promptModels->active('interview'));
        $this->assertSame(ChatComposer::defaultModel(), $promptModels->active('docs_retrieval'));
        $this->assertDatabaseMissing('prompt_models', [
            'prompt' => 'docs_retrieval',
        ]);

        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $log): bool {
            return $log->message === 'Modelos dos prompts atualizados.';
        });
    }

    public function test_link_shows_for_admin_and_hides_from_product_manager_in_both_menus(): void
    {
        $href = 'href="'.route('admin.prompts.index').'"';
        $productManager = User::factory()->create();

        $pmPage = $this->actingAs($productManager)
            ->get(route('conversations.index'));

        $pmPage->assertOk()->assertDontSee($href, false);
        $this->assertSame(0, substr_count($pmPage->getContent(), 'Modelos dos prompts'));

        $adminPage = $this->actingAs(User::factory()->admin()->create())
            ->get(route('conversations.index'));

        $adminPage->assertOk()->assertSee($href, false);
        $html = $adminPage->getContent();
        $this->assertSame(2, substr_count($html, $href));
        $this->assertSame(2, substr_count($html, 'Modelos dos prompts'));
        $this->assertStringContainsString('Gerenciar Projetos', $html);
    }
}
