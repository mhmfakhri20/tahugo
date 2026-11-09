{{--
    Kartu metrik tahugo.: label muted, nilai Display bold, ikon dalam kotak soft (kanan atas),
    tren opsional (↗ success / ↘ danger) atau hint muted.
--}}
@props([
    'label',
    'value',
    'icon' => null,
    'trend' => null,
    'trendDirection' => 'up',
    'hint' => null,
])

<div {{ $attributes->class('flex flex-col gap-3 rounded-xl border border-border bg-surface p-5') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="text-sm text-muted">{{ $label }}</div>
        @if ($icon)
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
                <flux:icon :name="$icon" class="size-5 text-brown" />
            </div>
        @endif
    </div>

    <div class="text-[26px] leading-tight font-bold text-text lg:text-3xl">{{ $value }}</div>

    @if ($trend)
        <div @class([
            'flex items-center gap-1.5 text-sm font-medium',
            'text-success' => $trendDirection === 'up',
            'text-danger' => $trendDirection === 'down',
        ])>
            <flux:icon :name="$trendDirection === 'down' ? 'arrow-trending-down' : 'arrow-trending-up'" class="size-4" />
            <span>{{ $trend }}</span>
        </div>
    @elseif ($hint)
        <div class="text-sm text-muted">{{ $hint }}</div>
    @endif
</div>
