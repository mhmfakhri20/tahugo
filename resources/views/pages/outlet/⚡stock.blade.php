<?php

use App\Enums\RequestStatus;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\StockAdjustmentService;
use App\Services\StockOverviewService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Stok outlet untuk penjaga (INV-1, mobile): stok outletnya, catat rusak (INV-9),
 * permintaan ke gudang & konfirmasi terima kiriman (INV-4, INV-7). Selalu di-scope ke outlet user (BR-7).
 */
new #[Title('Stok')] class extends Component {
    use HandlesInventoryErrors;

    #[Url(except: 'stock')]
    public string $tab = 'stock';

    #[Url(except: '')]
    public string $search = '';

    public ?int $wasteItemId = null;

    public string $wasteQty = '';

    public string $wasteReason = '';

    public function mount(): void
    {
        $this->authorize('viewStock', $this->outlet);
    }

    #[Computed]
    public function outlet(): Location
    {
        return auth()->user()->location;
    }

    #[Computed]
    public function rows(): \Illuminate\Support\Collection
    {
        return app(StockOverviewService::class)
            ->rows(collect([$this->outlet]), $this->search)
            ->sortBy(fn ($row) => [$row['level']->needsAttention() ? 0 : 1, $row['item']->name])
            ->values();
    }

    /**
     * @return Collection<int, StockRequest>
     */
    #[Computed]
    public function requests(): Collection
    {
        return StockRequest::query()
            ->visibleTo(auth()->user())
            ->with('items.item')
            ->orderByRaw("CASE WHEN status = 'shipped' THEN 0 WHEN status IN ('pending', 'approved') THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function incomingCount(): int
    {
        return StockRequest::query()->visibleTo(auth()->user())->inStatus(RequestStatus::Shipped)->count();
    }

    #[Computed]
    public function wasteItem(): ?Item
    {
        return $this->wasteItemId ? Item::find($this->wasteItemId) : null;
    }

    public function openWaste(int $itemId): void
    {
        $this->authorize('recordWaste', $this->outlet);

        $this->resetErrorBag();
        $this->reset('wasteQty', 'wasteReason');
        $this->wasteItemId = $itemId;
        $this->modal('stock-waste')->show();
    }

    public function recordWaste(StockAdjustmentService $service): void
    {
        $this->authorize('recordWaste', $this->outlet);

        $validated = $this->validate([
            'wasteItemId' => ['required', 'integer', 'exists:items,id'],
            'wasteQty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'wasteReason' => ['required', 'string', 'max:500'],
        ], attributes: ['wasteQty' => 'jumlah', 'wasteReason' => 'alasan']);

        $item = Item::findOrFail($validated['wasteItemId']);

        $this->guardInventory(fn () => $service->waste($this->outlet, $item, $validated['wasteQty'], $validated['wasteReason'], auth()->user()), 'wasteQty');

        $this->modal('stock-waste')->close();
        unset($this->rows);
        $this->dispatch('notify', message: qty($validated['wasteQty'])." {$item->unit} {$item->name} dicatat rusak/terbuang.");
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-[26px] leading-tight font-bold">Stok outlet</h1>
            <p class="text-muted">{{ $this->outlet->name }}</p>
        </div>
    </div>

    <flux:button variant="primary" icon="paper-airplane" :href="route('outlet.requests.create')" wire:navigate class="min-h-14 w-full text-base">
        Minta stok ke gudang
    </flux:button>

    @if ($this->incomingCount > 0)
        <button type="button" wire:click="$set('tab', 'requests')" class="flex min-h-14 items-center gap-3 rounded-xl bg-info/10 p-4 text-start text-info">
            <flux:icon name="truck" class="size-6 shrink-0" />
            <span class="flex-1 font-semibold">{{ $this->incomingCount }} kiriman perlu dikonfirmasi</span>
            <flux:icon name="chevron-right" class="size-5" />
        </button>
    @endif

    <nav class="grid grid-cols-2 border-b border-border" aria-label="Tampilan stok">
        @foreach (['stock' => 'Stok saya', 'requests' => 'Permintaan'] as $key => $label)
            <button
                type="button"
                wire:click="$set('tab', '{{ $key }}')"
                @if ($tab === $key) aria-current="page" @endif
                @class([
                    '-mb-px min-h-12 border-b-2 text-base',
                    'border-primary font-semibold text-text' => $tab === $key,
                    'border-transparent text-muted' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'requests')
        @if ($this->requests->isEmpty())
            <x-empty-state icon="clipboard-document-list" title="Belum ada permintaan" description="Tekan tombol Minta stok ke gudang bila bahan mulai menipis." size="lg" class="border border-border" />
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($this->requests as $request)
                    <li wire:key="req-{{ $request->id }}">
                        <a href="{{ route('outlet.requests.show', $request) }}" wire:navigate class="flex min-h-12 flex-col gap-2 rounded-xl border border-border bg-surface p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="font-semibold">{{ $request->number }}</div>
                                <x-status-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-status-badge>
                            </div>
                            <div class="text-muted">{{ $request->items->map(fn ($line) => $line->item->name.' '.qty($line->qty_requested).' '.$line->item->unit)->implode(' · ') }}</div>
                            <div class="flex items-center justify-between text-sm text-muted">
                                <span>{{ $request->created_at->translatedFormat('d M Y, H.i') }}</span>
                                @if ($request->status === RequestStatus::Shipped)
                                    <span class="font-semibold text-info">Konfirmasi terima →</span>
                                @elseif ($request->status === RequestStatus::Received && $request->hasDiscrepancy())
                                    <x-status-badge variant="warning">Selisih</x-status-badge>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @else
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari bahan..." aria-label="Cari bahan" />

        @if ($this->rows->isEmpty())
            <x-empty-state icon="archive-box" title="Bahan tidak ditemukan" description="Ubah kata kunci pencarian." size="lg" class="border border-border" />
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($this->rows as $row)
                    <li wire:key="row-{{ $row['item']->id }}" class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-semibold">{{ $row['item']->name }}</div>
                                <div class="text-2xl font-bold">{{ qty($row['qty']) }} <span class="text-base font-normal text-muted">{{ $row['item']->unit }}</span></div>
                                @if ($row['min'] !== null)
                                    <div class="text-sm text-muted">Minimum {{ qty($row['min']) }} {{ $row['item']->unit }}</div>
                                @endif
                            </div>
                            <x-status-badge :variant="$row['level']->badge()">{{ $row['level']->label() }}</x-status-badge>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <flux:button :href="route('outlet.stock.card', $row['item'])" wire:navigate icon="clipboard-document-list" class="min-h-12">Riwayat</flux:button>
                            <flux:button wire:click="openWaste({{ $row['item']->id }})" icon="trash" class="min-h-12">Catat rusak</flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <flux:modal name="stock-waste" class="w-full max-w-md">
        <form wire:submit="recordWaste" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Catat rusak / terbuang</flux:heading>
                <flux:text class="mt-1 text-base">{{ $this->wasteItem?->name }}. Stok outlet akan berkurang.</flux:text>
            </div>
            <flux:input wire:model="wasteQty" label="Jumlah *" type="number" min="0" step="0.001" inputmode="decimal">
                <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $this->wasteItem?->unit }}</span></x-slot>
            </flux:input>
            <flux:textarea wire:model="wasteReason" label="Alasan *" rows="2" placeholder="Mis. tahu basi, jatuh, kemasan sobek." />
            <div class="grid grid-cols-2 gap-2">
                <flux:modal.close>
                    <flux:button class="min-h-12 w-full">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check" class="min-h-12">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
