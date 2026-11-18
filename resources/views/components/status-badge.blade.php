{{--
    Badge pill tahugo.: titik + teks (status tidak boleh hanya warna).
    variant: success (Aman), warning (Stok menipis), danger (Habis), info (Dikirim), muted (Menunggu), soft (label netral kuning).
--}}
@props([
    'variant' => 'muted',
])

@php
    $classes = match ($variant) {
        'success' => 'bg-success/10 text-success',
        'warning' => 'bg-soft text-brown',
        'danger' => 'bg-danger/10 text-danger',
        'info' => 'bg-info/10 text-info',
        'soft' => 'bg-soft text-brown',
        default => 'bg-border text-muted',
    };
    $dot = match ($variant) {
        'success' => 'bg-success',
        'warning' => 'bg-warning',
        'danger' => 'bg-danger',
        'info' => 'bg-info',
        'soft' => 'bg-brown',
        default => 'bg-muted',
    };
@endphp

{{-- shrink-0 + nowrap: badge tidak terpotong/terlipat di samping teks panjang pada layar kecil. --}}
<span {{ $attributes->class(['inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-sm font-medium whitespace-nowrap', $classes]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $slot }}
</span>
