<?php

namespace Tests\Feature\Versoes;

use App\Livewire\VersoesIndex;
use App\Models\User;
use App\Support\Versoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VersoesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_versoes(): void
    {
        $this->get(route('versoes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_the_full_changelog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('versoes.index'))
            ->assertOk()
            ->assertSee('Versões')
            ->assertSee('0.1.0')
            ->assertSee('0.2.0')
            ->assertSee('0.3.0')
            ->assertSee(Versoes::numeroAtual())
            ->assertSee('39138ba')
            ->assertSee('first commit')
            ->assertSee('Integração OpenRouter')
            ->assertSee('Conversas');
    }

    public function test_search_reduces_the_history_to_matching_releases(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(VersoesIndex::class)
            ->set('busca', 'Convidar membro para o time')
            ->assertSee('Nenhuma entrega encontrada com esses filtros.')
            ->set('busca', '39138ba')
            ->assertSee('0.1.0')
            ->assertSee('Primeira versão')
            ->assertSee('Integração OpenRouter')
            ->assertDontSee('Página /versoes com linha do tempo');
    }

    public function test_estado_filter_toggles_and_clears(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(VersoesIndex::class)
            ->call('filtrarPor', 'development')
            ->assertSet('estado', 'development')
            ->assertSee('Página /versoes com linha do tempo')
            ->assertDontSee('Integração OpenRouter')
            ->call('filtrarPor', 'development')
            ->assertSet('estado', '')
            ->assertSee('Integração OpenRouter');
    }
}
