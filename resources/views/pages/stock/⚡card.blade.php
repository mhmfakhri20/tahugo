<?php

use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\Qty;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Kartu stok (INV-10): riwayat mutasi satu bahan di satu lokasi dengan saldo berjalan.
 * Dipakai ketiga peran; penjaga outlet hanya untuk outletnya (BR-7).
 */
new #[Title('Kartu stok')] class extends Component {
    use WithPagination;

    #[Locked]
    public int $locationId;

    #[Locked]
    public int $itemId;

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $until = '';

    public function mount(Item $item, ?Location $location = null): void
    {
        $user = auth()->user();
        $location = $user->isOutletStaff() ? $user->location : $location;

        abort_if($location === null || ! $location->exists, 404);
        $this->authorize('viewStock', $location);

        $this->locationId = $location->id;
        $this->itemId = $item->id;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'until'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function location(): Location
    {
        return Location::findOrFail($this->locationId);
    }

    #[Computed]
    public function item(): Item
    {
        return Item::findOrFail($this->itemId);
    }

    #[Computed]
    public function balance(): string
    {
        return app(StockService::class)->balance($this->locationId, $this->itemId);
    }

    #[Computed]
    public function minQty(): ?string
    {
        $min = $this->item->minStocks()->where('location_id', $this->locationId)->value('min_qty');

        return $min !== null ? Qty::of($min) : null;
    }

    /**
     * Newest first; each row carries its own `balance_after`, so the running balance stays
     * correct with pagination and date filters.
     */
    #[Computed]
    public function movements(): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with('user:id,name')
            ->where('location_id', $this->locationId)
            ->where('item_id', $this->itemId)
            ->when($this->from !== '', fn ($query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->until !== '', fn ($query) => $query->where('created_at', '<=', $this->until.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function backUrl(): string
    {
        return match (auth()->user()->role->area()) {
            'owner' => route('owner.stock'),
            'warehouse' => route('warehouse.stock', ['locationId' => $this->locationId]),
            default => route('outlet.stock'),
        };
    }
}; ?>

@php
    $isOutlet = auth()->user()->isOutletStaff();
    $level = \App\Enums\StockLevel::for($this->balance, $this->minQty);
@endphp

<div class="flex flex-col gap-6">
    <div>
        <a href="{{ $this->backUrl() }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted hover:text-text sm:min-h-0 sm:text-sm">
            <flux:icon name="arrow-left" class="size-4" /> Kembali ke stok
        </a>
        <x-page-header class="mt-1" :title="'Kartu stok: '.$this->item->name" :description="$this->location->name.' · satuan '.$this->item->unit" />
    </div>

    <div @class(['grid gap-4', 'grid-cols-2' => $isOutlet, 'sm:grid-cols-3' => ! $isOutlet])>
        <x-metric-card label="Stok saat ini" :value="qty($this->balance).' '.$this->item->unit" icon="archive-box" />
        <x-metric-card label="Stok minimum" :value="$this->minQty !== null ? qty($this->minQty).' '.$this->item->unit : 'Belum diatur'" icon="flag" />
        <div @class(['flex flex-col gap-3 rounded-xl border border-border bg-surface p-5', 'col-span-2' => $isOutlet])>
            <div class="text-sm text-muted">Status</div>
            <div><x-status-badge :variant="$level->badge()" class="text-base">{{ $level->label() }}</x-status-badge></div>
        </div>
    </div>

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div @class(['flex flex-col gap-4', 'md:flex-row md:items-end md:justify-between' => ! $isOutlet])>
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-text">Riwayat mutasi</h2>
                <p @class(['text-muted', 'text-base' => $isOutlet, 'text-sm' => ! $isOutlet])>Masuk (+) dan keluar (−) beserta saldo setelahnya.</p>
            </div>
            <div @class(['grid min-w-0 gap-3', 'grid-cols-1 min-[400px]:grid-cols-2' => $isOutlet, 'grid-cols-2' => ! $isOutlet])>
                <flux:input type="date" wire:model.live="from" label="Dari tanggal" class="min-w-0 [&_input]:min-w-0" />
                <flux:input type="date" wire:model.live="until" label="Sampai tanggal" class="min-w-0 [&_input]:min-w-0" />
            </div>
        </div>

        @if ($this->movements->isEmpty())
            <x-empty-state icon="clipboard-document-list" title="Belum ada mutasi" description="Mutasi muncul setelah ada pembelian, kiriman, penjualan, atau penyesuaian." :size="$isOutlet ? 'lg' : 'md'" />
        @elseif ($isOutlet)
            <ul class="flex flex-col divide-y divide-border">
                @foreach ($this->movements as $movement)
                    <li wire:key="mv-{{ $movement->id }}" class="flex items-start justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $movement->type->label() }}</div>
                            <div class="text-sm text-muted">{{ $movement->created_at->translatedFormat('d M Y, H.i') }} · {{ $movement->user->name }}</div>
                            @if ($movement->note)
                                <div class="text-sm text-muted">{{ $movement->note }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 text-end">
                            <div @class(['font-semibold', 'text-success' => Qty::isPositive($movement->qty), 'text-danger' => Qty::isNegative($movement->qty)])>
                                {{ Qty::isPositive($movement->qty) ? '+' : '−' }}{{ qty(ltrim($movement->qty, '-')) }}
                            </div>
                            <div class="text-sm text-muted">Saldo {{ qty($movement->balance_after) }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div>{{ $this->movements->links() }}</div>
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Waktu</th>
                    <th>Jenis</th>
                    <th>Keterangan</th>
                    <th>Oleh</th>
                    <th class="text-end!">Masuk</th>
                    <th class="text-end!">Keluar</th>
                    <th class="text-end!">Saldo</th>
                </x-slot:head>

                @foreach ($this->movements as $movement)
                    <tr wire:key="mv-{{ $movement->id }}">
                        <td class="whitespace-nowrap">{{ $movement->created_at->translatedFormat('d M Y, H.i') }}</td>
                        <td>{{ $movement->type->label() }}</td>
                        <td class="text-muted">{{ $movement->note ?? '—' }}</td>
                        <td>{{ $movement->user->name }}</td>
                        <td class="text-end text-success">{{ Qty::isPositive($movement->qty) ? '+'.qty($movement->qty) : '' }}</td>
                        <td class="text-end text-danger">{{ Qty::isNegative($movement->qty) ? '−'.qty(ltrim($movement->qty, '-')) : '' }}</td>
                        <td class="text-end font-semibold">{{ qty($movement->balance_after) }}</td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->movements->count() }} dari {{ $this->movements->total() }} mutasi</span>
                    {{ $this->movements->links() }}
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>
</div>
