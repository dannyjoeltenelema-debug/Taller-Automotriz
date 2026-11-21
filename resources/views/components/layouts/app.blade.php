{{--
 | Layout por defecto de Livewire (config/livewire.php → component_layout): lo usan las pantallas
 | de "Mi perfil" (settings/*, Volt + Flux). Se monta dentro del layout del panel para compartir el
 | marco persistente y la navegación sin recargas. Flux solo se usa dentro de estas pantallas.
--}}
<x-layouts.panel :title="$title ?? null">
    {{ $slot }}
    @fluxScripts
</x-layouts.panel>
