<?php

use App\Enums\RequestStatus;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\StockOverviewService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    /**
     * Requests approved by the owner, waiting to be shipped (§11).
     *
     * @return Collection<int, StockRequest>
     */
    #[Computed]
    public function readyToShip(): Collection
    {
        return StockRequest::query()
            ->inStatus(RequestStatus::Approved)
            ->with(['items.item', 'toLocation'])
            ->orderBy('approved_at')
            ->get();
    }

    #[Computed]
    public function lowStock(): \Illuminate\Support\Collection
    {
        return app(StockOverviewService::class)->attention(Location::query()->warehouses()->active()->get());
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-greeting :name="auth()->user()->name" description="Kelola persediaan dan pengiriman ke outlet." />

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-text">Permintaan siap dikirim</h2>
                    <p class="text-sm text-muted">Sudah disetujui pemilik, urut dari yang paling lama.</p>
                </div>
                @if ($this->readyToShip->isNotEmpty())
                    <x-status-badge variant="soft">{{ $this->readyToShip->count() }} permintaan</x-status-badge>
                @endif
            </div>

            @if ($this->readyToShip->isEmpty())
                <x-empty-state icon="truck" title="Belum ada yang perlu dikirim" description="Permintaan yang disetujui pemilik akan muncul di sini.">
                    <flux:button :href="route('warehouse.shipments')" wire:navigate>Lihat pengiriman</flux:button>
                </x-empty-state>
            @else
                <ul class="flex flex-col divide-y divide-border">
                    @foreach ($this->readyToShip as $request)
                        <li wire:key="ready-{{ $request->id }}">
                            <a href="{{ route('warehouse.requests.show', $request) }}" wire:navigate class="flex items-center gap-3 py-3 hover:bg-canvas">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
                                    <flux:icon name="building-storefront" class="size-5 text-brown" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ $request->toLocation->name }}</div>
                                    <div class="truncate text-sm text-muted">{{ $request->items->map(fn ($line) => $line->item->name.' '.qty($line->qty_approved).' '.$line->item->unit)->implode(' · ') }}</div>
                                    <div class="text-xs text-muted">{{ $request->number }} · disetujui {{ $request->approved_at?->translatedFormat('d M, H.i') }}</div>
                                </div>
                                <x-status-badge variant="success">Disetujui</x-status-badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Stok gudang menipis</h2>
                <p class="text-sm text-muted">Pertimbangkan pembelian ulang.</p>
            </div>

            @if ($this->lowStock->isEmpty())
                <x-empty-state icon="check-circle" title="Stok gudang aman" description="Tidak ada bahan di bawah batas minimum.">
                    <flux:button :href="route('warehouse.stock')" wire:navigate>Periksa inventori</flux:button>
                </x-empty-state>
            @else
                <ul class="flex flex-col divide-y divide-border">
                    @foreach ($this->lowStock as $row)
                        <li wire:key="low-{{ $row['location']->id }}-{{ $row['item']->id }}" class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $row['item']->name }}</div>
                                <div class="text-sm text-muted">{{ qty($row['qty']) }} {{ $row['item']->unit }} @if ($row['min'] !== null) / min. {{ qty($row['min']) }} @endif</div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-status-badge :variant="$row['level']->badge()">{{ $row['level']->label() }}</x-status-badge>
                                <flux:button size="sm" :href="route('warehouse.purchases.create', ['item' => $row['item']->id])" wire:navigate>Beli bahan</flux:button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
