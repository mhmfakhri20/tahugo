{{-- Judul halaman (Display) + deskripsi muted + slot aksi di kanan (bertumpuk di layar kecil). --}}
@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        <h1 class="text-[26px] leading-tight font-bold text-text lg:text-3xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-base text-muted">{{ $description }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
