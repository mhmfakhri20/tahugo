<?php

use App\Enums\ItemCategory;
use App\Enums\StockLevel;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Item;
use App\Models\Location;
use App\Services\StockAdjustmentService;
use App\Services\StockOverviewService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Stok per lokasi untuk admin gudang (INV-1): gudang (default) dan outlet (hanya lihat).
 * Catat rusak/terbuang (INV-9) hanya untuk gudang.
 */
new #[Title('Stok Gudang')] class extends Component {
    use HandlesInventoryErrors;

    #[Url(except: null)]
    public ?int $locationId = null;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $categoryFilter = '';

    #[Url(except: '')]
    public string $levelFilter = '';

    public ?int $wasteItemId = null;

    public string $wasteQty = '';

    public string $wasteReason = '';

    public function mount(): void
    {
        $this->locationId ??= Location::query()->warehouses()->active()->orderBy('id')->value('id')
            ?? Location::query()->active()->orderBy('id')->value('id');

        if ($this->locationId !== null) {
            $this->authorize('viewStock', $this->location);
        }
    }

    public function updatedLocationId(): void
    {
        $this->authorize('viewStock', $this->location);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->active()->orderBy('type')->orderBy('name')->get();
    }

    #[Computed]
    public function location(): ?Location
    {
        return $this->locationId ? Location::findOrFail($this->locationId) : null;
    }

    #[Computed]
    public function rows(): \Illuminate\Support\Collection
    {
        return app(StockOverviewService::class)
            ->rows(collect([$this->location]), $this->search, ItemCategory::tryFrom($this->categoryFilter))
            ->when($this->levelFilter !== '', fn ($rows) => $rows->filter(fn ($row) => $row['level']->value === $this->levelFilter))
            ->values();
    }

    #[Computed]
    public function canWaste(): bool
    {
        return $this->location !== null && auth()->user()->can('recordWaste', $this->location);
    }

    #[Computed]
    public function wasteItem(): ?Item
    {
        return $this->wasteItemId ? Item::find($this->wasteItemId) : null;
    }

    public function openWaste(int $itemId): void
    {
        $this->authorize('recordWaste', $this->location);

        $this->resetErrorBag();
        $this->reset('wasteQty', 'wasteReason');
        $this->wasteItemId = $itemId;
        $this->modal('stock-waste')->show();
    }

    public function recordWaste(StockAdjustmentService $service): void
    {
        $this->authorize('recordWaste', $this->location);

        $validated = $this->validate([
            'wasteItemId' => ['required', 'integer', 'exists:items,id'],
            'wasteQty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'wasteReason' => ['required', 'string', 'max:500'],
        ], attributes: ['wasteQty' => 'jumlah', 'wasteReason' => 'alasan']);

        $item = Item::findOrFail($validated['wasteItemId']);

        $this->guardInventory(fn () => $service->waste($this->location, $item, $validated['wasteQty'], $validated['wasteReason'], auth()->user()), 'wasteQty');

        $this->modal('stock-waste')->close();
        unset($this->rows);
        $this->dispatch('notify', message: qty($validated['wasteQty'])." {$item->unit} {$item->name} dicatat rusak/terbuang.");
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Stok Gudang" description="Pantau persediaan gudang dan setiap outlet.">
        <flux:button icon="shopping-bag" :href="route('warehouse.purchases.create')" wire:navigate>Catat pembelian</flux:button>
        <flux:button variant="primary" icon="truck" :href="route('warehouse.shipments')" wire:navigate>Proses pengiriman</flux:button>
    </x-page-header>

    @if ($this->location === null)
        <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <x-empty-state icon="archive-box" title="Belum ada gudang" description="Minta pemilik menambahkan gudang di Pengaturan." />
        </div>
    @else
    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Persediaan {{ $this->location->name }}</h2>
                <p class="text-sm text-muted">
                    {{ $this->location->isWarehouse() ? 'Belum dikurangi permintaan yang menunggu persetujuan.' : 'Stok outlet hanya bisa dilihat.' }}
                </p>
            </div>
            <flux:select wire:model.live="locationId" aria-label="Pilih lokasi" class="sm:w-64!">
                @foreach ($this->locations as $location)
                    <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex flex-col gap-3 md:flex-row">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama bahan..." class="md:max-w-sm" aria-label="Cari bahan" />
            <div class="grid grid-cols-2 gap-3 md:ms-auto md:flex">
                <flux:select wire:model.live="categoryFilter" aria-label="Filter kategori">
                    <flux:select.option value="">Semua kategori</flux:select.option>
                    @foreach (ItemCategory::options() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="levelFilter" aria-label="Filter status">
                    <flux:select.option value="">Semua status</flux:select.option>
                    @foreach (StockLevel::cases() as $level)
                        <flux:select.option :value="$level->value">{{ $level->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($this->rows->isEmpty())
            <x-empty-state icon="archive-box" title="Bahan tidak ditemukan" description="Ubah kata kunci atau filter." />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Bahan</th>
                    <th>Kategori</th>
                    <th>Stok tersedia</th>
                    <th>Minimum</th>
                    <th>Status</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->rows as $row)
                    <tr wire:key="row-{{ $row['item']->id }}">
                        <td class="font-medium">{{ $row['item']->name }}</td>
                        <td>{{ $row['item']->category->label() }}</td>
                        <td class="font-semibold">{{ qty($row['qty']) }} {{ $row['item']->unit }}</td>
                        <td>{{ $row['min'] !== null ? qty($row['min']).' '.$row['item']->unit : '—' }}</td>
                        <td><x-status-badge :variant="$row['level']->badge()">{{ $row['level']->label() }}</x-status-badge></td>
                        <td class="text-end whitespace-nowrap">
                            @if ($this->location->isWarehouse() && $row['level']->needsAttention())
                                <flux:button size="sm" :href="route('warehouse.purchases.create', ['item' => $row['item']->id])" wire:navigate>Beli bahan</flux:button>
                            @endif
                            @if ($this->canWaste)
                                <flux:button variant="ghost" size="sm" wire:click="openWaste({{ $row['item']->id }})">Catat rusak</flux:button>
                            @endif
                            <flux:button variant="ghost" size="sm" :href="route('warehouse.stock.card', [$this->location, $row['item']])" wire:navigate>Detail</flux:button>
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->rows->count() }} bahan</span>
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>
    @endif

    <flux:modal name="stock-waste" class="w-full max-w-md">
        <form wire:submit="recordWaste" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Catat rusak / terbuang</flux:heading>
                <flux:text class="mt-1">{{ $this->wasteItem?->name }} di {{ $this->location?->name }}. Stok akan berkurang.</flux:text>
            </div>
            <flux:input wire:model="wasteQty" label="Jumlah *" type="number" min="0" step="0.001" inputmode="decimal">
                <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $this->wasteItem?->unit }}</span></x-slot>
            </flux:input>
            <flux:textarea wire:model="wasteReason" label="Alasan *" rows="2" placeholder="Mis. tahu basi, kemasan sobek." />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
