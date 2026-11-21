<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\EmpresaColaboradora;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('cedula', '1710000009')->first();
    }

    public function test_crear_actualizar_eliminar_y_restaurar_quedan_registrados(): void
    {
        $this->actingAs($this->admin());

        $c = EmpresaColaboradora::create(['nombre' => 'Convenio auditado', 'porcentaje_descuento' => 5]);
        $c->update(['porcentaje_descuento' => 7, 'nombre' => 'Convenio auditado']); // nombre no cambia
        $c->delete();
        $c->restore();

        $log = Auditoria::where('auditable_type', EmpresaColaboradora::class)->where('auditable_id', $c->id)->orderBy('id')->get();
        $this->assertSame(['creado', 'actualizado', 'eliminado', 'restaurado'], $log->pluck('accion')->all());
        $this->assertSame($this->admin()->id, $log[0]->user_id);
        $this->assertSame('Convenio auditado', $log[0]->valores_despues['nombre']);

        // Solo los campos que cambiaron, con su valor anterior.
        $this->assertSame(['porcentaje_descuento'], array_keys($log[1]->valores_despues));
        $this->assertEquals(5, $log[1]->valores_antes['porcentaje_descuento']);
        $this->assertEquals(7, $log[1]->valores_despues['porcentaje_descuento']);
        $this->assertSame(['porcentaje_descuento' => [$log[1]->valores_antes['porcentaje_descuento'], $log[1]->valores_despues['porcentaje_descuento']]], $log[1]->diff());
    }

    public function test_cada_accion_cuesta_un_solo_insert_y_ningun_select(): void
    {
        $this->actingAs($this->admin());
        $c = EmpresaColaboradora::firstOrFail();

        $sql = [];
        DB::listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });
        $c->update(['contacto' => 'Nuevo contacto']);

        $auditoria = array_filter($sql, fn ($s) => str_contains($s, 'auditorias'));
        $this->assertCount(1, $auditoria);
        $this->assertStringStartsWith('insert', array_values($auditoria)[0]);
        $this->assertSame([], array_values(array_filter($sql, fn ($s) => str_starts_with(strtolower($s), 'select'))), implode("\n", $sql));
    }

    public function test_nunca_guarda_contrasenas_ni_remember_token(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $u->update(['password' => 'Otra-Clave-Segura-1']);
        $u->forceFill(['remember_token' => 'token-secreto'])->save();

        $hash = $u->fresh()->getAuthPassword();
        $todo = Auditoria::all()->map(fn ($a) => json_encode([$a->valores_antes, $a->valores_despues]))->implode(' ');
        $this->assertStringNotContainsString('Otra-Clave-Segura-1', $todo);
        $this->assertStringNotContainsString($hash, $todo);
        $this->assertStringNotContainsString('token-secreto', $todo);

        $cambio = Auditoria::where('auditable_type', User::class)->where('accion', 'actualizado')->latest('id')->firstOrFail();
        $this->assertSame(Auditoria::OCULTO, $cambio->valores_despues['password']);
    }

    public function test_login_logout_y_login_fallido(): void
    {
        Volt::test('auth.login')->set('cedula', '1710000009')->set('password', 'clave-equivocada')->call('login')->assertHasErrors('cedula');

        $fallido = Auditoria::where('accion', 'login_fallido')->latest('id')->firstOrFail();
        $this->assertSame(['cedula' => '1710000009'], $fallido->valores_despues);
        $this->assertNotNull($fallido->ip);
        $this->assertStringNotContainsString('clave-equivocada', json_encode($fallido->toArray()));

        Volt::test('auth.login')->set('cedula', '1710000009')->set('password', '1710000009')->call('login')->assertHasNoErrors();
        $this->assertDatabaseHas('auditorias', ['accion' => 'login', 'user_id' => $this->admin()->id]);

        $this->post('/logout');
        $this->assertDatabaseHas('auditorias', ['accion' => 'logout', 'user_id' => $this->admin()->id]);
    }

    public function test_usuario_bloqueado_no_puede_entrar_y_pierde_la_sesion(): void
    {
        $u = $this->admin();
        $this->actingAs($u)->get('/panel/ordenes')->assertOk();

        $u->forceFill(['bloqueado_at' => now()])->save();
        $this->get('/panel/ordenes')->assertRedirect(route('login'));
        $this->assertGuest();

        Volt::test('auth.login')->set('cedula', '1710000009')->set('password', '1710000009')->call('login')
            ->assertHasErrors('cedula')->assertSee('bloqueado');
        $this->assertGuest();
    }
}
