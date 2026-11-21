<?php

// Solo se sobrescribe lo necesario; el resto se fusiona con la config por defecto de Livewire.
return [
    // Livewire 4 usa 'layouts::app' por defecto; el starter kit tiene su layout en components/layouts/app.
    'component_layout' => 'components.layouts.app',

    // Barra de progreso de wire:navigate con el acento de la paleta ERP.
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#1E40AF',
    ],
];
