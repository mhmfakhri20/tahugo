{{-- Error inline tahugo.: ikon (!) + teks danger. --}}
<div {{ $attributes->class('flex items-start gap-2 text-danger') }} role="alert">
    <flux:icon name="exclamation-circle" class="mt-0.5 size-5 shrink-0" />
    <span>{{ $slot }}</span>
</div>
