@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1 text-center">
    <h1 class="page-title">{{ $title }}</h1>
    <p class="page-sub">{{ $description }}</p>
</div>
