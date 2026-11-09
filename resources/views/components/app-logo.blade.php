{{-- Logo + wordmark "tahugo.". Pakai `size="lg"` untuk header halaman login. --}}
@props([
    'size' => 'md',
    'href' => null,
])

@php
    $iconClass = $size === 'lg' ? 'size-10' : 'size-8';
    $textClass = $size === 'lg' ? 'text-2xl' : 'text-xl';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class('inline-flex items-center gap-2.5 text-text') }}>
    <x-app-logo-icon class="{{ $iconClass }} shrink-0" />
    <span class="{{ $textClass }} font-bold tracking-tight">tahugo.</span>
</{{ $tag }}>
