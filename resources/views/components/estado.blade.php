@props(['estado', 'labels' => \App\Models\OrdenTrabajo::ESTADOS])
<span {{ $attributes->merge(['class' => 'badge st-'.$estado]) }}>{{ $labels[$estado] ?? ucfirst(str_replace('_', ' ', $estado)) }}</span>
