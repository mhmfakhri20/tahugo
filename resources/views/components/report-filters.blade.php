{{--
    Filter laporan: periode (WIB) + lokasi. Dipakai komponen yang memakai trait WithReportFilters
    (properti `from`, `to`, `outlet`, method `setRange()`).
    options: koleksi lokasi untuk pilihan (null = tanpa pilihan lokasi).
--}}
@props([
    'options' => null,
    'label' => 'Cabang',
    'allLabel' => 'Semua cabang',
])

<section {{ $attributes->class('no-print flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 lg:flex-row lg:items-end') }}>
    <div @class(['grid flex-1 grid-cols-1 gap-3', 'sm:grid-cols-3' => $options, 'sm:grid-cols-2' => ! $options])>
        <flux:input wire:model.live="from" type="date" label="Dari tanggal" :max="now()->toDateString()" />
        <flux:input wire:model.live="to" type="date" label="Sampai tanggal" :max="now()->toDateString()" />
        @if ($options)
            <flux:select wire:model.live="outlet" :label="$label">
                <flux:select.option value="">{{ $allLabel }}</flux:select.option>
                @foreach ($options as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach (['today' => 'Hari ini', '7' => '7 hari', '30' => '30 hari', 'month' => 'Bulan ini'] as $key => $text)
            <flux:button size="sm" wire:click="setRange('{{ $key }}')">{{ $text }}</flux:button>
        @endforeach
    </div>
</section>
