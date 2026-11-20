<?php

// Módulo Inventario — lo construye el otro programador. No tocar desde otros módulos.
//
// Punto de partida:
//  - Modelos: app/Models/Inventario/{CategoriaProducto, Proveedor, Producto, Inventario, MovimientoInventario}
//  - Componentes Livewire: app/Livewire/Inventario/ (vacío)
//  - Vistas: resources/views/livewire/inventario/ (vacío)
//  - Integración con OT: líneas ot_items.tipo = 'producto' (ver TODO INVENTARIO en
//    app/Livewire/Ordenes/Formulario.php) + movimientos_inventario.orden_id para las salidas.
//  - Menú: activar el ítem "Inventario" en resources/views/components/layouts/panel.blade.php.
