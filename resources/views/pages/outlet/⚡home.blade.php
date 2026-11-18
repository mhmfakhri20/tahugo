<?php

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Beranda penjaga outlet (§11): kartu besar langkah berikutnya
 * Absen Masuk → Buka Shift → [sedang buka] → Tutup Shift → Absen Pulang, plus riwayat kehadiran.
 */
new #[Title('Beranda')] class extends Component {
    #[Computed]
    public function state(): array
    {
        return app(AttendanceService::class)->todayState(auth()->user());
    }

    /**
     * Own attendance of the last 7 days, one row per day.
     *
     * @return Collection<string, array{in: Attendance|null, out: Attendance|null}>
     */
    #[Computed]
    public function history(): Collection
    {
        return Attendance::query()
            ->where('user_id', auth()->id())
            ->where('server_time', '>=', today()->subDays(6))
            ->orderByDesc('server_time')
            ->get()
            ->groupBy(fn (Attendance $a) => $a->server_time->toDateString())
            ->map(fn (Collection $rows) => [
                'in' => $rows->firstWhere('type', AttendanceType::ClockIn),
                'out' => $rows->firstWhere('type', AttendanceType::ClockOut),
            ]);
    }
}; ?>

@php
    $state = $this->state;
    $step = $state['step'];
    $shift = $state['openShift'];
    $outlet = auth()->user()->location;
    $steps = ['clock_in' => 'Absen masuk', 'open_shift' => 'Buka shift', 'on_duty' => 'Tutup shift', 'clock_out' => 'Absen pulang'];
    $stepIndex = array_search($step, array_keys($steps), true);
    $stepIndex = $stepIndex === false ? count($steps) : $stepIndex;
@endphp

