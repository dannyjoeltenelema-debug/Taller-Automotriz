@php
    $empresa = \App\Models\Empresa::actual();
    $user = auth()->user();
    $nav = [
        'Operación' => [
            ['ordenes.*', 'ordenes.index', 'Órdenes de trabajo'],
            ['citas.*', 'citas.index', 'Citas / Solicitudes'],
            ['vehiculos.*', 'vehiculos.index', 'Vehículos'],
            ['clientes.*', 'clientes.index', 'Clientes'],
        ],
        'Administración' => [
            ['convenios.*', 'convenios.index', 'Empresas colaboradoras'],
            ['empleados.*', 'empleados.index', 'Empleados'],
            ['catalogos.*', 'catalogos.index', 'Catálogos'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Panel' }} · {{ $empresa?->razon_social ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui min-h-screen" x-data="{ menu: false }">
    <header class="topbar no-print sticky top-0 z-20 flex items-center justify-between px-3">
        <div class="flex items-center gap-3">
            <button class="btn btn-sm md:hidden" @click="menu = !menu" aria-label="Menú">☰</button>
            <a href="{{ route('ordenes.index') }}" wire:navigate class="font-semibold text-[15px]">
                {{ $empresa?->razon_social ?? config('app.name') }}
            </a>
            <span class="muted hidden sm:inline">| Sistema de gestión de taller</span>
        </div>
        <div class="flex items-center gap-3" x-data="{ open: false }" @click.outside="open = false">
            <a href="{{ route('home') }}" class="link hidden sm:inline text-[12.5px]">Portal público</a>
            <div class="relative">
                <button class="btn btn-sm" @click="open = !open">
                    {{ $user->name }}
                    <span class="muted">({{ $user->getRoleNames()->first() ?? 'sin rol' }})</span> ▾
                </button>
                <div x-show="open" x-cloak class="panel absolute right-0 mt-1 w-48 z-30">
                    <a href="{{ route('settings.profile') }}" class="nav-link">Mi perfil / contraseña</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-link w-full text-left">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="flex">
        <aside class="sidebar no-print w-56 shrink-0 min-h-[calc(100vh-48px)] fixed md:sticky top-12 z-10 md:block"
               :class="menu ? 'block' : 'hidden'">
            <nav class="py-2">
                @foreach ($nav as $grupo => $items)
                    <div class="nav-title">{{ $grupo }}</div>
                    @foreach ($items as [$patron, $ruta, $texto])
                        <a href="{{ route($ruta) }}" wire:navigate @class(['nav-link', 'active' => request()->routeIs($patron)])>{{ $texto }}</a>
                    @endforeach
                @endforeach

                @role('superadmin')
                    <a href="{{ route('configuracion.empresa') }}" wire:navigate @class(['nav-link', 'active' => request()->routeIs('configuracion.*')])>Datos del taller</a>
                @endrole

                <div class="nav-title">Bodega</div>
                {{-- Módulo Inventario: lo construye el otro programador (routes/inventario.php). --}}
                <span class="nav-link disabled" title="Módulo en construcción">Inventario <span class="tag-soon">Próximamente</span></span>
            </nav>
        </aside>

        <main class="flex-1 min-w-0 px-4 py-4">
            <div class="mx-auto max-w-[1200px]">
                {{ $slot }}
            </div>
        </main>
    </div>
    @include('partials.toasts')
    <style>[x-cloak]{display:none!important}</style>
</body>
</html>
