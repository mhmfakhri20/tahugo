{{-- Judul beranda: "Selamat pagi/siang/sore/malam, {nama}" menurut jam Asia/Jakarta. --}}
@props([
    'name',
    'description' => null,
])

@php
    $hour = now()->hour;
    $salam = match (true) {
        $hour >= 4 && $hour < 11 => 'pagi',
        $hour >= 11 && $hour < 15 => 'siang',
        $hour >= 15 && $hour < 18 => 'sore',
        default => 'malam',
    };
@endphp

<div {{ $attributes }}>
    <h1 class="text-[26px] leading-tight font-bold text-text lg:text-3xl">Selamat {{ $salam }}, {{ $name }}</h1>
    @if ($description)
        <p class="mt-1 text-base text-muted">{{ $description }}</p>
    @endif
</div>
