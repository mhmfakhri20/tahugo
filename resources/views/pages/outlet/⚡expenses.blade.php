<?php

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\CashEntry;
use App\Models\Shift;
use App\Services\CashService;
use App\Services\ShiftService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Pengeluaran outlet (CASH-1, CASH-2, CASH-5), mobile. Daftar tetap bisa dibuka tanpa shift supaya
 * penjaga melihat pengeluaran yang ditolak; mencatat hanya saat shift buka (BR-4). Selalu di-scope ke outlet user (BR-7).
 */
new #[Title('Pengeluaran')] class extends Component {
    use HandlesInventoryErrors;

    public string $incomeAmount = '';

    public string $incomeDescription = '';

    #[Locked]
    public ?int $deleteId = null;

    #[Computed]
    public function shift(): ?Shift
    {
        return app(ShiftService::class)->openShiftFor(auth()->user()->location);
    }

    /**
     * Entries of the open shift (all staff of this outlet).
     *
     * @return Collection<int, CashEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        if ($this->shift === null) {
            return new Collection;
        }

        return CashEntry::query()
            ->with(['category', 'user', 'verifier', 'shift'])
            ->where('shift_id', $this->shift->id)
            ->where('location_id', auth()->user()->location_id)
            ->latest('id')
            ->get();
    }

    /**
     * CASH-5: my rejected expenses of the last 14 days, to be accounted for.
     *
     * @return Collection<int, CashEntry>
     */
    #[Computed]
    public function rejected(): Collection
    {
        return CashEntry::query()
            ->with(['category', 'verifier'])
            ->where('user_id', auth()->id())
            ->where('direction', CashDirection::Out)
            ->where('status', ExpenseStatus::Rejected)
            ->where('created_at', '>=', today()->subDays(13))
            ->latest('verified_at')
            ->get();
    }

    #[Computed]
    public function totals(): array
    {
        $out = $this->entries->where('direction', CashDirection::Out);

        return [
            'pending' => (int) $out->where('status', ExpenseStatus::Pending)->sum('amount'),
            'pending_count' => $out->where('status', ExpenseStatus::Pending)->count(),
            'approved' => (int) $out->where('status', ExpenseStatus::Approved)->sum('amount'),
            'rejected' => (int) $this->rejected->sum('amount'),
        ];
    }

    public function saveIncome(CashService $cash): void
    {
        $validated = $this->validate([
            'incomeAmount' => ['required', 'integer', 'min:1', 'max:'.CashService::MAX_AMOUNT],
            'incomeDescription' => ['required', 'string', 'min:3', 'max:500'],
        ], [], ['incomeAmount' => 'nominal', 'incomeDescription' => 'keterangan']);

        $this->guardInventory(fn () => $cash->recordIncome(auth()->user(), (int) $validated['incomeAmount'], $validated['incomeDescription']), 'incomeAmount');

        Flux::modal('income')->close();
        $this->reset('incomeAmount', 'incomeDescription');
        unset($this->entries);
        $this->dispatch('notify', message: 'Pemasukan lain tercatat dan menambah kas seharusnya.');
    }

    public function confirmDelete(int $id): void
    {
        $entry = CashEntry::with('shift')->findOrFail($id);
        $this->authorize('delete', $entry);
        $this->deleteId = $entry->id;

        Flux::modal('delete-entry')->show();
    }

    public function delete(CashService $cash): void
    {
        $entry = CashEntry::findOrFail($this->deleteId);

        $this->guardInventory(fn () => $cash->deleteEntry($entry, auth()->user()), 'delete');

        Flux::modal('delete-entry')->close();
        $this->deleteId = null;
        unset($this->entries, $this->totals);
        $this->dispatch('notify', message: 'Catatan dihapus.');
    }
}; ?>

@php $totals = $this->totals; @endphp

