<?php

namespace Tests\Feature;

use App\Livewire\Ordenes\Formulario;
use App\Livewire\Ordenes\Listado;
use App\Livewire\Publico\Inicio;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\CalculadorDescuento;
use App\Services\GeneradorCodigoOT;
use App\Support\Dinero;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Bugs del informe de verificación: cada test falló antes del arreglo y pasa después. */
class BugsVerificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function super(): User
    {
        return User::where('cedula', '1729057412')->first(); // sin empleado vinculado
    }

    private function admin(): User
    {
        return User::where('cedula', '1710000009')->first();
    }

    private function formularioBasico($lw, Vehiculo $v)
    {
        return $lw->call('seleccionarCliente', $v->cliente_id, $v->id)->set('kilometraje_ingreso', 50000);
    }

    // 1 ───────── Superadmin sin empleado puede crear OT eligiendo asesor
    public function test_bug1_superadmin_sin_empleado_crea_ot_eligiendo_asesor(): void
    {
        $v = Vehiculo::firstOrFail();
        $asesor = Empleado::whereIn('cargo', ['asesor', 'admin'])->firstOrFail();

        $lw = $this->formularioBasico(Livewire::actingAs($this->super())->test(Formulario::class), $v)
            ->assertSee('Asesor')
            ->call('guardar')->assertHasErrors('asesor_id')
            ->set('asesor_id', $asesor->id)->call('guardar')->assertHasNoErrors();

        $this->assertSame($asesor->id, OrdenTrabajo::latest('id')->first()->asesor_id);

        // Un técnico no puede ser asesor.
        $tecnico = Empleado::where('cargo', 'tecnico')->firstOrFail();
        $this->formularioBasico(Livewire::actingAs($this->super())->test(Formulario::class), $v)
            ->set('asesor_id', $tecnico->id)->call('guardar')->assertHasErrors('asesor_id');

        // Con empleado vinculado se usa ese, como hoy.
        $this->formularioBasico(Livewire::actingAs($this->admin())->test(Formulario::class), $v)->call('guardar')->assertHasNoErrors();
        $this->assertSame($this->admin()->empleado->id, OrdenTrabajo::latest('id')->first()->asesor_id);
    }

    // 2 ───────── Mecánicos asignables como técnicos (cargos en config)
    public function test_bug2_mecanicos_son_asignables(): void
    {
        $mecanico = Empleado::create(['sede_id' => 1, 'nombres' => 'Mario', 'apellidos' => 'Mecánico', 'cedula' => '0102030405', 'cargo' => 'mecanico']);
        $this->assertTrue(Empleado::tecnicosActivos()->whereKey($mecanico->id)->exists());

        $v = Vehiculo::firstOrFail();
        $this->formularioBasico(Livewire::actingAs($this->admin())->test(Formulario::class), $v)
            ->assertSee('Mario Mecánico')
            ->set('tecnico_id', $mecanico->id)->call('guardar')->assertHasNoErrors();
        $this->assertSame($mecanico->id, OrdenTrabajo::latest('id')->first()->tecnico_id);

        config(['taller.ordenes.cargos_tecnico' => ['tecnico']]);
        $this->assertFalse(Empleado::tecnicosActivos()->whereKey($mecanico->id)->exists());
    }

    // 3 ───────── IVA histórico: la OT guarda su % y no cambia al cambiar el de la empresa
    public function test_bug3_iva_historico_por_orden(): void
    {
        $v = Vehiculo::firstOrFail();
        $aceite = Servicio::where('nombre', 'Cambio de aceite y filtro')->firstOrFail();
        $this->formularioBasico(Livewire::actingAs($this->admin())->test(Formulario::class), $v)
            ->call('agregarServicio', $aceite->id)->call('guardar')->assertHasNoErrors();
        $ot = OrdenTrabajo::latest('id')->first();
        $this->assertEquals(15, $ot->iva_porcentaje);
        $ivaOriginal = $ot->iva;

        Empresa::first()->update(['iva_porcentaje' => 12]);

        // Editar y guardar la OT vieja sigue usando 15 %.
        Livewire::actingAs($this->admin())->test(Formulario::class, ['orden' => $ot])
            ->assertSee('IVA 15%')->call('guardar')->assertHasNoErrors();
        $this->assertEquals(15, $ot->fresh()->iva_porcentaje);
        $this->assertSame($ivaOriginal, $ot->fresh()->iva);

        // El PDF muestra el % guardado.
        $this->actingAs($this->admin())->get("/panel/ordenes/{$ot->id}/pdf")->assertOk();
        $this->assertStringContainsString('IVA 15%', view('pdf.orden', ['orden' => $ot->fresh()->load(['vehiculo.modelo.marca', 'cliente.empresaColaboradora', 'asesor', 'tecnico', 'sede', 'items']), 'empresa' => Empresa::actual(), 'qr' => ''])->render());

        // Una OT nueva usa el vigente.
        $this->formularioBasico(Livewire::actingAs($this->admin())->test(Formulario::class), $v)->call('guardar');
        $this->assertEquals(12, OrdenTrabajo::latest('id')->first()->iva_porcentaje);

        // Las OT sembradas antes de la migración quedaron con el % vigente de entonces.
        $this->assertSame(0, OrdenTrabajo::whereNull('iva_porcentaje')->count());
    }

    // 4 ───────── Dinero exacto (sin float): BCMath y respaldo en centavos dan lo mismo
    public function test_bug4_dinero_exacto_sin_bcmath(): void
    {
        Dinero::usarBcmath(false);
        try {
            $this->test_bug4_dinero_exacto();
        } finally {
            Dinero::usarBcmath(null);
        }
    }

    public function test_bug4_dinero_exacto(): void
    {
        $this->assertSame('0.30', Dinero::sumar('0.1', '0.2'));
        $this->assertSame('1.01', Dinero::r('1.005'));
        $this->assertSame('-1.01', Dinero::r('-1.005'));
        $this->assertSame('100.02', Dinero::multiplicar('3', '33.335')); // 3 × round(33.335) = 3 × 33.34
        $this->assertSame('2.68', Dinero::porcentaje('17.85', '15'));    // 2.6775 → 2.68
        $this->assertSame('8.05', Dinero::r('8.045'));                     // float daría 8.04 (8.044999…)
        $this->assertSame('1234567.89', Dinero::r('1234567.885'));

        $t = app(CalculadorDescuento::class)->calcular([
            ['cantidad' => '3', 'precio_unitario' => '33.335'],
            ['cantidad' => '1', 'precio_unitario' => '0.1'],
            ['cantidad' => '1', 'precio_unitario' => '0.2'],
        ], null, 15.0);
        $this->assertSame(['100.32', '0.00', '15.05', '115.37'], [$t['subtotal'], $t['descuento'], $t['iva'], $t['total']]);
        $this->assertSame('$ 1,234.50', Dinero::mostrar('1234.5'));
        $this->assertSame('$ -5.00', Dinero::mostrar(-5));
        $this->assertSame('0.00', Dinero::r(null));
        $this->assertSame('0.30', Dinero::r(0.1 + 0.2));
    }

    // 5 ───────── Portal con reglas ecuatorianas (normaliza y valida)
    public function test_bug5_portal_valida_placa_y_documento_y_encuentra_todo_lo_sembrado(): void
    {
        RateLimiter::clear('consulta-publica:127.0.0.1');
        Livewire::test(Inicio::class)->set('valor', 'XX')->call('consultar')->assertHasErrors('valor');
        Livewire::test(Inicio::class)->set('tipo', 'cedula')->set('valor', '1700000002')->call('consultar')->assertHasErrors('valor'); // módulo 10 inválido

        config(['taller.portal.consultas_max' => 10000]);
        foreach (Vehiculo::where('activo', true)->pluck('placa') as $placa) {
            Livewire::test(Inicio::class)->set('valor', strtolower(substr($placa, 0, 3)).'-'.substr($placa, 3))->call('consultar')->assertHasNoErrors()->assertRedirect();
        }
        foreach (Cliente::whereIn('tipo_documento', ['cedula', 'ruc'])->whereHas('vehiculos', fn ($q) => $q->where('activo', true))->pluck('numero_documento') as $doc) {
            Livewire::test(Inicio::class)->set('tipo', 'cedula')->set('valor', ' '.$doc.' ')->call('consultar')->assertHasNoErrors();
        }
    }

    // 6 ───────── Transiciones de estado controladas
    public function test_bug6_transiciones_de_estado(): void
    {
        $ot = OrdenTrabajo::where('estado', 'recibido')->firstOrFail();

        $lw = Livewire::actingAs($this->admin())->test(Listado::class);
        $lw->call('cambiarEstado', $ot->id, 'entregado')->assertDispatched('toast'); // inválida: recibido → entregado
        $this->assertSame('recibido', $ot->fresh()->estado);

        $lw->call('cambiarEstado', $ot->id, 'en_proceso');
        $this->assertSame('en_proceso', $ot->fresh()->estado);
        $lw->call('cambiarEstado', $ot->id, 'listo_entrega');

        // Entregar con cobro pendiente: el modal advierte antes de confirmar.
        $this->assertSame('pendiente', $ot->fresh()->estado_cobro);
        $lw->call('cambiarEstado', $ot->id, 'entregado')->assertSee('cobro pendiente');
        $this->assertSame('listo_entrega', $ot->fresh()->estado);
        $lw->call('confirmarEntrega');
        $this->assertSame('entregado', $ot->fresh()->estado);

        // Estado final: el admin no puede reabrir; el listado ya no ofrece opciones.
        $lw->call('cambiarEstado', $ot->id, 'en_proceso');
        $this->assertSame('entregado', $ot->fresh()->estado);

        // El formulario también valida.
        Livewire::actingAs($this->admin())->test(Formulario::class, ['orden' => $ot->fresh()])
            ->set('estado', 'recibido')->call('guardar')->assertHasErrors('estado');

        // El superadmin sí puede reabrir, y queda auditado.
        Livewire::actingAs($this->super())->test(Listado::class)->call('cambiarEstado', $ot->id, 'en_proceso');
        $this->assertSame('en_proceso', $ot->fresh()->estado);
        $this->assertDatabaseHas('auditorias', ['accion' => 'reabierto', 'auditable_id' => $ot->id, 'user_id' => $this->super()->id]);
    }

    // 7 ───────── Sin fila empresa: excepción clara en vez de seguir sin bloqueo
    public function test_bug7_generador_sin_empresa_lanza_excepcion_clara(): void
    {
        // Simula que no existe la fila de empresa (borrarla rompería las FK de sedes).
        Empresa::addGlobalScope('sin_filas', fn ($q) => $q->whereRaw('1 = 0'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empresa');
        DB::transaction(fn () => app(GeneradorCodigoOT::class)->siguiente());
    }

    // 8 ───────── settings/* exige un rol del panel
    public function test_bug8_settings_exige_rol_del_panel(): void
    {
        $sinRol = User::factory()->create();
        foreach (['/settings/profile', '/settings/password', '/settings/appearance'] as $url) {
            $this->actingAs($sinRol)->get($url)->assertForbidden();
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    // 9 ───────── preventLazyLoading activo fuera de producción
    public function test_bug9_prevent_lazy_loading_activo(): void
    {
        $this->expectException(LazyLoadingViolationException::class);
        $vehiculos = Vehiculo::limit(2)->get();
        $vehiculos->first()->cliente; // lazy load con más de un modelo cargado
    }

    public function test_bug9_descripcion_del_vehiculo_no_consulta_dentro_de_bucles(): void
    {
        // Sin eager loading, el accessor ya no esconde un N+1 con loadMissing: en desarrollo avisa.
        $this->assertThrows(fn () => Vehiculo::limit(3)->get()->each(fn ($v) => $v->descripcion), LazyLoadingViolationException::class);

        // Con eager loading: cero consultas en el bucle.
        $vehiculos = Vehiculo::with('modelo.marca')->get();
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $vehiculos->each(fn ($v) => $v->descripcion);
        $this->assertSame(0, $n);
    }

    // 10 ───────── Valores quemados a config
    public function test_bug10_parametros_en_config(): void
    {
        config(['taller.codigo_ot' => ['prefijo' => 'TT', 'relleno' => 6]]);
        $codigo = DB::transaction(fn () => app(GeneradorCodigoOT::class)->siguiente());
        $this->assertMatchesRegularExpression('/^TT-\d{4}-\d{6}$/', $codigo);

        config(['taller.citas.duracion_franja_min' => 30, 'taller.citas.hora_apertura' => 8, 'taller.citas.hora_cierre' => 10]);
        $this->assertSame(['08:00', '08:30', '09:00', '09:30'], Cita::franjas());
        $this->assertSame('08:30–09:00', Cita::etiquetaFranja('08:30'));

        config(['taller.paginacion.clientes' => 2]);
        Livewire::actingAs($this->admin())->test(\App\Livewire\Clientes\Listado::class)
            ->assertViewHas('clientes', fn ($p) => $p->perPage() === 2);
    }
}
