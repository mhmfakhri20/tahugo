{{-- Empty state tahugo.: kotak canvas, ikon brown dalam kotak soft, judul, deskripsi, tombol opsional (slot). --}}
@props([
    'icon' => 'cube',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center gap-3 rounded-xl bg-canvas px-6 py-10 text-center') }}>
    <div class="flex size-12 items-center justify-center rounded-lg bg-soft">
        <flux:icon :name="$icon" class="size-6 text-brown" />
    </div>
    <div class="font-semibold text-text">{{ $title }}</div>
    @if ($description)
        <p class="max-w-sm text-sm text-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-1">{{ $slot }}</div>
    @endif
</div>
