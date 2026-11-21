@php
    $empresa = \App\Models\Empresa::actual();
    $user = auth()->user();
    $nav = [
        'Operación' => [
            ['ordenes.index', 'Órdenes de trabajo'],
            ['citas.index', 'Citas / Solicitudes'],
            ['vehiculos.index', 'Vehículos'],
            ['clientes.index', 'Clientes'],
        ],
        'Administración' => [
            ['convenios.index', 'Empresas colaboradoras'],
            ['empleados.index', 'Empleados'],
            ['catalogos.index', 'Catálogos'],
        ],
    ];
    $sistema = [
        ['sistema.auditoria', 'Auditoría'],
        ['sistema.usuarios', 'Usuarios y roles'],
        ['sistema.sesiones', 'Sesiones activas'],
        ['sistema.eliminados', 'Registros eliminados'],
        ['sistema.estado', 'Estado del sistema'],
        ['sistema.logs', 'Logs'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Livewire toma el token de aquí en cada petición: tras navegar (p. ej. al cerrar sesión) usa el nuevo. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Panel' }} · {{ $empresa?->razon_social ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui min-h-screen">
    {{--
     | Marco persistente (cabecera + menú): con wire:navigate no se vuelve a renderizar ni pierde su estado.
     | El ítem activo lo marca el componente Alpine "indicadorNav" (resources/js/app.js) al cargar y tras cada navegación.
    --}}
    @persist('marco-panel')
    <div x-data="{ menu: false }" x-on:livewire:navigated.window="menu = false">
        <header class="topbar no-print fixed inset-x-0 top-0 z-20 flex items-center justify-between px-3">
            <div class="flex items-center gap-3">
                <button class="btn btn-sm md:hidden" @click="menu = !menu" aria-label="Menú">☰</button>
                <a href="{{ route('ordenes.index') }}" wire:navigate class="font-semibold text-[15px]">
                    {{ $empresa?->razon_social ?? config('app.name') }}
                </a>
                <span class="muted hidden sm:inline">| Sistema de gestión de taller</span>
            </div>
            <div class="flex items-center gap-3" x-data="{ open: false }" @click.outside="open = false" x-on:livewire:navigated.window="open = false">
                <a href="{{ route('home') }}" wire:navigate class="link hidden sm:inline text-[12.5px]">Portal público</a>
                <div class="relative">
                    <button class="btn btn-sm" @click="open = !open" :aria-expanded="open">
                        {{ $user->name }}
                        <span class="muted">({{ $user->getRoleNames()->first() ?? 'sin rol' }})</span> ▾
                    </button>
                    <div x-show="open" x-cloak
                         x-transition:enter="anim-enter" x-transition:enter-start="anim-from-up"
                         x-transition:leave="anim-leave" x-transition:leave-end="anim-from-up"
                         class="panel absolute right-0 mt-1 w-48 z-30">
                        <a href="{{ route('settings.profile') }}" wire:navigate class="nav-link">Mi perfil / contraseña</a>
                        <button type="button" class="nav-link w-full text-left" x-data="cerrarSesion(@js(route('logout')), @js(route('home')))" @click="salir" :disabled="cerrando">
                            <span x-text="cerrando ? 'Cerrando…' : 'Cerrar sesión'">Cerrar sesión</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <aside class="sidebar no-print w-56 fixed left-0 top-12 bottom-0 overflow-y-auto z-10 md:block" :class="menu ? 'block' : 'hidden'">
            <nav class="py-2 relative con-indicador" x-data="indicadorNav" x-init="$nextTick(() => mover())" x-on:livewire:navigated.window="$nextTick(() => mover())">
                {{-- Indicador del ítem activo: se desliza con transform (la altura no se anima). --}}
                <span class="nav-indicador" aria-hidden="true" :style="estilo"></span>

                @foreach ($nav as $grupo => $items)
                    <div class="nav-title">{{ $grupo }}</div>
                    @foreach ($items as [$ruta, $texto])
                        <a href="{{ route($ruta) }}" wire:navigate class="nav-link">{{ $texto }}</a>
                    @endforeach
                @endforeach

                @role('superadmin')
                    <a href="{{ route('configuracion.empresa') }}" wire:navigate class="nav-link">Datos del taller</a>
                @endrole

                <div class="nav-title">Bodega</div>
                {{-- Módulo Inventario: lo construye el otro programador (routes/inventario.php). --}}
                <span class="nav-link disabled" title="Módulo en construcción">Inventario <span class="tag-soon">Próximamente</span></span>

                @role('superadmin')
                    <div class="nav-title">Sistema</div>
                    @foreach ($sistema as [$ruta, $texto])
                        <a href="{{ route($ruta) }}" wire:navigate class="nav-link">{{ $texto }}</a>
                    @endforeach
                @endrole
            </nav>
        </aside>
    </div>
    @endpersist

    <main class="md:ml-56 pt-12 min-w-0">
        {{-- Contenido: se reemplaza al navegar y entra con un fundido corto. --}}
        <div class="mx-auto max-w-[1200px] px-4 py-4 pagina-entra">
            {{ $slot }}
        </div>
    </main>

    @include('partials.toasts')
</body>
</html>
