<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * El registro público está deshabilitado: las cuentas las crea el administrador.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_does_not_exist(): void
    {
        $this->assertFalse(Route::has('register'));

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
    }

    public function test_login_screen_has_no_sign_up_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Sign up')
            ->assertDontSee('/register');
    }
}