<div class="flex flex-col gap-5">
    <div>
        <h1 class="text-[26px] leading-tight font-bold">Pengeluaran</h1>
        <p class="text-base text-muted">Catat pengeluaran dengan foto nota. Pemilik akan memeriksanya.</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-sm text-muted">Menunggu verifikasi</div>
            <div class="text-xl font-bold">{{ rupiah($totals['pending']) }}</div>
            <div class="text-sm text-muted">{{ $totals['pending_count'] }} pengeluaran</div>
        </div>
        <div class="rounded-xl border border-border bg-surface p-4">
            <div class="text-sm text-muted">Disetujui shift ini</div>
            <div class="text-xl font-bold">{{ rupiah($totals['approved']) }}</div>
        </div>
        @if ($totals['rejected'] > 0)
            <div class="col-span-2 rounded-xl border border-danger/30 bg-danger/10 p-4">
                <div class="text-sm text-danger">Harus dipertanggungjawabkan (14 hari)</div>
                <div class="text-xl font-bold text-danger">{{ rupiah($totals['rejected']) }}</div>
                <div class="text-sm">Pengeluaran yang ditolak pemilik. Lihat alasannya di bawah.</div>
            </div>
        @endif
    </div>

    @if ($this->shift)
        <div class="flex flex-col gap-2">
            <a href="{{ route('outlet.expenses.create') }}" wire:navigate class="flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B]">
                <flux:icon name="camera" class="size-6" /> Catat pengeluaran
            </a>
            <flux:modal.trigger name="income">
                <flux:button icon="plus" class="min-h-12 w-full">Pemasukan lain</flux:button>
            </flux:modal.trigger>
        </div>
    @else
        <div class="flex items-start gap-3 rounded-xl bg-soft/60 p-4">
            <flux:icon name="information-circle" class="size-6 shrink-0 text-brown" />
            <div class="text-base">Buka shift dulu di Beranda untuk mencatat pengeluaran.</div>
        </div>
    @endif

    {{-- Catatan shift berjalan --}}
    @if ($this->shift)
        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Catatan shift ini</h2>

            @if ($this->entries->isEmpty())
                <x-empty-state icon="banknotes" title="Belum ada catatan" description="Pengeluaran dan pemasukan lain di shift ini akan tampil di sini." size="lg" class="border border-border" />
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($this->entries as $entry)
                        @php $badge = $entry->statusBadge(); $isIn = $entry->direction === \App\Enums\CashDirection::In; @endphp
                        <li wire:key="entry-{{ $entry->id }}" class="flex flex-col gap-2 rounded-xl border border-border bg-surface p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-semibold">{{ $isIn ? 'Pemasukan lain' : $entry->category?->name }}</div>
                                    <div class="text-base">{{ $entry->description }}</div>
                                    <div class="text-sm text-muted">
                                        {{ $entry->number() }} · {{ $entry->created_at->format('H.i') }} · {{ $entry->user->name }}
                                        @unless ($isIn) · {{ $entry->fund_source?->label() }} @endunless
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <div @class(['text-lg font-bold', 'text-success' => $isIn])>{{ $isIn ? '+' : '' }}{{ rupiah($entry->amount) }}</div>
                                    @unless ($isIn)
                                        <x-status-badge :variant="$badge['variant']">{{ $badge['label'] }}</x-status-badge>
                                    @endunless
                                </div>
                            </div>
                            @if ($entry->reject_reason)
                                <div class="text-sm text-danger">Alasan pemilik: {{ $entry->reject_reason }}</div>
                            @endif
                            @if (auth()->user()->can('update', $entry) || auth()->user()->can('delete', $entry))
                                <div class="flex justify-end gap-2">
                                    @can('update', $entry)
                                        <flux:button variant="ghost" icon="pencil-square" class="min-h-12" :href="route('outlet.expenses.edit', $entry)" wire:navigate>Ubah</flux:button>
                                    @endcan
                                    @can('delete', $entry)
                                        <flux:button variant="ghost" icon="trash" class="btn-danger min-h-12" wire:click="confirmDelete({{ $entry->id }})">Hapus</flux:button>
                                    @endcan
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    {{-- Ditolak (CASH-5) --}}
    @if ($this->rejected->isNotEmpty())
        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Harus dipertanggungjawabkan</h2>
            <ul class="flex flex-col gap-3">
                @foreach ($this->rejected as $entry)
                    <li wire:key="rejected-{{ $entry->id }}" class="flex flex-col gap-1 rounded-xl border border-danger/30 bg-surface p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold">{{ $entry->category?->name }} · {{ $entry->number() }}</div>
                                <div class="text-sm text-muted">{{ $entry->created_at->translatedFormat('d M, H.i') }} · {{ $entry->description }}</div>
                            </div>
                            <div class="text-lg font-bold text-danger">{{ rupiah($entry->amount) }}</div>
                        </div>
                        <div class="text-sm text-danger">Ditolak {{ $entry->verifier?->name }}: {{ $entry->reject_reason }}</div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <flux:modal name="income" class="w-full max-w-md">
        <form wire:submit="saveIncome" class="flex flex-col gap-4">
            <flux:heading size="lg">Pemasukan lain (tunai)</flux:heading>
            <p class="text-base text-muted">Uang masuk ke laci selain penjualan. Jarang terjadi.</p>
            <flux:input wire:model="incomeAmount" type="number" min="1" inputmode="numeric" label="Nominal *">
                <x-slot name="iconLeading"><span class="text-base text-muted">Rp</span></x-slot>
            </flux:input>
            <flux:textarea wire:model="incomeDescription" label="Keterangan *" rows="2" placeholder="Mis. titipan uang dari pemilik" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button class="min-h-12">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check" class="min-h-12">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-entry" class="w-full max-w-md">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Hapus catatan ini?</flux:heading>
            <p class="text-base text-muted">Catatan dan foto notanya akan dihapus.</p>
            @error('delete')
                <x-inline-error>{{ $message }}</x-inline-error>
            @enderror
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button class="min-h-12">Batal</flux:button></flux:modal.close>
                <flux:button variant="ghost" icon="trash" class="btn-danger min-h-12" wire:click="delete">Ya, hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
