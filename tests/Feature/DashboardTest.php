<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/panel/ordenes')->assertRedirect('/login');
    }

    public function test_dashboard_redirects_to_work_orders_panel(): void
    {
        $this->seed();
        $this->actingAs(User::where('email', 'admin@taller.ec')->first());

        $this->get(route('dashboard'))->assertRedirect('/panel/ordenes');
        $this->get('/panel/ordenes')->assertOk();
    }
}
