<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Documents;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\RagConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CatalogModels;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'sk-test']);
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.21, -0.43, 0.72]],
                ],
            ], 200),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('documents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_product_manager_is_forbidden_and_does_not_see_the_documents_link(): void
    {
        $productManager = User::factory()->create();

        $this->actingAs($productManager)
            ->get(route('documents.index'))
            ->assertForbidden();

        Livewire::actingAs($productManager)
            ->test(Documents::class)
            ->assertForbidden();

        $this->actingAs($productManager)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('documents.index').'"', false);
    }

    public function test_admin_accesses_documents_and_sees_the_nav_link(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('href="'.route('documents.index').'"', false)
            ->assertSee('Documentos');

        $this->actingAs($admin)
            ->get(route('documents.index'))
            ->assertOk();
    }

    public function test_authenticated_user_uploads_md_file_and_sees_it_in_the_list(): void
    {
        Storage::fake('local');

        $user = User::factory()->admin()->create();

        $file = UploadedFile::fake()->createWithContent('guia.md', "# Guia\n\nConteúdo de teste.");

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->call('startCreate')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Documento enviado.')
            ->assertSee('guia.md')
            ->assertSee('Conteúdo de teste');

        $this->assertDatabaseHas('documents', [
            'name' => 'guia.md',
            'content' => "# Guia\n\nConteúdo de teste.",
            'project_id' => null,
        ]);
        $this->assertDatabaseHas('document_chunks', [
            'chunk_index' => 0,
            'heading_path' => null,
            'content' => "Guia (guia.md)\n# Guia\n\nConteúdo de teste.",
        ]);
        $this->assertSame(
            [0.21, -0.43, 0.72],
            DocumentChunk::query()->first()?->embedding
        );
    }

    public function test_upload_splits_long_file_into_500_character_chunks(): void
    {
        Storage::fake('local');

        $user = User::factory()->admin()->create();
        $content = str_repeat('a', 500).str_repeat('b', 20);
        $file = UploadedFile::fake()->createWithContent('longo.md', $content);

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->call('startCreate')
            ->set('file', $file)
            ->call('save')
            ->assertHasNoErrors();

        $document = Document::query()->where('name', 'longo.md')->first();

        $this->assertNotNull($document);
        $this->assertDatabaseCount('document_chunks', 2);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => "longo.md (longo.md)\n".str_repeat('a', 500),
        ]);
        $this->assertDatabaseHas('document_chunks', [
            'document_id' => $document->id,
            'chunk_index' => 1,
            'content' => "longo.md (longo.md)\n".str_repeat('b', 20),
        ]);
    }

    public function test_rejects_non_md_extension(): void
    {
        Storage::fake('local');

        $user = User::factory()->admin()->create();

        $file = UploadedFile::fake()->createWithContent('notas.txt', 'texto');

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->call('startCreate')
            ->set('file', $file)
            ->call('save')
            ->assertHasErrors(['file']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_user_can_edit_and_delete_document(): void
    {
        $user = User::factory()->admin()->create();
        $document = Document::factory()->create([
            'name' => 'antigo.md',
            'content' => 'Antigo',
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Antigo',
            'chunk_index' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->call('startEdit', $document->id)
            ->set('name', 'novo.md')
            ->set('content', 'Novo conteúdo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Documento atualizado.')
            ->assertSee('novo.md');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'name' => 'novo.md',
            'content' => 'Novo conteúdo',
        ]);

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->call('destroy', $document->id)
            ->assertDontSee('novo.md');

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('document_chunks', ['document_id' => $document->id]);
    }

    public function test_search_ranks_chunks_by_cosine_and_shows_top_three(): void
    {
        $user = User::factory()->admin()->create();
        $this->seedRankedChunks();

        $component = Livewire::actingAs($user)
            ->test(Documents::class)
            ->set('question', 'quantos quilos de Lumina')
            ->call('search')
            ->assertHasNoErrors()
            ->assertSee('Chunk A mais próximo')
            ->assertSee('Chunk B')
            ->assertSee('Chunk C')
            ->assertDontSee('Chunk D mais distante');

        $matches = $component->get('matches');

        $this->assertCount(3, $matches);
        $this->assertSame(['Chunk A mais próximo', 'Chunk B', 'Chunk C'], array_column($matches, 'content'));
        $this->assertEqualsWithDelta(1.0, $matches[0]['score'], 0.0001);
        $this->assertGreaterThan($matches[1]['score'], $matches[0]['score']);
        $this->assertGreaterThan($matches[2]['score'], $matches[1]['score']);
    }

    public function test_search_does_not_call_llm(): void
    {
        $user = User::factory()->admin()->create();
        $this->seedRankedChunks();

        // Sem nenhum fake de LLM — se chamar a LLM a chamada Http real falharia;
        // verificamos que nenhuma chamada ao endpoint de completions ocorre.
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => [0.21, -0.43, 0.72]]],
            ], 200),
            'https://openrouter.ai/*' => Http::response(['error' => 'should not be called'], 500),
        ]);

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->set('question', 'quantos quilos de Lumina')
            ->call('search')
            ->assertHasNoErrors()
            ->assertSee('Chunk A mais próximo');

        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'completions'));
    }

    public function test_search_requires_a_question(): void
    {
        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->set('question', '   ')
            ->call('search')
            ->assertHasErrors(['question']);
    }

    public function test_search_without_embeddings_keeps_the_empty_index_message(): void
    {
        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->set('question', 'algo sobre o projeto')
            ->call('search')
            ->assertSee('Nenhum chunk com embedding para comparar.')
            ->assertDontSee('Nenhum trecho encontrado.');
    }

    public function test_search_says_when_no_chunk_reaches_the_minimum_score(): void
    {
        CatalogModels::seedEmbeddingPrice();
        $this->assertTrue(app(RagConfig::class)->save(
            RagConfig::DEFAULT_CHUNK_SIZE,
            RagConfig::DEFAULT_EMBEDDING_MODEL,
            RagConfig::DEFAULT_TOP_K,
            RagConfig::STRATEGY_SIZE,
            0.30,
        ));

        $user = User::factory()->admin()->create();
        $document = Document::factory()->create(['name' => 'missao.md']);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Trecho irrelevante.',
            'chunk_index' => 0,
            'embedding' => [0.0, 1.0, 0.0],
        ]);

        Livewire::actingAs($user)
            ->test(Documents::class)
            ->set('question', 'quantos quilos de Lumina')
            ->call('search')
            ->assertSee('Nenhum trecho encontrado.')
            ->assertDontSee('Nenhum chunk com embedding para comparar.')
            ->assertDontSee('Trecho irrelevante.');
    }

    private function seedRankedChunks(): void
    {
        $document = Document::factory()->create(['name' => 'missao.md']);

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk A mais próximo',
            'chunk_index' => 0,
            'embedding' => [0.21, -0.43, 0.72],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk B',
            'chunk_index' => 1,
            'embedding' => [0.21, -0.43, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk C',
            'chunk_index' => 2,
            'embedding' => [0.21, 0.0, 0.0],
        ]);
        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'content' => 'Chunk D mais distante',
            'chunk_index' => 3,
            'embedding' => [0.0, 1.0, 0.0],
        ]);
    }
}
