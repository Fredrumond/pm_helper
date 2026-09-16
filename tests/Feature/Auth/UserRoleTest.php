<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_defaults_to_product_manager(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::ProductManager, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_factory_admin_state_creates_an_admin(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->isAdmin());
    }

    public function test_migration_defaults_existing_users_to_product_manager(): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Usuário legado',
            'email' => 'legado@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            UserRole::ProductManager->value,
            DB::table('users')->where('id', $id)->value('role'),
        );
    }

    public function test_admin_gate_allows_admins_and_denies_product_managers(): void
    {
        $admin = User::factory()->admin()->create();
        $productManager = User::factory()->create();

        $this->assertTrue($admin->can('admin'));
        $this->assertFalse($productManager->can('admin'));
    }

    public function test_admin_middleware_forbids_product_managers(): void
    {
        $this->registerAdminProbeRoute();

        $this->actingAs(User::factory()->create())
            ->get('/__probe/admin')
            ->assertForbidden();
    }

    public function test_admin_middleware_allows_admins(): void
    {
        $this->registerAdminProbeRoute();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/__probe/admin')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_database_seeder_promotes_the_test_user_to_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->can('admin'));
    }

    public function test_role_cast_rejects_unknown_values(): void
    {
        $this->expectException(\ValueError::class);

        $user = User::factory()->make();
        $user->role = 'superadmin';
    }

    public function test_promote_admin_command_promotes_an_existing_user(): void
    {
        Event::fake([MessageLogged::class]);

        $user = User::factory()->create([
            'email' => 'pm@example.com',
        ]);

        $this->artisan('user:promote-admin', ['email' => 'pm@example.com'])
            ->expectsOutputToContain('pm@example.com')
            ->assertSuccessful();

        $this->assertSame(UserRole::Admin, $user->fresh()->role);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $log) use ($user): bool {
            return $log->level === 'info'
                && $log->message === 'Usuário promovido a admin.'
                && ($log->context['id'] ?? null) === $user->id
                && ($log->context['email'] ?? null) === 'pm@example.com'
                && ! array_key_exists('password', $log->context);
        });
    }

    public function test_promote_admin_command_fails_for_unknown_email_without_creating_a_user(): void
    {
        $this->artisan('user:promote-admin', ['email' => 'ausente@example.com'])
            ->expectsOutputToContain('ausente@example.com')
            ->assertFailed();

        $this->assertDatabaseMissing('users', [
            'email' => 'ausente@example.com',
        ]);
        $this->assertSame(0, User::query()->count());
    }

    private function registerAdminProbeRoute(): void
    {
        Route::middleware(['web', 'admin'])
            ->get('/__probe/admin', fn () => 'ok');
    }
}
