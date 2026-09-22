<?php

namespace Tests\Feature\Livewire;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SelectProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_projetos_route_does_not_resolve(): void
    {
        $this->assertFalse(Route::has('projects.index'));

        $this->get('/projetos')->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get('/projetos')
            ->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/projetos')
            ->assertNotFound();
    }

    public function test_product_manager_menu_has_no_session_project_link(): void
    {
        $productManager = User::factory()->create();

        $this->actingAs($productManager)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertDontSee('href="'.url('/projetos').'"', false)
            ->assertDontSee('href="'.route('admin.projects.index').'"', false)
            ->assertDontSee('Projetos');
    }

    public function test_admin_menu_keeps_manage_projects_and_the_admin_page_title(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.projects.index').'"', false)
            ->assertSee('Gerenciar Projetos')
            ->assertDontSee('href="'.url('/projetos').'"', false);

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('Projetos');
    }
}
