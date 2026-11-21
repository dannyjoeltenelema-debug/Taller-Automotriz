<?php

namespace Tests\Feature;

use App\Livewire\Sistema\Auditoria;
use App\Livewire\Sistema\Eliminados;
use App\Livewire\Sistema\Estado;
use App\Livewire\Sistema\Logs;
use App\Livewire\Sistema\Sesiones;
use App\Livewire\Sistema\Usuarios;
use App\Models\Auditoria as Registro;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\EmpresaColaboradora;
use App\Models\User;
use App\Services\GestorConvenios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SistemaTest extends TestCase
{
    use RefreshDatabase;

    private const RUTAS = [
        '/panel/sistema/auditoria', '/panel/sistema/usuarios', '/panel/sistema/sesiones',
        '/panel/sistema/eliminados', '/panel/sistema/estado', '/panel/sistema/logs',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function super(): User
    {
        return User::where('cedula', '1729057412')->first();
    }

    private function admin(): User
    {
        return User::where('cedula', '1710000009')->first();
    }

    public function test_solo_el_superadmin_entra_a_sistema(): void
    {
        foreach (self::RUTAS as $url) {
            $this->actingAs($this->super())->get($url)->assertOk();
            $this->actingAs($this->admin())->get($url)->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->get('/panel/sistema/auditoria')->assertRedirect('/login');
    }

    public function test_el_menu_sistema_solo_aparece_al_superadmin(): void
    {
        $this->actingAs($this->super())->get('/panel/ordenes')->assertSee('Registros eliminados');
        $this->actingAs($this->admin())->get('/panel/ordenes')->assertDontSee('Registros eliminados');
    }

    public function test_las_acciones_livewire_de_sistema_dan_403_al_admin(): void
    {
        Livewire::actingAs($this->admin())->test(Usuarios::class)->assertForbidden();
    }

    public function test_auditoria_filtra_muestra_diff_y_exporta_pdf(): void
    {
        $this->actingAs($this->admin());
        $c = EmpresaColaboradora::firstOrFail();
        $c->update(['contacto' => 'Contacto auditado']);

        $id = Registro::where('accion', 'actualizado')->where('auditable_type', EmpresaColaboradora::class)->latest('id')->value('id');

        Livewire::actingAs($this->super())->test(Auditoria::class)
            ->assertDontSee('Contacto auditado') // el diff se carga al pedirlo
            ->call('verDetalle', $id)->assertSee('Contacto auditado')
            ->set('usuario', (string) $this->admin()->id)->assertSee('Contacto auditado')
            ->set('usuario', (string) $this->super()->id)->assertDontSee('Contacto auditado')
            ->set('usuario', '')->set('modulo', EmpresaColaboradora::class)->set('accion', 'actualizado')->assertSee('Contacto auditado')
            ->set('desde', now()->addDay()->toDateString())->assertDontSee('Contacto auditado')
            ->call('limpiarFiltros')->set('buscar', 'María')->assertSee('Contacto auditado')
            ->call('exportarPdf')->assertFileDownloaded();

        $this->assertDatabaseHas('auditorias', ['accion' => 'exportado', 'user_id' => $this->super()->id]);
    }

    public function test_crear_editar_usuario_con_rol_y_empleado(): void
    {
        $empleado = Empleado::whereNull('user_id')->where('cargo', 'admin')->firstOrFail();

        Livewire::actingAs($this->super())->test(Usuarios::class)
            ->call('nuevo')
            ->set('form.name', 'Nuevo Asesor')->set('form.cedula', '1700000002')->set('form.rol', 'admin')->set('form.password', 'clave-larga-1')
            ->call('guardar')->assertHasErrors('form.cedula') // cédula inválida (módulo 10)
            ->set('form.cedula', '1712345675')->set('form.empleado_id', (string) $empleado->id)
            ->call('guardar')->assertHasNoErrors()->assertDispatched('toast');

        $u = User::where('cedula', '1712345675')->firstOrFail();
        $this->assertTrue($u->hasRole('admin'));
        $this->assertTrue(Hash::check('clave-larga-1', $u->password));
        $this->assertSame($u->id, $empleado->fresh()->user_id);
        $this->assertDatabaseHas('auditorias', ['accion' => 'creado', 'auditable_type' => User::class, 'auditable_id' => $u->id]);
    }

    public function test_superadmin_no_se_quita_el_rol_ni_se_bloquea_y_queda_al_menos_uno(): void
    {
        $yo = $this->super();
        Livewire::actingAs($yo)->test(Usuarios::class)
            ->call('editar', $yo->id)->set('form.rol', 'admin')->call('guardar')->assertHasErrors('form.rol')
            ->call('pedirConfirmacion', $yo->id, 'bloquear')->call('confirmar');
        $this->assertNull($yo->fresh()->bloqueado_at);
        $this->assertTrue($yo->fresh()->hasRole('superadmin'));

        // Bloquear al otro superadmin está bien; pero entonces el último activo no puede perder el rol.
        $otro = User::where('cedula', '1721708608')->first();
        Livewire::actingAs($yo)->test(Usuarios::class)->call('pedirConfirmacion', $otro->id, 'bloquear')->call('confirmar');
        $this->assertNotNull($otro->fresh()->bloqueado_at);

        $this->assertSame(1, User::role('superadmin')->whereNull('bloqueado_at')->count());
    }

    public function test_bloquear_cierra_sus_sesiones_y_restablecer_genera_clave_temporal(): void
    {
        $admin = $this->admin();
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'sesion-del-admin', 'user_id' => $admin->id, 'ip_address' => '10.0.0.5', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        $lw = Livewire::actingAs($this->super())->test(Usuarios::class)
            ->call('pedirConfirmacion', $admin->id, 'bloquear')->assertSee('no podrá iniciar sesión')->call('confirmar');
        $this->assertNotNull($admin->fresh()->bloqueado_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-del-admin']);

        $lw->call('pedirConfirmacion', $admin->id, 'desbloquear')->call('confirmar');
        $this->assertNull($admin->fresh()->bloqueado_at);

        $lw->call('pedirConfirmacion', $admin->id, 'restablecer')->call('confirmar');
        $clave = $lw->get('claveTemporal');
        $this->assertNotEmpty($clave);
        $this->assertTrue(Hash::check($clave, $admin->fresh()->password));
        $this->assertSame(3, Registro::whereIn('accion', ['bloqueado', 'desbloqueado', 'password_restablecida'])->count());
    }

    public function test_sesiones_lista_y_cierra(): void
    {
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'otra-sesion', 'user_id' => $this->admin()->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'Firefox', 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        Livewire::actingAs($this->super())->test(Sesiones::class)
            ->assertSee('10.0.0.9')->call('confirmarCierre', 'otra-sesion')->call('cerrar');

        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion']);
        $this->assertDatabaseHas('auditorias', ['accion' => 'sesion_cerrada']);
    }

    public function test_sesiones_con_otro_driver_muestra_aviso(): void
    {
        config(['session.driver' => 'file']);
        Livewire::actingAs($this->super())->test(Sesiones::class)->assertSee('no se guardan en base de datos');
    }

    public function test_registros_eliminados_restaura_sin_revincular_clientes(): void
    {
        $convenio = EmpresaColaboradora::where('porcentaje_descuento', 10)->firstOrFail();
        $cliente = Cliente::where('empresa_colaboradora_id', $convenio->id)->firstOrFail();
        app(GestorConvenios::class)->eliminar($convenio);

        Livewire::actingAs($this->super())->test(Eliminados::class)
            ->assertSee($convenio->nombre)
            ->call('confirmarRestaurar', $convenio->id)->assertSee('NO se vuelven a vincular solos')
            ->call('restaurar')->assertDispatched('toast');

        $this->assertNotSoftDeleted($convenio);
        $this->assertNull($cliente->fresh()->empresa_colaboradora_id);
        $this->assertDatabaseHas('auditorias', ['accion' => 'restaurado', 'auditable_id' => $convenio->id]);
    }

    public function test_estado_muestra_versiones_y_limpia_caches(): void
    {
        Livewire::actingAs($this->super())->withoutLazyLoading()->test(Estado::class)
            ->assertSee(PHP_VERSION)->assertSee(app()->version())
            ->call('limpiarCaches')->assertDispatched('toast');
        $this->assertDatabaseHas('auditorias', ['accion' => 'cache_limpiada']);
    }

    public function test_visor_de_logs_pagina_y_filtra_sin_salir_de_storage_logs(): void
    {
        $ruta = storage_path('logs/test-visor.log');
        $lineas = [];
        for ($i = 1; $i <= 130; $i++) {
            $nivel = $i % 10 === 0 ? 'ERROR' : 'INFO';
            $lineas[] = "[2026-11-20 10:00:00] testing.{$nivel}: Mensaje número {$i}";
            if ($nivel === 'ERROR') {
                $lineas[] = "#0 traza de la entrada {$i}";
            }
        }
        file_put_contents($ruta, implode("\n", $lineas)."\n");

        try {
            Livewire::actingAs($this->super())->withoutLazyLoading()->test(Logs::class)
                ->set('archivo', 'test-visor.log')
                ->assertSee('Mensaje número 130')->assertDontSee('Mensaje número 30<') // página 1 = 100 más recientes (130…31)
                ->call('irA', 2)->assertSee('Mensaje número 30')->assertDontSee('Mensaje número 31')
                ->set('nivel', 'error')->assertSee('Mensaje número 130')->assertSee('traza de la entrada 130')->assertDontSee('Mensaje número 129')
                ->set('archivo', '../../.env')->assertDontSee('APP_KEY');
        } finally {
            @unlink($ruta);
        }
    }
}
