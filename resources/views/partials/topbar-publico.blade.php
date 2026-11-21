{{-- Encabezado público (wordmark del taller). Compartido por el home y las páginas de acceso. --}}
@php($empresa = \App\Models\Empresa::actual())
<header class="topbar flex items-center justify-between px-4">
    <a href="{{ route('home') }}" wire:navigate class="font-semibold text-[15px] truncate">{{ $empresa?->razon_social ?? config('app.name') }}</a>
    @if ($conAcceso ?? true)
        <a href="{{ route('login') }}" wire:navigate class="btn btn-sm">Acceso Personal</a>
    @endif
</header>
