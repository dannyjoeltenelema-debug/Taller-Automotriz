<?php

namespace Tests\Feature;

use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Navegación sin recargas: marco persistente, enlaces internos con wire:navigate, logout sin form clásico. */
class NavegacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function super(): User
    {
        return User::where('cedula', '1729057412')->first();
    }

    public function test_paginas_del_panel_usan_marco_persistente_y_wire_navigate(): void
    {
        $veh = Vehiculo::firstOrFail();
        $ot = OrdenTrabajo::firstOrFail();
        $urls = ['/panel/ordenes', '/panel/ordenes/nueva', "/panel/ordenes/{$ot->id}/editar", '/panel/vehiculos', "/panel/vehiculos/{$veh->id}",
            '/panel/clientes', '/panel/citas', '/panel/empleados', '/panel/catalogos', '/panel/configuracion', '/settings/profile',
            '/panel/sistema/auditoria', '/panel/sistema/usuarios'];

        foreach ($urls as $url) {
            $html = $this->actingAs($this->super())->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('x-persist="marco-panel"', $html, $url);
            $this->assertDoesNotMatchRegularExpression('/<form[^>]+method="POST"/i', $html, "{$url} tiene un <form method=POST> clásico.");

            // Todo <a href> interno lleva wire:navigate, salvo PDF/etiquetas que abren en otra pestaña.
            preg_match_all('/<a\s[^>]*href="([^"]+)"[^>]*>/i', $html, $m, PREG_SET_ORDER);
            foreach ($m as [$tag, $href]) {
                $interno = str_starts_with($href, '/') || str_starts_with($href, config('app.url'));
                if ($interno && ! str_contains($tag, 'target="_blank"') && ! str_starts_with($href, '#')) {
                    $this->assertStringContainsString('wire:navigate', $tag, "{$url}: enlace sin wire:navigate → {$tag}");
                }
            }
        }
    }

    public function test_portal_enlaza_al_acceso_con_wire_navigate(): void
    {
        $this->get('/')->assertOk()->assertSee('wire:navigate class="btn btn-sm">Acceso Personal', false);
    }

    public function test_logout_desde_el_panel_responde_sin_redirigir_y_cierra_sesion(): void
    {
        $this->actingAs($this->super())->postJson('/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_barra_de_progreso_con_el_acento(): void
    {
        $this->assertSame('#1E40AF', config('livewire.navigate.progress_bar_color'));
    }
}
