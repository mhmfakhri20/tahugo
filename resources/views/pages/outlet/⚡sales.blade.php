<?php

use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\SaleService;
use App\Services\ShiftService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Riwayat transaksi shift berjalan + pembatalan (POS-8). Hanya transaksi outlet user (BR-7);
 * setelah shift ditutup transaksi terkunci (BR-6).
 */
new #[Title('Riwayat transaksi')] class extends Component {
    use HandlesInventoryErrors;

    #[Locked]
    public ?int $voidId = null;

    public string $voidReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Sale::class);
    }

    #[Computed]
    public function shift(): ?Shift
    {
        return app(ShiftService::class)->openShiftFor(auth()->user()->location);
    }

    /**
     * @return Collection<int, Sale>
     */
    #[Computed]
    public function sales(): Collection
    {
        return Sale::query()
            ->with(['items.product', 'user', 'voider'])
            ->where('location_id', auth()->user()->location_id)
            ->where('shift_id', $this->shift?->id)
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function summary(): ?array
    {
        return $this->shift ? app(SaleService::class)->shiftSummary($this->shift) : null;
    }

    #[Computed]
    public function voiding(): ?Sale
    {
        return $this->voidId ? Sale::find($this->voidId) : null;
    }

    public function confirmVoid(int $saleId): void
    {
        $sale = Sale::with('shift')->findOrFail($saleId);
        $this->authorize('void', $sale);

        $this->voidId = $sale->id;
        $this->voidReason = '';
        $this->resetErrorBag();

        Flux::modal('void-sale')->show();
    }

    public function void(SaleService $service): void
    {
        $this->validate(
            ['voidReason' => ['required', 'string', 'min:3', 'max:255']],
            [],
            ['voidReason' => 'alasan pembatalan'],
        );

        $sale = Sale::findOrFail($this->voidId);

        $this->guardInventory(fn () => $service->void($sale, auth()->user(), $this->voidReason), 'voidReason');

        Flux::modal('void-sale')->close();
        $this->dispatch('notify', message: "Transaksi {$sale->number} dibatalkan. Stok bahan sudah dikembalikan.");

        $this->reset('voidId', 'voidReason');
        unset($this->sales, $this->summary);
    }
}; ?>

<div class="flex flex-col gap-5">
    <div>
        <a href="{{ route('outlet.cashier') }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
            <flux:icon name="arrow-left" class="size-5" /> Kasir
        </a>
        <h1 class="text-[26px] leading-tight font-bold">Riwayat transaksi</h1>
        @if ($this->shift)
            <p class="text-base text-muted">Shift dibuka {{ $this->shift->opened_at->translatedFormat('d M Y, H.i') }}.</p>
        @endif
    </div>

    @if ($summary = $this->summary)
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl border border-border bg-surface p-4">
                <div class="text-sm text-muted">Penjualan</div>
                <div class="text-xl font-bold">{{ rupiah($summary['total']) }}</div>
            </div>
            <div class="rounded-xl border border-border bg-surface p-4">
                <div class="text-sm text-muted">Transaksi</div>
                <div class="text-xl font-bold">{{ $summary['count'] }}</div>
                @if ($summary['void_count'])
                    <div class="text-sm text-muted">{{ $summary['void_count'] }} dibatalkan</div>
                @endif
            </div>
        </div>
    @endif

    @if ($this->sales->isEmpty())
        <x-empty-state icon="receipt-percent" title="Belum ada transaksi" description="Transaksi di shift ini akan tampil di sini." size="lg" class="border border-border">
            <flux:button :href="route('outlet.cashier')" wire:navigate icon="shopping-cart" class="min-h-12">Ke kasir</flux:button>
        </x-empty-state>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($this->sales as $sale)
                <li wire:key="sale-{{ $sale->id }}" @class(['flex flex-col gap-3 rounded-xl border border-border bg-surface p-4', 'opacity-70' => $sale->isVoid()])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-semibold">{{ $sale->number }}</div>
                            <div class="text-sm text-muted">{{ $sale->created_at->format('H.i') }} · {{ $sale->channelLabel() }} · {{ $sale->user->name }}</div>
                            @if ($sale->platform_order_id)
                                <div class="text-sm text-muted">Pesanan {{ $sale->platform_order_id }}</div>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <div @class(['text-lg font-bold', 'line-through' => $sale->isVoid()])>{{ rupiah($sale->total) }}</div>
                            <x-status-badge :variant="$sale->status->badge()">{{ $sale->status->label() }}</x-status-badge>
                        </div>
                    </div>

                    <div class="text-base">
                        {{ $sale->items->map(fn ($item) => $item->product->name.' × '.$item->qty)->implode(', ') }}
                    </div>

                    @if ($sale->isVoid())
                        <div class="text-sm text-danger">Dibatalkan {{ $sale->voider?->name }} pukul {{ $sale->voided_at->format('H.i') }}: {{ $sale->void_reason }}</div>
                    @else
                        <div class="flex justify-end">
                            <flux:button variant="ghost" icon="x-circle" class="btn-danger min-h-12" wire:click="confirmVoid({{ $sale->id }})">
                                Batalkan
                            </flux:button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal name="void-sale" class="w-full max-w-md">
        <form wire:submit="void" class="flex flex-col gap-4">
            <flux:heading size="lg">Batalkan transaksi {{ $this->voiding?->number }}?</flux:heading>
            <p class="text-base text-muted">
                Total {{ rupiah($this->voiding?->total) }}. Stok bahan akan dikembalikan ke outlet. Pembatalan tidak bisa diurungkan.
            </p>
            <flux:textarea wire:model="voidReason" label="Alasan pembatalan *" rows="2" placeholder="Mis. salah input produk" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button class="min-h-12">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="ghost" icon="x-circle" class="btn-danger min-h-12">Ya, batalkan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
