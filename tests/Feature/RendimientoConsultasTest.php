<?php

namespace Tests\Feature;

use App\Livewire\Ordenes\Formulario;
use App\Livewire\Publico\Inicio;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Modelo;
use App\Models\OrdenTrabajo;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Blindaje de rendimiento: si alguien reintroduce un N+1, quita un eager loading o vuelve
 * a consultar catálogos en cada render, estos tests fallan.
 *
 * Cada medición simula un request "en frío" de php-fpm (se limpian once() y las instancias
 * scoped) con la caché de catálogos ya caliente. En tests la caché es `array` (0 consultas);
 * con CACHE_STORE=database cada pantalla suma 1 lectura de caché.
 */
class RendimientoConsultasTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_CONSULTAS_LISTADO = 10;

    /** @var list<string> */
    private array $sql = [];

    private bool $escuchando = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('cedula', '1710000009')->first();
    }

    /** Ejecuta $fn como un request nuevo y devuelve el SQL ejecutado. */
    private function consultas(callable $fn): array
    {
        if (! $this->escuchando) {
            DB::listen(fn ($q) => $this->sql[] = $q->sql);
            $this->escuchando = true;
        }
        Once::flush();
        $this->app->forgetScopedInstances();
        $this->sql = [];
        $fn();

        return $this->sql;
    }

    private function get200(string $url): array
    {
        $admin = $this->admin(); // fuera del conteo
        $this->app['auth']->forgetGuards();

        return $this->consultas(fn () => $this->actingAs($admin)->get($url)->assertOk());
    }

    /** Agrega $n clientes, cada uno con vehículo, OT entregada (con 2 ítems) y cita. */
    private function masDatos(int $n): void
    {
        $sede = Sede::first();
        $asesor = Empleado::where('cargo', 'asesor')->first();
        $tecnico = Empleado::tecnicosActivos()->first();
        $modelos = Modelo::pluck('id');

        DB::transaction(function () use ($n, $sede, $asesor, $tecnico, $modelos) {
            for ($i = 0; $i < $n; $i++) {
                $c = Cliente::create(['tipo_documento' => 'pasaporte', 'numero_documento' => 'PERF'.$i, 'nombres' => 'Cliente carga '.$i, 'telefono' => '0990000000']);
                $v = Vehiculo::create(['placa' => sprintf('ZZZ%04d', $i), 'cliente_id' => $c->id, 'modelo_id' => $modelos[$i % $modelos->count()], 'anio' => 2020, 'color' => 'Azul']);
                $o = OrdenTrabajo::create([
                    'sede_id' => $sede->id, 'vehiculo_id' => $v->id, 'cliente_id' => $c->id, 'asesor_id' => $asesor->id, 'tecnico_id' => $tecnico->id,
                    'fecha_ingreso' => now()->subDays($i), 'kilometraje_ingreso' => 1000 + $i, 'estado' => 'recibido',
                    'subtotal' => 0, 'descuento' => 0, 'iva' => 0, 'total' => 0,
                ]);
                $o->items()->createMany([
                    ['tipo' => 'libre', 'descripcion' => 'Ítem A', 'cantidad' => 1, 'precio_unitario' => 1, 'subtotal' => 1],
                    ['tipo' => 'libre', 'descripcion' => 'Ítem B', 'cantidad' => 1, 'precio_unitario' => 1, 'subtotal' => 1],
                ]);
                Cita::create([
                    'sede_id' => $sede->id, 'vehiculo_id' => $v->id, 'placa' => $v->placa, 'cliente_nombre' => 'X', 'cliente_telefono' => '0990000000',
                    'fecha_deseada' => now()->addDay(), 'hora_inicio' => '09:00', 'motivo' => 'x', 'origen' => 'recepcion', 'estado' => 'pendiente',
                ]);
            }
        });
    }

    // ───────────── Listados del panel ─────────────

    public function test_listados_del_panel_sin_n_mas_1_y_con_tope_de_consultas(): void
    {
        $urls = [
            'ordenes' => '/panel/ordenes', 'vehiculos' => '/panel/vehiculos', 'clientes' => '/panel/clientes',
            'citas' => '/panel/citas', 'empleados' => '/panel/empleados', 'convenios' => '/panel/empresas-colaboradoras',
        ];

        $this->get200('/panel/ordenes'); // calienta la caché de catálogos
        $antes = array_map(fn ($u) => count($this->get200($u)), $urls);

        $this->masDatos(25);
        $despues = array_map(fn ($u) => count($this->get200($u)), $urls);

        foreach ($urls as $k => $u) {
            $this->assertSame($antes[$k], $despues[$k], "N+1 en {$k}: {$antes[$k]} consultas con pocos datos, {$despues[$k]} con +25 registros.");
            $this->assertLessThanOrEqual(self::MAX_CONSULTAS_LISTADO, $despues[$k], "{$k} hace {$despues[$k]} consultas por carga.");
        }
    }

    public function test_listado_de_ot_no_trae_columnas_pesadas(): void
    {
        $sql = collect($this->get200('/panel/ordenes'));

        $this->assertFalse($sql->contains(fn ($s) => str_contains($s, 'from "ordenes_trabajo"') && str_contains($s, 'select *')),
            'El listado de OT volvió a hacer select * (trae checklist JSON, diagnóstico, observaciones).');
    }

    // ───────────── Ficha del vehículo ─────────────

    public function test_ficha_del_vehiculo_no_crece_con_el_historial(): void
    {
        $veh = Vehiculo::where('placa', 'PBA4521')->first();
        $this->get200("/panel/vehiculos/{$veh->id}");
        $antes = count($this->get200("/panel/vehiculos/{$veh->id}"));

        // 15 OT entregadas más para el mismo vehículo, con ítems.
        $base = OrdenTrabajo::where('vehiculo_id', $veh->id)->first();
        DB::transaction(function () use ($base) {
            for ($i = 0; $i < 15; $i++) {
                $o = OrdenTrabajo::create(collect($base->getAttributes())->except(['id', 'codigo', 'created_at', 'updated_at'])
                    ->merge(['estado' => 'entregado', 'fecha_ingreso' => now()->subYears(2)->addDays($i), 'checklist' => []])->all());
                $o->items()->create(['tipo' => 'libre', 'descripcion' => 'Histórico '.$i, 'cantidad' => 1, 'precio_unitario' => 1, 'subtotal' => 1]);
            }
        });

        $despues = count($this->get200("/panel/vehiculos/{$veh->id}"));

        $this->assertSame($antes, $despues, "N+1 en la ficha: {$antes} → {$despues} consultas.");
        $this->assertLessThanOrEqual(self::MAX_CONSULTAS_LISTADO, $despues);
    }

    // ───────────── Portal público ─────────────

    public function test_portal_publico_resuelve_el_vehiculo_en_una_sola_consulta(): void
    {
        $veh = Vehiculo::where('placa', 'PBA4521')->first();
        $this->get("/q/{$veh->qr_token}")->assertOk(); // calienta catálogos

        $sql = collect($this->consultas(fn () => $this->get("/q/{$veh->qr_token}")->assertOk()));

        // Vehículo + modelo + marca + propietario: una sola consulta (JOIN), ninguna suelta.
        $this->assertCount(1, $sql->filter(fn ($s) => str_contains($s, 'from "vehiculos"')));
        $this->assertTrue($sql->contains(fn ($s) => str_contains($s, 'inner join "marcas"') && str_contains($s, 'inner join "clientes"')));
        foreach (['modelos', 'marcas', 'clientes', 'servicios', 'sedes', 'empresa'] as $tabla) {
            $this->assertFalse($sql->contains(fn ($s) => str_starts_with($s, 'select') && str_contains($s, "from \"{$tabla}\"")),
                "El portal volvió a consultar {$tabla} por separado.");
        }
        // vehículo (1) + OTs del vehículo (1) + ítems del historial (1)
        $this->assertLessThanOrEqual(3, $sql->count(), $sql->implode("\n"));
    }

    public function test_consulta_publica_por_placa_hace_una_sola_consulta(): void
    {
        $sql = $this->consultas(fn () => Livewire::test(Inicio::class)
            ->set('valor', 'pba-4521')->call('consultar')->assertRedirect());

        $this->assertCount(1, $sql, implode("\n", $sql));
        $this->assertStringContainsString('"qr_token" from "vehiculos"', $sql[0]);
    }

    // ───────────── Formulario de OT (roundtrips de Livewire) ─────────────

    public function test_tecleo_en_el_formulario_de_ot_no_reconsulta_catalogos(): void
    {
        $lw = Livewire::actingAs($this->admin())->test(Formulario::class);

        $sql = collect($this->consultas(fn () => $lw->set('buscarCliente', 'Ju')));

        // Se permite el eager loading de los resultados (… where "marcas"."id" in (…)), no el catálogo completo.
        foreach (['servicios', 'sedes', 'empresas_colaboradoras', 'marcas', 'checklist_items', 'empresa'] as $tabla) {
            $this->assertFalse(
                $sql->contains(fn ($s) => str_contains($s, "from \"{$tabla}\"") && ! str_contains($s, "\"{$tabla}\".\"id\" in")),
                "Un tecleo volvió a consultar el catálogo {$tabla}."
            );
        }
        $this->assertFalse($sql->contains(fn ($s) => str_contains($s, '"cargo" = ?')), 'Un tecleo volvió a consultar los técnicos.');
    }

    public function test_editar_ot_no_supera_el_tope(): void
    {
        $ot = OrdenTrabajo::first();
        $this->get200("/panel/ordenes/{$ot->id}/editar");

        $this->assertLessThanOrEqual(12, count($this->get200("/panel/ordenes/{$ot->id}/editar")));
    }

    // ───────────── Caché de catálogos: nunca desactualizada ─────────────

    public function test_la_cache_de_catalogos_se_invalida_al_crear_editar_y_desactivar(): void
    {
        Catalogos::servicios(); // caliente

        $s = Servicio::create(['nombre' => 'Servicio recién creado', 'precio_referencial' => 10]);
        $this->assertTrue(Catalogos::servicios()->contains('nombre', 'Servicio recién creado'));

        $s->update(['nombre' => 'Servicio renombrado']);
        $this->assertTrue(Catalogos::servicios()->contains('nombre', 'Servicio renombrado'));

        $s->update(['activo' => false]);
        $this->assertFalse(Catalogos::servicios()->contains('id', $s->id));

        Modelo::resolver('Marca Nueva', 'Modelo X');
        $this->assertTrue(Catalogos::marcas()->contains('Marca Nueva'));
        $this->assertSame(['Modelo X'], Catalogos::modelosDe('marca nueva')->all());

        Empresa::first()->update(['razon_social' => 'Taller Renombrado']);
        $this->assertSame('Taller Renombrado', Empresa::actual()->razon_social);

        $sede = Sede::first();
        $sede->update(['activa' => false]);
        $this->assertFalse(Catalogos::sedesActivas()->contains('id', $sede->id));
        $this->assertTrue(Catalogos::sedes()->contains('id', $sede->id));
    }

    public function test_la_cache_tambien_se_invalida_dentro_de_transacciones_y_en_requests_siguientes(): void
    {
        $this->get200('/panel/vehiculos/nuevo'); // caliente

        DB::transaction(fn () => Modelo::resolver('Geely', 'Coolray'));

        // Request nuevo (memo limpio): la caché compartida ya no tiene la versión vieja.
        $this->consultas(fn () => null);
        $this->assertTrue(Catalogos::marcas()->contains('Geely'));
        $this->actingAs($this->admin())->get('/panel/vehiculos/nuevo')->assertSee('<option value="Geely">', false);
    }

    // ───────────── Parámetros en config/taller.php ─────────────

    public function test_parametros_operativos_salen_de_config(): void
    {
        $this->assertSame(['08:00', '09:00', '10:00', '11:00', '12:00', '13:00'], Cita::franjas());
        $this->assertSame(5000, (new Vehiculo)->intervalo_mantenimiento_km);

        config(['taller.citas.hora_apertura' => 9, 'taller.citas.hora_cierre' => 12, 'taller.mantenimiento.intervalo_km' => 7000]);

        $this->assertSame(['09:00', '10:00', '11:00'], Cita::franjas());
        $this->assertSame(7000, (new Vehiculo)->intervalo_mantenimiento_km);
        $this->assertSame(7000, Vehiculo::create([
            'placa' => 'PAA-1111', 'cliente_id' => Cliente::first()->id, 'modelo_id' => Modelo::first()->id, 'anio' => 2020, 'color' => 'Negro',
        ])->fresh()->intervalo_mantenimiento_km);
    }

    public function test_iva_viene_de_empresa_y_el_respaldo_de_config(): void
    {
        Empresa::actual(); // caliente
        Empresa::first()->update(['iva_porcentaje' => 12]);
        $this->assertSame(12.0, Empresa::ivaPorcentaje()); // el cambio se ve sin esperar al TTL

        // Respaldo cuando aún no existe la fila de empresa (no se borra aquí: sedes/empleados dependen de ella).
        $this->assertSame(15.0, config('taller.iva_por_defecto'));
    }
}
