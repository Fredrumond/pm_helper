<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_login_form_uses_https_when_behind_a_tls_proxy(): void
    {
        $response = $this->call(
            'GET',
            '/login',
            server: [
                'HTTP_HOST' => 'demo.ngrok-free.dev',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_HOST' => 'demo.ngrok-free.dev',
                'HTTP_X_FORWARDED_PORT' => '443',
            ],
        );

        $response->assertOk();
        $this->assertTrue(request()->secure());
        $this->assertSame('https://demo.ngrok-free.dev/login', url()->current());
        $response->assertSee('action="https://demo.ngrok-free.dev/login"', false);
        $response->assertDontSee('http://localhost', false);
    }
}
