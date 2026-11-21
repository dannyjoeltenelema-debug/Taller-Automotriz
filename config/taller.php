<?php

/*
|--------------------------------------------------------------------------
| Parámetros operativos del taller
|--------------------------------------------------------------------------
| Valores que antes estaban quemados en el código. Todos tienen un default
| igual al comportamiento original; se pueden ajustar por .env sin tocar código.
| El IVA vigente NO vive aquí: se toma de empresa.iva_porcentaje (Datos del
| taller); este archivo solo define el respaldo si aún no existe esa fila.
*/

return [

    'iva_por_defecto' => (float) env('TALLER_IVA_POR_DEFECTO', 15),

    'mantenimiento' => [
        // Intervalo por defecto de un vehículo nuevo y respaldo del "km + intervalo"
        // con el que se sugiere el próximo kilometraje en la OT.
        'intervalo_km' => (int) env('TALLER_INTERVALO_KM', 5000),
    ],

    'citas' => [
        // Franjas desde la apertura hasta el cierre: 8 → 14 con franjas de 60 min = 08:00–09:00 … 13:00–14:00.
        'hora_apertura' => (int) env('TALLER_CITAS_HORA_APERTURA', 8),
        'hora_cierre' => (int) env('TALLER_CITAS_HORA_CIERRE', 14),
        'duracion_franja_min' => (int) env('TALLER_CITAS_FRANJA_MIN', 60),
        // Hasta cuántos días adelante se puede pedir cita desde el portal.
        'dias_anticipacion_max' => (int) env('TALLER_CITAS_DIAS_MAX', 60),
    ],

    'portal' => [
        // Throttle de las rutas públicas (peticiones por minuto e IP).
        'throttle_por_minuto' => (int) env('TALLER_PORTAL_THROTTLE', 30),
        // Consultas por placa/cédula (acción Livewire) por IP.
        'consultas_max' => (int) env('TALLER_PORTAL_CONSULTAS_MAX', 15),
        'consultas_ventana_seg' => (int) env('TALLER_PORTAL_CONSULTAS_VENTANA', 60),
        // Solicitudes de cita por IP.
        'citas_max' => (int) env('TALLER_PORTAL_CITAS_MAX', 3),
        'citas_ventana_seg' => (int) env('TALLER_PORTAL_CITAS_VENTANA', 600),
    ],

    // Tamaño de página de cada listado del panel.
    'paginacion' => [
        'ordenes' => 20,
        'clientes' => 20,
        'citas' => 25,
        'vehiculos' => 25,
        'empleados' => 50,
        'convenios' => 50,
        'auditoria' => 50,
        'usuarios' => 25,
        'logs' => 100,
    ],

    // Código de OT: {prefijo}-{año}-{número con relleno}. Ej.: OT-2026-0001.
    // Cambiar el prefijo o el relleno solo afecta a las OT nuevas.
    'codigo_ot' => [
        'prefijo' => env('TALLER_OT_PREFIJO', 'OT'),
        'relleno' => (int) env('TALLER_OT_RELLENO', 4),
    ],

    'ordenes' => [
        // Cargos de empleado que pueden asignarse como técnico de una OT.
        'cargos_tecnico' => ['tecnico', 'mecanico'],
        // Cargos que pueden registrarse como asesor de una OT (selector para usuarios sin empleado vinculado).
        'cargos_asesor' => ['asesor', 'admin'],

        // Transiciones de estado permitidas. 'entregado' y 'cancelado' son finales:
        // solo el superadmin puede reabrirlos (queda auditado).
        'transiciones' => [
            'recibido' => ['en_proceso', 'cancelado'],
            'en_proceso' => ['listo_entrega', 'recibido', 'cancelado'],
            'listo_entrega' => ['entregado', 'en_proceso'],
            'entregado' => [],
            'cancelado' => [],
        ],
        // A qué estado vuelve una OT final cuando el superadmin la reabre.
        'reabrir_a' => 'en_proceso',
    ],

    'cache' => [
        // Red de seguridad: los catálogos se invalidan al guardar/eliminar, pero
        // además expiran tras este tiempo (cubre updates masivos sin eventos).
        'ttl_seg' => (int) env('TALLER_CACHE_TTL', 86400),
    ],

];
