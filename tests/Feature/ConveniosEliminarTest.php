<?php

namespace Tests\Feature;

use App\Livewire\Clientes\Listado as ClientesListado;
use App\Livewire\Convenios\Listado;
use App\Models\Cliente;
use App\Models\EmpresaColaboradora;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Support\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Empresas colaboradoras: sin "activo", se eliminan con soft delete. */
class ConveniosEliminarTest extends TestCase
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

    public function test_la_pantalla_ya_no_muestra_estado_ni_campo_activo(): void
    {
        $this->actingAs($this->admin())->get('/panel/empresas-colaboradoras')
            ->assertOk()->assertDontSee('<th>Estado</th>', false)->assertDontSee('Convenio activo')->assertSee('Eliminar');
        $this->assertFalse(Schema::hasColumn('empresas_colaboradoras', 'activo'));
    }

    public function test_eliminar_pide_confirmacion_y_deja_a_los_clientes_en_no_aplica_sin_tocar_las_ot(): void
    {
        $convenio = EmpresaColaboradora::where('porcentaje_descuento', 10)->firstOrFail();
        $clientes = Cliente::where('empresa_colaboradora_id', $convenio->id)->pluck('id');
        $this->assertNotEmpty($clientes);
        $totales = OrdenTrabajo::whereIn('cliente_id', $clientes)->pluck('total', 'id')->all();

        Livewire::actingAs($this->admin())->test(Listado::class)
            ->call('confirmarEliminar', $convenio->id)
            ->assertSee("Se eliminará el convenio. {$clientes->count()} ".($clientes->count() === 1 ? 'cliente quedará' : 'clientes quedarán')." en 'No aplica'. Las órdenes ya emitidas no cambian.", false)
            ->call('eliminar')
            ->assertSet('eliminandoId', null)
            ->assertDispatched('toast')
            ->assertDontSee($convenio->nombre);

        $this->assertSoftDeleted($convenio);
        $this->assertSame(0, Cliente::where('empresa_colaboradora_id', $convenio->id)->count());
        $this->assertSame(0.0, Cliente::find($clientes->first())->porcentajeDescuento());
        $this->assertSame($totales, OrdenTrabajo::whereIn('cliente_id', $clientes)->pluck('total', 'id')->all());
        $this->assertFalse(Catalogos::convenios()->contains('id', $convenio->id));
    }

    public function test_cancelar_no_elimina(): void
    {
        $convenio = EmpresaColaboradora::firstOrFail();

        Livewire::actingAs($this->admin())->test(Listado::class)
            ->call('confirmarEliminar', $convenio->id)->set('eliminandoId', null);

        $this->assertNotSoftDeleted($convenio);
    }

    public function test_no_se_puede_asignar_un_convenio_eliminado_a_un_cliente(): void
    {
        $convenio = EmpresaColaboradora::firstOrFail();
        $convenio->delete();
        $cliente = Cliente::whereNull('empresa_colaboradora_id')->where('tipo_documento', 'cedula')->firstOrFail();

        Livewire::actingAs($this->admin())->test(ClientesListado::class)
            ->call('editar', $cliente->id)
            ->set('empresa_colaboradora_id', (string) $convenio->id)
            ->call('guardar')
            ->assertHasErrors('empresa_colaboradora_id');
    }

    public function test_el_ruc_de_un_convenio_eliminado_avisa_que_se_restaura(): void
    {
        $convenio = EmpresaColaboradora::whereNotNull('ruc')->firstOrFail();
        $convenio->delete();

        Livewire::actingAs($this->admin())->test(Listado::class)
            ->call('nuevo')->set('nombre', 'Otra')->set('ruc', $convenio->ruc)->set('porcentaje_descuento', '5')
            ->call('guardar')
            ->assertHasErrors(['ruc' => 'unique']);
    }

    /** La migración convierte "inactivo" en eliminado y deja a sus clientes en "No aplica". */
    public function test_la_migracion_convierte_inactivos_en_eliminados(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('En MySQL el DDL hace commit implícito y rompe la transacción de RefreshDatabase.');
        }

        $migracion = require database_path('migrations/2026_11_23_000001_soft_delete_empresas_colaboradoras.php');
        $migracion->down();

        $activa = DB::table('empresas_colaboradoras')->where('porcentaje_descuento', 10)->value('id');
        $inactiva = DB::table('empresas_colaboradoras')->where('porcentaje_descuento', 15)->value('id');
        DB::table('empresas_colaboradoras')->where('id', $inactiva)->update(['activo' => false]);
        $deLaInactiva = DB::table('clientes')->where('empresa_colaboradora_id', $inactiva)->pluck('id');
        $this->assertNotEmpty($deLaInactiva);

        $migracion->up();

        $this->assertFalse(Schema::hasColumn('empresas_colaboradoras', 'activo'));
        $this->assertNotNull(DB::table('empresas_colaboradoras')->where('id', $inactiva)->value('deleted_at'));
        $this->assertNull(DB::table('empresas_colaboradoras')->where('id', $activa)->value('deleted_at'));
        $this->assertSame(0, DB::table('clientes')->whereIn('id', $deLaInactiva)->whereNotNull('empresa_colaboradora_id')->count());
        $this->assertGreaterThan(0, DB::table('clientes')->where('empresa_colaboradora_id', $activa)->count());
    }
}
