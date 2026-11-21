{{-- Placeholder de componentes #[Lazy]: skeleton gris neutro, sin brillo. --}}
<div aria-busy="true">
    <div class="mb-3">
        <h1 class="page-title">{{ $titulo }}</h1>
        <div class="skel mt-1" style="width: 260px"></div>
    </div>
    <div class="panel p-3 space-y-2">
        @for ($i = 0; $i < 6; $i++)<div class="skel skel-pulso" style="width: {{ 92 - $i * 8 }}%"></div>@endfor
    </div>
</div>
