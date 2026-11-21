@php($empresa = \App\Models\Empresa::actual())
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Consulta de vehículo' }} · {{ $empresa?->razon_social ?? config('app.name') }}</title>
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui min-h-screen flex flex-col">
    @include('partials.topbar-publico')

    <main class="flex-1 w-full max-w-3xl mx-auto px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="border-t border-[var(--c-border)] bg-white text-center muted text-[12px] py-3 px-4">
        {{ $empresa?->razon_social }} · RUC {{ $empresa?->ruc }} · {{ $empresa?->direccion }}
    </footer>
</body>
</html>
