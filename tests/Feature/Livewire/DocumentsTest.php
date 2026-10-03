<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Documents;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('documents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_uploads_md_file_and_sees_it_in_the_list(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

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
        ]);
    }

    public function test_rejects_non_md_extension(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

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
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'name' => 'antigo.md',
            'content' => 'Antigo',
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
    }
}
