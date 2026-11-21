<?php

namespace Tests\Feature;

use App\Livewire\Citas\Listado as Citas;
use App\Livewire\Clientes\Listado as Clientes;
use App\Livewire\Empleados\Listado as Empleados;
use App\Livewire\Ordenes\Formulario;
use App\Livewire\Publico\Inicio;
use App\Livewire\Publico\VistaVehiculo;
use App\Models\Cita;
use App\Models\Empleado;
use App\Models\OrdenTrabajo;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Vehiculo;
use App\Rules\CedulaRucEcuador;
use App\Support\Placa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TallerModulosTest extends TestCase
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

    public function test_todas_las_pantallas_del_panel_cargan(): void
    {
        $v = Vehiculo::first();
        $this->actingAs($this->admin());

        foreach ([
            route('ordenes.index'), route('ordenes.create'), route('clientes.index'), route('convenios.index'),
            route('vehiculos.index'), route('vehiculos.create'), route('vehiculos.show', $v), route('vehiculos.edit', $v),
            route('citas.index'), route('empleados.index'), route('catalogos.index'),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('Inventario')->assertSee('Próximamente');
        }
    }

    public function test_datos_del_taller_solo_para_superadmin(): void
    {
        $this->actingAs($this->admin())->get(route('configuracion.empresa'))->assertForbidden();
        $this->actingAs(User::where('cedula', '1729057412')->first())->get(route('configuracion.empresa'))->assertOk();
    }

    public function test_normalizacion_de_placa_y_validacion_de_documentos(): void
    {
        $this->assertSame('PBA4521', Placa::normalizar(' pba-45 21'));
        $this->assertSame('PBA-4521', Placa::formatear('pba4521'));
        $this->assertTrue(CedulaRucEcuador::cedulaValida('1712345675'));
        $this->assertFalse(CedulaRucEcuador::cedulaValida('1712345678'));
        $this->assertFalse(CedulaRucEcuador::cedulaValida('1762345678')); // tercer dígito >= 6
        $this->assertTrue(CedulaRucEcuador::rucValido('1712345675001'));
        $this->assertTrue(CedulaRucEcuador::rucValido('1790012345001'));
        $this->assertFalse(CedulaRucEcuador::rucValido('1712345675002'));
    }

    public function test_cliente_crud_con_convenio(): void
    {
        Livewire::actingAs($this->admin())->test(Clientes::class)
            ->call('nuevo')
            ->set('nombres', 'Lucía Andrango')
            ->set('tipo_documento', 'cedula')
            ->set('numero_documento', '1001234564')
            ->set('telefono', '0981234567')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Lucía Andrango');
    }

    public function test_alta_de_tecnico_sin_usuario_y_asesor_con_usuario(): void
    {
        $sede = Sede::first();
        Livewire::actingAs($this->admin())->test(Empleados::class)
            ->call('nuevo')
            ->set('form.nombres', 'Pablo')->set('form.apellidos', 'Quinga')->set('form.cedula', '1001234564')
            ->set('form.cargo', 'tecnico')->set('form.sede_id', $sede->id)
            ->call('guardar')->assertHasNoErrors();
        $this->assertNull(Empleado::where('cedula', '1001234564')->first()->user_id);

        Livewire::actingAs($this->admin())->test(Empleados::class)
            ->call('nuevo')
            ->set('form.nombres', 'Andrea')->set('form.apellidos', 'Mena')->set('form.cedula', '1713579132')
            ->set('form.cargo', 'asesor')->set('form.sede_id', $sede->id)
            ->set('crearAcceso', true)->set('email', '')->set('password', 'secreto123')
            ->call('guardar')->assertHasNoErrors();
        $user = User::where('cedula', '1713579132')->first();
        $this->assertTrue($user->hasRole('admin'));
        $this->assertSame($user->id, Empleado::where('cedula', '1713579132')->first()->user_id);
    }

    public function test_cita_se_convierte_en_ot_y_queda_enlazada(): void
    {
        $cita = Cita::whereNotNull('vehiculo_id')->where('estado', 'pendiente')->first();

        Livewire::actingAs($this->admin())->test(Citas::class)
            ->call('confirmar', $cita->id)
            ->call('convertir', $cita->id)
            ->assertRedirect(route('ordenes.create', ['cita' => $cita->id]));

        $this->actingAs($this->admin());
        $this->get(route('ordenes.create', ['cita' => $cita->id]))->assertOk();

        Livewire::withQueryParams(['cita' => $cita->id])->actingAs($this->admin())->test(Formulario::class)
            ->assertSet('vehiculo_id', $cita->vehiculo_id)
            ->assertSet('cita_id', $cita->id)
            ->set('kilometraje_ingreso', 50000)
            ->call('agregarServicio', Servicio::first()->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $cita->refresh();
        $this->assertSame('atendida', $cita->estado);
        $this->assertNotNull($cita->orden_id);
        $this->assertSame($cita->vehiculo_id, OrdenTrabajo::find($cita->orden_id)->vehiculo_id);
    }

    public function test_cita_de_placa_no_registrada_abre_alta_rapida(): void
    {
        $cita = Cita::whereNull('vehiculo_id')->first();
        Livewire::withQueryParams(['cita' => $cita->id])->actingAs($this->admin())->test(Formulario::class)
            ->assertSet('modalAlta', true)
            ->assertSet('nuevo.placa', Placa::formatear($cita->placa));
    }

    public function test_portal_publico_consulta_por_placa_y_cedula(): void
    {
        $this->get('/')->assertOk()->assertSee('Acceso Personal')->assertSee('También puede escanear el código QR');

        $v = Vehiculo::porPlaca('PBA4521');
        Livewire::test(Inicio::class)->set('tipo', 'placa')->set('valor', 'pba 4521')->call('consultar')
            ->assertRedirect(route('publico.vehiculo', $v->qr_token));

        // Cliente con 2 vehículos → elegir
        Livewire::test(Inicio::class)->set('tipo', 'cedula')->set('valor', '1791234567001')->call('consultar')
            ->assertSee('Seleccione su vehículo')->assertSee('PDE-5678')->assertSee('PDE-5679');

        Livewire::test(Inicio::class)->set('tipo', 'placa')->set('valor', 'ZZZ9999')->call('consultar')
            ->assertHasErrors('valor');
    }

    public function test_vista_publica_enmascara_y_agenda_cita(): void
    {
        $v = Vehiculo::porPlaca('PBA4521');
        $this->get(route('publico.vehiculo', $v->qr_token))
            ->assertOk()
            ->assertSee('PBA-4521')
            ->assertDontSee('1722223334')            // cédula completa
            ->assertDontSee('9BGKS48U0KG123456')     // VIN completo
            ->assertSee('************23456')
            ->assertSee('50.380 km')               // próximo mantenimiento = km + 5000
            ->assertSee('https://wa.me/593998765432', false);

        Livewire::test(VistaVehiculo::class, ['token' => $v->qr_token])
            ->set('fecha_deseada', now()->addDays(5)->toDateString())
            ->set('hora_inicio', '10:00')
            ->set('motivo', 'Alineación y balanceo')
            ->set('cliente_nombre', 'Juan Pérez')
            ->set('cliente_telefono', '0991234567')
            ->call('agendar')
            ->assertHasNoErrors()
            ->assertSet('citaEnviada', true);

        $this->assertDatabaseHas('citas', ['placa' => 'PBA4521', 'origen' => 'qr_vehiculo', 'estado' => 'pendiente', 'hora_inicio' => '10:00']);
    }

    public function test_token_qr_inexistente_da_404(): void
    {
        $this->get('/q/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }
}
