{{--
    Toast/alert tahugo.: latar status/10, ikon dalam lingkaran, judul semibold berwarna status,
    detail (slot) teks utama, tombol tutup ✕. variant: success (bawaan) | danger. size: md | lg (outlet, 16px).
--}}
@props([
    'title',
    'variant' => 'success',
    'dismissible' => true,
    'size' => 'md',
])

@php
    $isDanger = $variant === 'danger';
@endphp

<div
    @if ($dismissible) x-data="{ open: true }" x-show="open" @endif
    role="{{ $isDanger ? 'alert' : 'status' }}"
    {{ $attributes->class([
        'flex items-start gap-3 rounded-xl p-4',
        'bg-danger/10' => $isDanger,
        'bg-success/10' => ! $isDanger,
    ]) }}
>
    <flux:icon
        :name="$isDanger ? 'exclamation-circle' : 'check-circle'"
        :class="'mt-0.5 size-6 shrink-0 '.($isDanger ? 'text-danger' : 'text-success')"
    />

    <div class="min-w-0 flex-1">
        <div @class(['font-semibold', 'text-danger' => $isDanger, 'text-success' => ! $isDanger])>{{ $title }}</div>
        @if ($slot->isNotEmpty())
            <div @class(['mt-0.5 text-text', 'text-base' => $size === 'lg', 'text-sm' => $size !== 'lg'])>{{ $slot }}</div>
        @endif
    </div>

    @if ($dismissible)
        <button
            type="button"
            x-on:click="open = false"
            aria-label="Tutup"
            class="-me-2 -mt-2 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:text-text"
        >
            <flux:icon name="x-mark" class="size-5" />
        </button>
    @endif
</div>
