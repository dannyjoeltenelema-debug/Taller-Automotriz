<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

/**
 * El login es por cédula (campo "Usuario"), no por correo.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Acceso personal')
            ->assertSee('Usuario')
            ->assertSee('Contraseña')
            ->assertSee('Recordarme')
            ->assertSee('Entrar')
            ->assertSee('Volver al inicio')
            ->assertDontSee('Email address');
    }

    public function test_users_can_authenticate_with_cedula(): void
    {
        $user = User::factory()->create(['cedula' => '1712345675']);

        LivewireVolt::test('auth.login')
            ->set('cedula', '1712345675')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_without_email_can_authenticate(): void
    {
        $user = User::factory()->create(['cedula' => '1712345675', 'email' => null]);

        LivewireVolt::test('auth.login')
            ->set('cedula', '1712345675')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        User::factory()->create(['cedula' => '1712345675']);

        LivewireVolt::test('auth.login')
            ->set('cedula', '1712345675')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('cedula')
            ->assertSee('Usuario o contraseña incorrectos.');

        $this->assertGuest();
    }

    public function test_unknown_cedula_can_not_authenticate(): void
    {
        LivewireVolt::test('auth.login')
            ->set('cedula', '0000000000')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('cedula')
            ->assertSee('Usuario o contraseña incorrectos.');

        $this->assertGuest();
    }

    public function test_email_is_not_accepted_as_username(): void
    {
        $user = User::factory()->create(['cedula' => '1712345675']);

        LivewireVolt::test('auth.login')
            ->set('cedula', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('cedula');

        $this->assertGuest();
    }

    public function test_usuario_is_required(): void
    {
        LivewireVolt::test('auth.login')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['cedula' => 'required']);
    }

    public function test_seeded_superadmin_can_log_in_with_cedula(): void
    {
        $this->seed();

        LivewireVolt::test('auth.login')
            ->set('cedula', '1729057412')
            ->set('password', '1729057412')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole('superadmin'));
        $this->get('/panel/ordenes')->assertOk();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
