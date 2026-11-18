{{-- Tab laporan pemilik (§11: penjualan, kas & laba-rugi, persediaan, kinerja outlet, harian outlet). --}}
@props([
    'current',
    'query' => [],
])

@php
    $tabs = [
        'owner.reports.sales' => 'Penjualan',
        'owner.reports.cash' => 'Kas & laba-rugi',
        'owner.reports.inventory' => 'Persediaan',
        'owner.reports.performance' => 'Kinerja outlet',
        'owner.reports.daily' => 'Harian outlet',
    ];
    $query = array_filter($query, fn ($value) => filled($value));
@endphp

<nav class="no-print -mx-1 overflow-x-auto border-b border-border" aria-label="Jenis laporan">
    <ul class="flex min-w-max gap-6 px-1">
        @foreach ($tabs as $route => $label)
            <li>
                <a
                    href="{{ route($route, $query) }}"
                    wire:navigate
                    @if ($current === $route) aria-current="page" @endif
                    @class([
                        '-mb-px flex h-12 items-center border-b-2 text-sm',
                        'border-primary font-semibold text-text' => $current === $route,
                        'border-transparent text-muted hover:text-text' => $current !== $route,
                    ])
                >{{ $label }}</a>
            </li>
        @endforeach
    </ul>
</nav>
