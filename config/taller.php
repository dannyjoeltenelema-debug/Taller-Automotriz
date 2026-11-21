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
        // Franjas de 1 hora desde la apertura hasta el cierre: 8 → 14 = 08:00–09:00 … 13:00–14:00.
        'hora_apertura' => (int) env('TALLER_CITAS_HORA_APERTURA', 8),
        'hora_cierre' => (int) env('TALLER_CITAS_HORA_CIERRE', 14),
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

    'cache' => [
        // Red de seguridad: los catálogos se invalidan al guardar/eliminar, pero
        // además expiran tras este tiempo (cubre updates masivos sin eventos).
        'ttl_seg' => (int) env('TALLER_CACHE_TTL', 86400),
    ],

];
