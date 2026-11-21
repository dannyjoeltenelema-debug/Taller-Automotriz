@props(['target', 'filas' => 6])
{{--
 | Skeleton gris neutro sobre una tabla mientras Livewire procesa una búsqueda, filtro o página.
 | Va dentro de un contenedor "relative". Estático: sin brillo ni pulso.
--}}
<div wire:loading.delay wire:target="{{ $target }},gotoPage,nextPage,previousPage" class="absolute inset-0 bg-white/60 z-[1] p-3 space-y-2" aria-hidden="true">
    @for ($i = 0; $i < $filas; $i++)<div class="skel" style="width: {{ 92 - $i * 7 }}%"></div>@endfor
</div>
