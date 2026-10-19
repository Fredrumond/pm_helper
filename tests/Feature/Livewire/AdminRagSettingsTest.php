<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AdminRagSettings;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\LlmModel;
use App\Models\RagSetting;
use App\Models\User;
use App\Services\RagConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class AdminRagSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const OPENAI_KEY = 'sk-openai-rag-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => self::OPENAI_KEY]);

        CatalogModels::seed([
            [
                'id' => RagConfig::DEFAULT_EMBEDDING_MODEL,
                'name' => 'Embedding small',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'active' => false,
                'price_input' => 0.02,
                'price_output' => 0,
            ],
            [
                'id' => 'text-embedding-3-large',
                'name' => 'Embedding large',
                'tier' => LlmModel::TIER_PAID,
                'provider' => LlmModel::PROVIDER_OPENAI,
                'active' => true,
            ],
            [
                'id' => 'openai/gpt-4o-mini',
                'name' => 'Mini',
            ],
        ]);
    }

    public function test_guest_is_redirected_and_product_manager_is_forbidden(): void
    {
        $this->get(route('admin.rag.index'))
            ->assertRedirect(route('login'));

        $productManager = User::factory()->create();

        $this->actingAs($productManager)
            ->get(route('admin.rag.index'))
            ->assertForbidden();

        Livewire::actingAs($productManager)
            ->test(AdminRagSettings::class)
            ->assertForbidden();
    }

    public function test_admin_sees_defaults_and_the_whole_catalog_including_inactive_models(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.rag.index'));

        $response->assertOk()
            ->assertSee('Parâmetros do RAG')
            ->assertSee('Embedding small (inativo)')
            ->assertSee('Embedding large')
            ->assertSee('Mini')
            ->assertDontSee(self::OPENAI_KEY, false)
            ->assertDontSee(AdminRagSettings::REINDEX_NOTICE);

        $html = $response->getContent();
        $this->assertStringContainsString('value="'.RagConfig::DEFAULT_EMBEDDING_MODEL.'" selected', $html);

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->assertSet('chunkStrategy', RagConfig::STRATEGY_SIZE)
            ->assertSet('chunkSize', (string) RagConfig::DEFAULT_CHUNK_SIZE)
            ->assertSet('embeddingModel', RagConfig::DEFAULT_EMBEDDING_MODEL)
            ->assertSet('topK', (string) RagConfig::DEFAULT_TOP_K)
            ->assertSet('minScore', '0.00')
            ->assertSee('Sugerido: 0,30')
            ->assertSee('Informações sobre a estratégia de corte', false)
            ->assertSee('Informações sobre o tamanho do trecho', false)
            ->assertSee('Informações sobre o modelo de embedding', false)
            ->assertSee('Informações sobre o top-K', false)
            ->assertSee('Informações sobre o score mínimo', false)
            ->assertSee('Tamanho do chunk')
            ->assertDontSee('Tamanho máximo do trecho');
    }

    public function test_changing_chunk_size_shows_the_reindex_label_before_save_and_does_not_reindex(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['content' => str_repeat('a', 40)]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'trecho antigo',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('chunkSize', '120')
            ->assertSee(AdminRagSettings::REINDEX_NOTICE)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Parâmetros atualizados.')
            ->assertSee(AdminRagSettings::REINDEX_NOTICE);

        $this->assertSame(120, app(RagConfig::class)->chunkSize());
        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'content' => 'trecho antigo',
        ]);
    }

    public function test_changing_only_top_k_does_not_show_the_reindex_label(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('topK', '9')
            ->assertDontSee(AdminRagSettings::REINDEX_NOTICE)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Parâmetros atualizados.')
            ->assertDontSee(AdminRagSettings::REINDEX_NOTICE);

        $this->assertSame(9, app(RagConfig::class)->topK());
        $this->assertSame(RagConfig::DEFAULT_CHUNK_SIZE, app(RagConfig::class)->chunkSize());
    }

    public function test_admin_saves_strategy_and_minimum_score(): void
    {
        $admin = User::factory()->admin()->create();
        $document = Document::factory()->create(['content' => str_repeat('a', 40)]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'trecho antigo',
            'chunk_index' => 0,
            'embedding' => [1.0, 0.0],
        ]);

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('chunkStrategy', RagConfig::STRATEGY_MARKDOWN)
            ->assertSee('Tamanho máximo do trecho')
            ->assertSee(AdminRagSettings::REINDEX_NOTICE)
            ->set('minScore', '0.30')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Parâmetros atualizados.')
            ->assertSee(AdminRagSettings::REINDEX_NOTICE);

        $rag = app(RagConfig::class);
        $this->assertSame(RagConfig::STRATEGY_MARKDOWN, $rag->strategy());
        $this->assertEqualsWithDelta(0.30, $rag->minScore(), 0.001);
        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'content' => 'trecho antigo',
        ]);
    }

    public function test_changing_only_the_minimum_score_does_not_show_the_reindex_label(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('minScore', '0.30')
            ->assertDontSee(AdminRagSettings::REINDEX_NOTICE)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Parâmetros atualizados.')
            ->assertDontSee(AdminRagSettings::REINDEX_NOTICE);

        $this->assertEqualsWithDelta(0.30, app(RagConfig::class)->minScore(), 0.001);
        $this->assertSame(RagConfig::STRATEGY_SIZE, app(RagConfig::class)->strategy());
    }

    public function test_invalid_strategy_or_score_is_rejected_and_not_stored(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('chunkStrategy', RagConfig::STRATEGY_MARKDOWN)
            ->set('minScore', '0.30')
            ->call('save')
            ->assertHasNoErrors()
            ->set('chunkStrategy', 'sentence')
            ->call('save')
            ->assertHasErrors(['chunkStrategy']);

        $this->assertSame(RagConfig::STRATEGY_MARKDOWN, app(RagConfig::class)->strategy());
        $this->assertEqualsWithDelta(0.30, app(RagConfig::class)->minScore(), 0.001);

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('minScore', '1.5')
            ->call('save')
            ->assertHasErrors(['minScore']);

        $this->assertSame(RagConfig::STRATEGY_MARKDOWN, app(RagConfig::class)->strategy());
        $this->assertEqualsWithDelta(0.30, app(RagConfig::class)->minScore(), 0.001);
    }

    public function test_changing_the_embedding_model_shows_the_reindex_label(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('embeddingModel', 'text-embedding-3-large')
            ->assertSee(AdminRagSettings::REINDEX_NOTICE)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(AdminRagSettings::REINDEX_NOTICE);

        $this->assertSame('text-embedding-3-large', app(RagConfig::class)->embeddingModel());
    }

    public function test_save_accepts_any_integer_without_a_range_and_rejects_a_non_integer(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('chunkSize', '100000')
            ->set('topK', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(100000, app(RagConfig::class)->chunkSize());
        $this->assertSame(0, app(RagConfig::class)->topK());

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('chunkSize', 'abc')
            ->set('topK', '4')
            ->call('save')
            ->assertHasErrors(['chunkSize']);

        $this->assertSame(100000, app(RagConfig::class)->chunkSize());
    }

    public function test_model_outside_catalog_is_not_stored(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('embeddingModel', 'modelo-fora-do-catalogo')
            ->call('save')
            ->assertHasErrors(['embeddingModel']);

        $this->assertDatabaseCount('rag_settings', 0);
        $this->assertSame(RagConfig::DEFAULT_EMBEDDING_MODEL, app(RagConfig::class)->embeddingModel());
    }

    public function test_admin_save_creates_audit_for_changed_chunk_size(): void
    {
        config(['audit.console' => true]);

        $admin = User::factory()->admin()->create();
        $rag = app(RagConfig::class);
        $this->assertTrue($rag->save(
            RagConfig::DEFAULT_CHUNK_SIZE,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            RagConfig::DEFAULT_TOP_K,
        ));

        Audit::query()->delete();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->set('chunkSize', '640')
            ->call('save')
            ->assertHasNoErrors();

        $audits = Audit::query()
            ->where('auditable_type', RagSetting::class)
            ->get();

        $this->assertCount(1, $audits);

        $audit = $audits->first();
        $this->assertSame('updated', $audit->event);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame(
            ['chunk_size' => RagConfig::DEFAULT_CHUNK_SIZE, 'sync_sources' => null],
            $audit->old_values,
        );
        $this->assertSame(640, $audit->new_values['chunk_size']);
        $this->assertArrayHasKey('sync_sources', $audit->new_values);
        $this->assertSame(RagSetting::query()->value('id'), $audit->auditable_id);
    }

    public function test_admin_can_save_sync_sources_and_sees_sync_notice(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminRagSettings::class)
            ->assertSet('syncSources', RagConfig::SYNC_SOURCE_KEYS)
            ->assertSee('Fontes de sincronização')
            ->assertSee('docs/discovery/')
            ->set('syncSources', [RagConfig::SYNC_SOURCE_README, RagConfig::SYNC_SOURCE_DOCS_ADR])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(AdminRagSettings::SYNC_SOURCES_NOTICE)
            ->assertSet('syncSources', [RagConfig::SYNC_SOURCE_README, RagConfig::SYNC_SOURCE_DOCS_ADR]);

        $this->assertSame(
            [RagConfig::SYNC_SOURCE_README, RagConfig::SYNC_SOURCE_DOCS_ADR],
            RagSetting::query()->value('sync_sources'),
        );
    }

    public function test_link_shows_for_admin_and_hides_from_product_manager_in_both_menus(): void
    {
        $href = 'href="'.route('admin.rag.index').'"';
        $productManager = User::factory()->create();

        $pmPage = $this->actingAs($productManager)
            ->get(route('conversations.index'));

        $pmPage->assertOk()->assertDontSee($href, false);
        $this->assertSame(0, substr_count($pmPage->getContent(), 'Parâmetros do RAG'));

        $adminPage = $this->actingAs(User::factory()->admin()->create())
            ->get(route('conversations.index'));

        $adminPage->assertOk()->assertSee($href, false);
        $html = $adminPage->getContent();
        $this->assertSame(2, substr_count($html, $href));
        $this->assertSame(2, substr_count($html, 'Parâmetros do RAG'));
    }
}
