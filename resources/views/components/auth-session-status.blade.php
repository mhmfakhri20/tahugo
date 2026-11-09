@props([
    'status',
])

@if ($status)
    <x-toast title="Berhasil" :class="$attributes->get('class')">{{ $status }}</x-toast>
@endif