<div class="flex flex-col gap-5">
    <x-greeting :name="auth()->user()->name" />

    @if ($state['staleShift'])
        <x-toast title="Shift lama belum ditutup" variant="danger" size="lg" :dismissible="false">
            Shift yang dibuka {{ $shift->opened_at->translatedFormat('l, d M Y H.i') }} belum ditutup. Tutup shift itu dulu.
        </x-toast>
    @endif

    {{-- Kartu status utama --}}
    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="text-base text-muted">{{ now()->translatedFormat('l, d F Y') }}</div>
                <h2 class="text-xl font-semibold">{{ $outlet->name }}</h2>
            </div>
            @if ($shift)
                <x-status-badge variant="success">Shift buka</x-status-badge>
            @else
                <x-status-badge variant="muted">Shift tutup</x-status-badge>
            @endif
        </div>

        {{-- Progres langkah --}}
        <ol class="grid grid-cols-4 gap-2" aria-label="Langkah hari ini">
            @foreach ($steps as $key => $label)
                @php $done = $loop->index < $stepIndex; $current = $key === $step; @endphp
                <li class="flex flex-col gap-1.5">
                    <span @class(['h-2 rounded-full', 'bg-success' => $done, 'bg-primary' => $current, 'bg-border' => ! $done && ! $current])></span>
                    <span @class(['text-xs leading-tight', 'font-semibold text-text' => $current, 'text-muted' => ! $current])>
                        {{ $done ? '✓ ' : '' }}{{ $label }}
                    </span>
                </li>
            @endforeach
        </ol>

        {{-- Masuk / pulang --}}
        <div class="grid grid-cols-2 gap-3">
            @foreach (['clockIn' => 'Absen masuk', 'clockOut' => 'Absen pulang'] as $key => $label)
                @php $record = $state[$key]; @endphp
                <div @class(['flex flex-col gap-1 rounded-xl p-4', 'bg-success/10' => $record, 'bg-canvas' => ! $record])>
                    <div @class(['text-sm', 'text-success' => $record, 'text-muted' => ! $record])>{{ $label }}</div>
                    <div class="text-2xl font-bold">{{ $record ? $record->server_time->format('H.i') : '—' }}</div>
                    @if ($record)
                        @php $badge = $record->statusBadge(); @endphp
                        <x-status-badge :variant="$badge['variant']">{{ $badge['label'] }}</x-status-badge>
                    @else
                        <div class="text-sm text-muted">Belum tercatat</div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($shift)
            <div class="flex items-center justify-between gap-3 rounded-xl bg-canvas p-4">
                <div>
                    <div class="text-sm text-muted">Shift dibuka {{ $shift->opener->name }}</div>
                    <div class="text-lg font-semibold">pukul {{ $shift->opened_at->format('H.i') }}</div>
                </div>
                <div class="text-end">
                    <div class="text-sm text-muted">Kas awal</div>
                    <div class="text-lg font-semibold">{{ rupiah($shift->opening_cash) }}</div>
                </div>
            </div>
        @endif

        @if ($state['othersPresent']->isNotEmpty())
            <div class="flex items-center gap-2 text-base text-muted">
                <flux:icon name="users" class="size-5 shrink-0 text-brown" />
                Rekan yang hadir: {{ $state['othersPresent']->pluck('name')->implode(', ') }}
            </div>
        @endif

        {{-- Aksi utama (maks 1 per layar) --}}
        @php
            $primary = match ($step) {
                'clock_in' => ['route' => 'outlet.attendance', 'icon' => 'finger-print', 'label' => 'Absen masuk', 'hint' => 'Foto selfie di outlet untuk mulai bekerja.'],
                'open_shift' => ['route' => 'outlet.shift.open', 'icon' => 'lock-open', 'label' => 'Buka shift', 'hint' => 'Isi kas awal (uang kembalian) untuk mulai berjualan.'],
                'on_duty' => ['route' => 'outlet.shift.close', 'icon' => 'lock-closed', 'label' => 'Tutup shift', 'hint' => 'Di akhir hari: hitung uang tunai lalu foto kondisi outlet.'],
                'clock_out' => ['route' => 'outlet.attendance', 'icon' => 'arrow-right-start-on-rectangle', 'label' => 'Absen pulang', 'hint' => 'Shift sudah ditutup. Foto selfie untuk pulang.'],
                default => null,
            };
        @endphp

        @if ($primary)
            <div class="flex flex-col gap-2">
                <a href="{{ route($primary['route']) }}" wire:navigate class="flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B]">
                    <flux:icon :name="$primary['icon']" class="size-6" /> {{ $primary['label'] }}
                </a>
                <p class="text-center text-base text-muted">{{ $primary['hint'] }}</p>
            </div>
        @else
            <div class="flex items-center gap-3 rounded-xl bg-success/10 p-4">
                <flux:icon name="check-circle" class="size-7 shrink-0 text-success" />
                <div>
                    <div class="font-semibold text-success">Selesai untuk hari ini</div>
                    <div class="text-base">Terima kasih atas kerja Anda hari ini.</div>
                </div>
            </div>
        @endif

        @if ($step === 'on_duty')
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('outlet.cashier') }}" wire:navigate class="flex min-h-12 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-3 text-base font-semibold">
                    <flux:icon name="shopping-cart" class="size-5" /> Kasir
                </a>
                <a href="{{ route('outlet.expenses') }}" wire:navigate class="flex min-h-12 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-3 text-base font-semibold">
                    <flux:icon name="banknotes" class="size-5" /> Pengeluaran
                </a>
            </div>
            @if ($state['canClockOut'])
                <flux:button variant="ghost" class="min-h-12" icon="arrow-right-start-on-rectangle" :href="route('outlet.attendance')" wire:navigate>
                    Pulang lebih dulu (rekan lanjut jaga)
                </flux:button>
            @endif
        @endif
    </section>

    {{-- Riwayat --}}
    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold">Riwayat kehadiran Anda</h2>

        @if ($this->history->isEmpty())
            <x-empty-state icon="clock" title="Belum ada riwayat" description="Absen masuk dan pulang Anda 7 hari terakhir akan tampil di sini." size="lg" class="border border-border" />
        @else
            <ul class="flex flex-col divide-y divide-border rounded-xl border border-border bg-surface">
                @foreach ($this->history as $date => $day)
                    <li wire:key="day-{{ $date }}" class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="font-semibold">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('D, d M') }}</div>
                            <div class="text-base text-muted">
                                {{ $day['in']?->server_time->format('H.i') ?? '—' }} – {{ $day['out']?->server_time->format('H.i') ?? 'belum pulang' }}
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            @foreach (array_filter([$day['in'], $day['out']]) as $record)
                                @if ($record->isLate() || $record->isEarlyLeave())
                                    @php $badge = $record->statusBadge(); @endphp
                                    <x-status-badge :variant="$badge['variant']">{{ $badge['label'] }}</x-status-badge>
                                @endif
                            @endforeach
                            @if ($day['in'] && ! $day['in']->isLate() && ! $day['out']?->isEarlyLeave())
                                <x-status-badge variant="success">Tepat waktu</x-status-badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
