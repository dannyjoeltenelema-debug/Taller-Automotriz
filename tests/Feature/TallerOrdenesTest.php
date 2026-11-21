<?php

namespace Tests\Feature;

use App\Livewire\Ordenes\Formulario;
use App\Livewire\Ordenes\Listado;
use App\Models\EmpresaColaboradora;
use App\Models\OrdenTrabajo;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TallerOrdenesTest extends TestCase
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

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/panel/ordenes')->assertRedirect('/login');
    }

    public function test_usuario_sin_rol_no_entra_al_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/panel/ordenes')->assertForbidden();
    }

    public function test_listado_muestra_kpis_y_busca_por_placa_cedula_y_nombre(): void
    {
        $this->actingAs($this->admin())->get('/panel/ordenes')->assertOk()->assertSee('OT-'.now()->year.'-0004');

        Livewire::actingAs($this->admin())->test(Listado::class)
            ->set('buscar', 'pcd-1234')->assertSee('PCD-1234')->assertDontSee('PDE-5679')
            ->set('buscar', '0912345675')->assertSee('Ana Lucía')
            ->set('estado', 'todas')->set('buscar', 'Transportes')->assertSee('PDE-5678');
    }

    public function test_cambio_de_estado_inline_registra_trazabilidad(): void
    {
        $orden = OrdenTrabajo::where('estado', 'recibido')->first();
        $antes = $orden->historialEstados()->count();

        Livewire::actingAs($this->admin())->test(Listado::class)->call('cambiarEstado', $orden->id, 'en_proceso');

        $this->assertSame('en_proceso', $orden->fresh()->estado);
        $ultimo = $orden->historialEstados()->get()->last();
        $this->assertSame($antes + 1, $orden->historialEstados()->count());
        $this->assertSame('recibido', $ultimo->estado_anterior);
        $this->assertSame($this->admin()->empleado->id, $ultimo->empleado_id);
    }

    public function test_crea_ot_con_alta_rapida_y_descuento_por_convenio(): void
    {
        $convenio = EmpresaColaboradora::where('porcentaje_descuento', 10)->first();
        $aceite = Servicio::where('nombre', 'Cambio de aceite y filtro')->first(); // 35.00

        Livewire::actingAs($this->admin())->test(Formulario::class)
            ->call('abrirAlta')
            ->set('nuevo.nombres', 'Pedro Paredes')
            ->set('nuevo.tipo_documento', 'cedula')
            ->set('nuevo.numero_documento', '1001234564')
            ->set('nuevo.telefono', '0991112222')
            ->set('nuevo.empresa_colaboradora_id', $convenio->id)
            ->set('nuevo.placa', 'pdx 0101')
            ->set('nuevo.marca', 'Chevrolet')
            ->set('nuevo.modelo', 'Onix')
            ->set('nuevo.anio', 2023)
            ->set('nuevo.color', 'Negro')
            ->call('guardarAlta')
            ->assertHasNoErrors()
            ->set('kilometraje_ingreso', 15000)
            ->assertSet('proximo_kilometraje', 20000)
            ->call('agregarServicio', $aceite->id)
            ->call('agregarLibre')
            ->set('items.1.descripcion', 'Filtro de aire')
            ->set('items.1.cantidad', '2')
            ->set('items.1.precio_unitario', '7.335')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect();

        $v = Vehiculo::porPlaca('PDX-0101');
        $this->assertNotNull($v->qr_token);
        $orden = OrdenTrabajo::where('vehiculo_id', $v->id)->firstOrFail();

        // 35.00 + 2 × 7.34 = 49.68 ; desc 10% = 4.97 ; base 44.71 ; IVA 15% = 6.71 ; total 51.42
        $this->assertSame('49.68', (string) $orden->subtotal);
        $this->assertSame('4.97', (string) $orden->descuento);
        $this->assertSame('6.71', (string) $orden->iva);
        $this->assertSame('51.42', (string) $orden->total);
        $this->assertMatchesRegularExpression('/^OT-\d{4}-0007$/', $orden->codigo);
        $this->assertSame(15000, $v->fresh()->kilometraje_actual);
    }

    public function test_cedula_invalida_es_rechazada(): void
    {
        Livewire::actingAs($this->admin())->test(Formulario::class)
            ->call('abrirAlta')
            ->set('nuevo.tipo_documento', 'cedula')
            ->set('nuevo.numero_documento', '1712345678')
            ->call('guardarAlta')
            ->assertHasErrors(['nuevo.numero_documento']);
    }

    public function test_pdf_y_etiqueta_qr(): void
    {
        $orden = OrdenTrabajo::first();
        $this->actingAs($this->admin());

        $this->get(route('ordenes.edit', $orden))->assertOk()->assertSee($orden->codigo);
        $pdf = $this->get(route('ordenes.pdf', $orden))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $this->get(route('vehiculos.etiqueta', $orden->vehiculo_id))
            ->assertOk()->assertSee('<svg', false)->assertSee($orden->vehiculo->placa_formateada);
    }
}
