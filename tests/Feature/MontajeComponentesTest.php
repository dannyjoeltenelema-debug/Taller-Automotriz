<?php

namespace Tests\Feature;

use App\Livewire;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\EmpresaColaboradora;
use App\Models\OrdenTrabajo;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire as LW;
use Tests\TestCase;

/**
 * Montaje de CADA componente Livewire del panel y del portal con el rol que le corresponde,
 * y sus acciones principales (crear, editar, eliminar, cambiar estado). Sin errores ni 500.
 */
class MontajeComponentesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('cedula', '1710000009')->first();
        $this->super = User::where('cedula', '1729057412')->first();
    }

    public function test_todos_los_componentes_montan_con_su_rol(): void
    {
        $ot = OrdenTrabajo::firstOrFail();
        $veh = Vehiculo::firstOrFail();

        $panel = [
            [Livewire\Ordenes\Listado::class, []], [Livewire\Ordenes\Formulario::class, []], [Livewire\Ordenes\Formulario::class, ['orden' => $ot]],
            [Livewire\Vehiculos\Listado::class, []], [Livewire\Vehiculos\Formulario::class, []], [Livewire\Vehiculos\Formulario::class, ['vehiculo' => $veh]],
            [Livewire\Vehiculos\Ficha::class, ['vehiculo' => $veh]], [Livewire\Clientes\Listado::class, []], [Livewire\Convenios\Listado::class, []],
            [Livewire\Citas\Listado::class, []], [Livewire\Empleados\Listado::class, []], [Livewire\Configuracion\Catalogos::class, []],
        ];
        foreach ($panel as [$clase, $params]) {
            LW::actingAs($this->admin)->test($clase, $params)->assertOk();
        }

        $soloSuper = [Livewire\Configuracion\DatosEmpresa::class, Livewire\Sistema\Auditoria::class, Livewire\Sistema\Usuarios::class,
            Livewire\Sistema\Sesiones::class, Livewire\Sistema\Eliminados::class, Livewire\Sistema\Estado::class, Livewire\Sistema\Logs::class];
        foreach ($soloSuper as $clase) {
            LW::actingAs($this->super)->withoutLazyLoading()->test($clase)->assertOk();
        }

        LW::test(Livewire\Publico\Inicio::class)->assertOk();
        LW::test(Livewire\Publico\VistaVehiculo::class, ['token' => $veh->qr_token])->assertOk()->assertSee($veh->placa_formateada);
    }

    public function test_clientes_crear_y_editar(): void
    {
        LW::actingAs($this->admin)->test(Livewire\Clientes\Listado::class)
            ->call('nuevo')->set('tipo_documento', 'cedula')->set('numero_documento', '1712345675')
            ->set('nombres', 'Cliente Montaje')->set('telefono', '0991234567')
            ->call('guardar')->assertHasNoErrors()->assertDispatched('toast')->assertDispatched('fila-nueva');
        $c = Cliente::where('numero_documento', '1712345675')->firstOrFail();

        LW::actingAs($this->admin)->test(Livewire\Clientes\Listado::class)
            ->call('editar', $c->id)->set('nombres', 'Cliente Editado')->call('guardar')->assertHasNoErrors();
        $this->assertSame('Cliente Editado', $c->fresh()->nombres);
    }

    public function test_convenios_crear_editar_y_eliminar(): void
    {
        $lw = LW::actingAs($this->admin)->test(Livewire\Convenios\Listado::class)
            ->call('nuevo')->set('nombre', 'Convenio Montaje')->set('porcentaje_descuento', '8')
            ->call('guardar')->assertHasNoErrors()->assertDispatched('fila-nueva');
        $e = EmpresaColaboradora::where('nombre', 'Convenio Montaje')->firstOrFail();

        $lw->call('editar', $e->id)->set('porcentaje_descuento', '9')->call('guardar')->assertHasNoErrors()
            ->call('confirmarEliminar', $e->id)->call('eliminar');
        $this->assertSoftDeleted($e);
    }

    public function test_citas_crear_confirmar_cancelar_y_convertir(): void
    {
        $lw = LW::actingAs($this->admin)->test(Livewire\Citas\Listado::class)
            ->call('nueva')->set('form.placa', 'PBA-4521')->set('form.motivo', 'Revisión de frenos')
            ->call('guardar')->assertHasNoErrors()->assertDispatched('toast');
        $cita = Cita::where('motivo', 'Revisión de frenos')->latest('id')->firstOrFail();

        $lw->call('confirmar', $cita->id);
        $this->assertSame('confirmada', $cita->fresh()->estado);
        $lw->call('convertir', $cita->id)->assertRedirect();
        $lw->call('cancelar', $cita->id);
        $this->assertSame('cancelada', $cita->fresh()->estado);
    }

    public function test_empleados_crear_y_editar(): void
    {
        $lw = LW::actingAs($this->admin)->test(Livewire\Empleados\Listado::class)
            ->call('nuevo')->set('form.nombres', 'Pedro')->set('form.apellidos', 'Montaje')->set('form.cedula', '0102030400')->set('form.cargo', 'mecanico')
            ->call('guardar')->assertHasNoErrors()->assertDispatched('fila-nueva');
        $e = Empleado::where('cedula', '0102030400')->firstOrFail();

        $lw->call('editar', $e->id)->set('form.especialidad', 'Frenos')->call('guardar')->assertHasNoErrors();
        $this->assertSame('Frenos', $e->fresh()->especialidad);
    }

    public function test_catalogos_y_datos_del_taller(): void
    {
        $lw = LW::actingAs($this->admin)->test(Livewire\Configuracion\Catalogos::class)
            ->set('s_nombre', 'Servicio montaje')->set('s_precio', '12.5')->call('guardarServicio')->assertHasNoErrors();
        $s = Servicio::where('nombre', 'Servicio montaje')->firstOrFail();
        $lw->call('toggleServicio', $s->id);
        $this->assertFalse($s->fresh()->activo);
        $lw->set('c_nombre', 'Luces de freno')->call('agregarChecklist')->assertHasNoErrors()
            ->set('m_marca', 'Toyota')->set('m_modelo', 'Corolla Cross')->call('agregarModelo')->assertHasNoErrors();

        LW::actingAs($this->super)->test(Livewire\Configuracion\DatosEmpresa::class)
            ->set('form.telefono', '022999888')->call('guardar')->assertHasNoErrors()->assertDispatched('toast');
        $this->assertSame('022999888', Empresa::first()->telefono);
    }

    public function test_vehiculo_editar_y_ot_cambiar_estado_y_guardar(): void
    {
        $veh = Vehiculo::firstOrFail();
        LW::actingAs($this->admin)->test(Livewire\Vehiculos\Formulario::class, ['vehiculo' => $veh])
            ->set('color', 'Gris Oxford')->call('guardar')->assertHasNoErrors();
        $this->assertSame('Gris Oxford', $veh->fresh()->color);

        $ot = OrdenTrabajo::where('estado', 'recibido')->firstOrFail();
        LW::actingAs($this->admin)->test(Livewire\Ordenes\Listado::class)->call('cambiarEstado', $ot->id, 'en_proceso')->assertDispatched('toast');
        LW::actingAs($this->admin)->test(Livewire\Ordenes\Formulario::class, ['orden' => $ot->fresh()])
            ->set('diagnostico', 'Pastillas gastadas')->call('guardar')->assertHasNoErrors()->assertRedirect();
        $this->assertSame('Pastillas gastadas', $ot->fresh()->diagnostico);
    }

    public function test_portal_agenda_cita(): void
    {
        $veh = Vehiculo::firstOrFail();
        LW::test(Livewire\Publico\VistaVehiculo::class, ['token' => $veh->qr_token])
            ->set('motivo', 'Mantenimiento')->set('cliente_nombre', 'Juan')->set('cliente_telefono', '0991112233')
            ->call('agendar')->assertHasNoErrors()->assertSet('citaEnviada', true);
    }
}
