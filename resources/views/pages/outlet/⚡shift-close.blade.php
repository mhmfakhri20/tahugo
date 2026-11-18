<?php

use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Shift;
use App\Services\SaleService;
use App\Services\ShiftService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Tutup shift (SHF-3/4): hitung uang tunai fisik, foto kondisi outlet/gerobak (kamera langsung).
 * Kas seharusnya & selisih dihitung server; setelah ditutup shift terkunci (SHF-5, BR-6).
 */
new #[Title('Tutup shift')] class extends Component {
    use HandlesInventoryErrors, WithFileUploads;

    #[Locked]
    public ?int $shiftId = null;

    public string $countedCash = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(ShiftService $service): void
    {
        $shift = $service->openShiftFor(auth()->user()->location);

        if ($shift === null) {
            session()->flash('error', 'Belum ada shift yang buka di outlet ini.');
            $this->redirectRoute('outlet.home', navigate: true);

            return;
        }

        $this->authorize('close', $shift);
        $this->shiftId = $shift->id;
    }

    #[Computed]
    public function shift(): ?Shift
    {
        return $this->shiftId ? Shift::with(['opener', 'location'])->find($this->shiftId) : null;
    }

    #[Computed]
    public function expectedCash(): int
    {
        return $this->shift ? app(ShiftService::class)->expectedCash($this->shift) : 0;
    }

    /**
     * Cash breakdown: income, expenses per status, staff advances (§6.3, CASH-5).
     */
    #[Computed]
    public function cash(): ?array
    {
        return $this->shift ? app(ShiftService::class)->cashSummary($this->shift) : null;
    }

    /**
     * Sales of this shift: count, per channel, cash vs QRIS, item usage (SHF-3/4).
     */
    #[Computed]
    public function summary(): ?array
    {
        return $this->shift ? app(SaleService::class)->shiftSummary($this->shift) : null;
    }

    #[Computed]
    public function variance(): ?int
    {
        return is_numeric($this->countedCash) ? (int) $this->countedCash - $this->expectedCash : null;
    }

    public function close(ShiftService $service): void
    {
        $validated = $this->validate([
            'countedCash' => ['required', 'integer', 'min:0', 'max:100000000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Ambil foto kondisi outlet/gerobak dulu.',
        ], [
            'countedCash' => 'uang tunai fisik',
            'photo' => 'foto kondisi outlet',
        ]);

        $shift = $this->guardInventory(
            fn () => $service->close(Shift::findOrFail($this->shiftId), auth()->user(), (int) $validated['countedCash'], $this->photo),
            'shift',
        );

        $variance = $shift->variance;
        $note = match (true) {
            $variance === 0 => 'Kas pas, tidak ada selisih.',
            $variance < 0 => 'Kas kurang '.rupiah(abs($variance)).'.',
            default => 'Kas lebih '.rupiah($variance).'.',
        };

        session()->flash('success', 'Shift ditutup. '.$note.' Jangan lupa absen pulang.');

        $this->redirectRoute('outlet.home', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    @if ($shift = $this->shift)
        <div>
            <a href="{{ route('outlet.home') }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
                <flux:icon name="arrow-left" class="size-5" /> Beranda
            </a>
            <h1 class="text-[26px] leading-tight font-bold">Tutup shift</h1>
            <p class="text-base text-muted">Dibuka {{ $shift->opener->name }}, {{ $shift->opened_at->translatedFormat('d M Y, H.i') }}.</p>
        </div>

        @php $summary = $this->summary; @endphp
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4">
            <h2 class="text-lg font-semibold">Penjualan shift ini</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-canvas p-4">
                    <div class="text-sm text-muted">Transaksi</div>
                    <div class="text-lg font-semibold">{{ $summary['count'] }}</div>
                    @if ($summary['void_count'])
                        <div class="text-sm text-muted">{{ $summary['void_count'] }} dibatalkan</div>
                    @endif
                </div>
                <div class="rounded-xl bg-canvas p-4">
                    <div class="text-sm text-muted">Omzet</div>
                    <div class="text-lg font-semibold">{{ rupiah($summary['total']) }}</div>
                </div>
            </div>

            <dl class="flex flex-col divide-y divide-border text-base">
                @foreach (\App\Enums\SalesChannel::cases() as $channel)
                    <div class="flex justify-between gap-3 py-2">
                        <dt class="text-muted">{{ $channel->label() }}</dt>
                        <dd class="font-medium">{{ rupiah($summary['per_channel'][$channel->value]) }}</dd>
                    </div>
                @endforeach
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-muted">Offline tunai (masuk laci)</dt>
                    <dd class="font-semibold">{{ rupiah($summary['cash']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-muted">Offline QRIS</dt>
                    <dd class="font-medium">{{ rupiah($summary['qris']) }}</dd>
                </div>
            </dl>

            @if ($summary['usage']->isNotEmpty())
                <div>
                    <h3 class="mb-1 font-semibold">Bahan terpakai</h3>
                    <ul class="flex flex-col divide-y divide-border text-base">
                        @foreach ($summary['usage'] as $row)
                            <li class="flex justify-between gap-3 py-2">
                                <span>{{ $row['item']->name }}</span>
                                <span class="font-medium">{{ qty($row['qty']) }} {{ $row['item']->unit }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        @php $cash = $this->cash; @endphp
        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4">
            <h2 class="text-lg font-semibold">Uang di laci</h2>
            <dl class="flex flex-col divide-y divide-border text-base">
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-muted">Kas awal</dt>
                    <dd class="font-medium">{{ rupiah($cash['opening_cash']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-muted">+ Penjualan tunai</dt>
                    <dd class="font-medium">{{ rupiah($cash['cash_sales']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-muted">+ Pemasukan lain</dt>
                    <dd class="font-medium">{{ rupiah($cash['income']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-muted">− Pengeluaran kas outlet <span class="block text-sm">disetujui {{ rupiah($cash['expense_approved']) }} · menunggu {{ rupiah($cash['expense_pending']) }}</span></dt>
                    <dd class="font-medium">{{ rupiah($cash['expense_approved'] + $cash['expense_pending']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                    <dt class="font-semibold">Kas seharusnya</dt>
                    <dd class="text-lg font-bold">{{ rupiah($this->expectedCash) }}</dd>
                </div>
            </dl>

            @if ($cash['personal'] > 0 || $cash['expense_rejected'] > 0)
                <dl class="flex flex-col gap-2 rounded-xl bg-canvas p-4 text-base">
                    @if ($cash['personal'] > 0)
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Talangan penjaga (uang pribadi, tidak dari laci)</dt>
                            <dd class="font-medium">{{ rupiah($cash['personal']) }}</dd>
                        </div>
                    @endif
                    @if ($cash['expense_rejected'] > 0)
                        <div class="flex justify-between gap-3 text-danger">
                            <dt>Pengeluaran ditolak (harus dipertanggungjawabkan)</dt>
                            <dd class="font-semibold">{{ rupiah($cash['expense_rejected']) }}</dd>
                        </div>
                    @endif
                </dl>
            @endif

            <flux:field>
                <flux:label class="text-base!">Uang tunai fisik di laci *</flux:label>
                <flux:input wire:model.live.debounce.400ms="countedCash" type="number" min="0" inputmode="numeric" placeholder="0">
                    <x-slot name="iconLeading"><span class="text-base text-muted">Rp</span></x-slot>
                </flux:input>
                <flux:description class="text-base!">Hitung semua uang tunai di laci, termasuk kas awal.</flux:description>
                <flux:error name="countedCash" />
            </flux:field>

            @if ($this->variance !== null)
                @php $variance = $this->variance; @endphp
                <div @class([
                    'flex items-center justify-between gap-3 rounded-xl p-4',
                    'bg-success/10' => $variance === 0,
                    'bg-danger/10' => $variance < 0,
                    'bg-soft' => $variance > 0,
                ])>
                    <div class="text-base">Selisih</div>
                    <div class="text-end">
                        <div @class(['text-xl font-bold', 'text-success' => $variance === 0, 'text-danger' => $variance < 0, 'text-brown' => $variance > 0])>
                            {{ $variance < 0 ? '−' : ($variance > 0 ? '+' : '') }}{{ rupiah(abs($variance)) }}
                        </div>
                        <div class="text-sm text-muted">{{ $variance === 0 ? 'Pas' : ($variance < 0 ? 'Uang kurang' : 'Uang lebih') }}</div>
                    </div>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-border bg-surface p-4">
            <x-camera-capture field="photo" facing="environment" label="Foto kondisi outlet / gerobak" />
            @error('photo')
                <x-inline-error class="mt-3">{{ $message }}</x-inline-error>
            @enderror
        </section>

        @error('shift')
            <x-inline-error>{{ $message }}</x-inline-error>
        @enderror

        <flux:modal.trigger name="confirm-close">
            <button type="button" class="flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B]">
                <flux:icon name="lock-closed" class="size-6" /> Tutup shift
            </button>
        </flux:modal.trigger>

        <flux:modal name="confirm-close" class="w-full max-w-md">
            <div class="flex flex-col gap-4">
                <flux:heading size="lg">Tutup shift sekarang?</flux:heading>
                <p class="text-base text-muted">Setelah ditutup, data shift tidak bisa diubah lagi. Penjualan dan pengeluaran baru harus menunggu shift berikutnya.</p>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button class="min-h-12">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" icon="check" class="min-h-12" wire:click="close" x-on:click="$flux.modal('confirm-close').close()" wire:loading.attr="disabled" wire:target="close,photo">
                        Ya, tutup shift
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
