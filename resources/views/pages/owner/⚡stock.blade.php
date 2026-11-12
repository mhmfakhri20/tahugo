<?php

use App\Enums\ItemCategory;
use App\Enums\StockLevel;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Item;
use App\Models\Location;
use App\Services\StockAdjustmentService;
use App\Services\StockOverviewService;
use App\Services\StockService;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Monitor stok lintas lokasi untuk pemilik (INV-1) + penyesuaian stok (adjustment, alasan wajib).
 */
new #[Title('Stok')] class extends Component {
    use HandlesInventoryErrors;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $categoryFilter = '';

    #[Url(except: false)]
    public bool $attentionOnly = false;

    // Form penyesuaian
    public ?int $adjustLocationId = null;

    public ?int $adjustItemId = null;

    public string $adjustDirection = 'in';

    public string $adjustQty = '';

    public string $adjustReason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->active()->orderBy('type')->orderBy('name')->get();
    }

    /**
     * Rows grouped per item: item_id => [location_id => row].
     *
     * @return \Illuminate\Support\Collection<int, array{item: Item, cells: array<int, array<string, mixed>>}>
     */
    #[Computed]
    public function matrix(): \Illuminate\Support\Collection
    {
        $rows = app(StockOverviewService::class)->rows(
            $this->locations,
            $this->search,
            ItemCategory::tryFrom($this->categoryFilter),
        );

        return $rows->groupBy(fn ($row) => $row['item']->id)
            ->map(fn ($cells) => [
                'item' => $cells->first()['item'],
                'cells' => $cells->keyBy(fn ($row) => $row['location']->id)->all(),
            ])
            ->when($this->attentionOnly, fn ($matrix) => $matrix->filter(
                fn ($row) => collect($row['cells'])->contains(fn ($cell) => $cell['tracked'] && $cell['level']->needsAttention())
            ))
            ->values();
    }

    #[Computed]
    public function attentionCount(): int
    {
        return app(StockOverviewService::class)->attention($this->locations)->count();
    }

    /**
     * @return Collection<int, Item>
     */
    #[Computed]
    public function items(): Collection
    {
        return Item::query()->active()->orderBy('name')->get();
    }

    public function openAdjust(?int $locationId = null, ?int $itemId = null): void
    {
        $this->resetErrorBag();
        $this->reset('adjustQty', 'adjustReason');
        $this->adjustDirection = 'in';
        $this->adjustLocationId = $locationId;
        $this->adjustItemId = $itemId;
        $this->modal('stock-adjust')->show();
    }

    #[Computed]
    public function adjustCurrent(): ?string
    {
        return $this->adjustLocationId && $this->adjustItemId
            ? app(StockService::class)->balance($this->adjustLocationId, $this->adjustItemId)
            : null;
    }

    public function adjust(StockAdjustmentService $service): void
    {
        $validated = $this->validate([
            'adjustLocationId' => ['required', 'integer', Rule::exists('locations', 'id')],
            'adjustItemId' => ['required', 'integer', Rule::exists('items', 'id')],
            'adjustDirection' => ['required', Rule::in(['in', 'out'])],
            'adjustQty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'adjustReason' => ['required', 'string', 'max:500'],
        ], attributes: [
            'adjustLocationId' => 'lokasi',
            'adjustItemId' => 'bahan',
            'adjustQty' => 'jumlah',
            'adjustReason' => 'alasan',
        ]);

        $location = Location::findOrFail($validated['adjustLocationId']);
        $item = Item::findOrFail($validated['adjustItemId']);
        $this->authorize('adjustStock', $location);

        $signed = $validated['adjustDirection'] === 'out' ? Qty::neg($validated['adjustQty']) : $validated['adjustQty'];

        $movement = $this->guardInventory(fn () => $service->adjust($location, $item, $signed, $validated['adjustReason'], auth()->user()), 'adjustQty');

        $this->modal('stock-adjust')->close();
        unset($this->matrix, $this->attentionCount);
        $this->dispatch('notify', message: "Stok {$item->name} di {$location->name} sekarang ".qty($movement->balance_after)." {$item->unit}.");
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Stok" description="Bandingkan persediaan gudang dan setiap outlet dalam satu tampilan.">
        <flux:button icon="adjustments-horizontal" wire:click="openAdjust">Sesuaikan stok</flux:button>
        <flux:button variant="primary" icon="plus" :href="route('owner.requests.create')" wire:navigate>Buat permintaan</flux:button>
    </x-page-header>

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Monitor stok lintas lokasi</h2>
                <p class="text-sm text-muted">Klik angka untuk membuka kartu stok.</p>
            </div>
            <div class="flex items-center gap-1.5 text-sm text-brown">
                <flux:icon name="exclamation-triangle" class="size-4" />
                {{ $this->attentionCount }} bahan perlu perhatian
            </div>
        </div>

        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama bahan..." class="md:max-w-sm" aria-label="Cari bahan" />
            <div class="flex flex-wrap items-center gap-3 md:ms-auto">
                <flux:select wire:model.live="categoryFilter" aria-label="Filter kategori" class="w-48!">
                    <flux:select.option value="">Semua kategori</flux:select.option>
                    @foreach (ItemCategory::options() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:checkbox wire:model.live="attentionOnly" label="Hanya yang perlu perhatian" />
            </div>
        </div>

        @if ($this->matrix->isEmpty())
            <x-empty-state icon="archive-box" title="Tidak ada bahan" description="Ubah kata kunci atau filter." />
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Bahan</th>
                    <th>Satuan</th>
                    @foreach ($this->locations as $location)
                        <th>{{ $location->name }}</th>
                    @endforeach
                </x-slot:head>

                @foreach ($this->matrix as $row)
                    <tr wire:key="item-{{ $row['item']->id }}">
                        <td class="font-medium">{{ $row['item']->name }}</td>
                        <td class="text-muted">{{ $row['item']->unit }}</td>
                        @foreach ($this->locations as $location)
                            @php $cell = $row['cells'][$location->id]; @endphp
                            <td>
                                <a href="{{ route('owner.stock.card', [$location, $row['item']]) }}" wire:navigate class="group inline-flex flex-col gap-1">
                                    <span @class([
                                        'font-semibold group-hover:underline',
                                        'text-danger' => $cell['tracked'] && $cell['level'] === StockLevel::Out,
                                        'text-brown' => $cell['level'] === StockLevel::Low,
                                    ])>{{ qty($cell['qty']) }}</span>
                                    @if ($cell['tracked'] && $cell['level']->needsAttention())
                                        <x-status-badge :variant="$cell['level']->badge()" class="text-xs">{{ $cell['level']->label() }}</x-status-badge>
                                    @endif
                                    @if ($cell['min'] !== null)
                                        <span class="text-xs text-muted">min. {{ qty($cell['min']) }}</span>
                                    @endif
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>{{ $this->matrix->count() }} jenis bahan · {{ $this->locations->count() }} lokasi</span>
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    <flux:modal name="stock-adjust" class="w-full max-w-lg">
        <form wire:submit="adjust" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Sesuaikan stok</flux:heading>
                <flux:text class="mt-1">Untuk koreksi hasil hitung fisik. Tercatat di kartu stok beserta alasannya.</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="adjustLocationId" label="Lokasi *" placeholder="Pilih lokasi">
                    @foreach ($this->locations as $location)
                        <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="adjustItemId" label="Bahan *" placeholder="Pilih bahan">
                    @foreach ($this->items as $item)
                        <flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($this->adjustCurrent !== null)
                <div class="rounded-lg bg-canvas p-3 text-sm">
                    Stok saat ini: <span class="font-semibold">{{ qty($this->adjustCurrent) }} {{ $this->items->firstWhere('id', $adjustItemId)?->unit }}</span>
                </div>
            @endif

            <flux:radio.group wire:model="adjustDirection" label="Jenis *" variant="segmented">
                <flux:radio value="in" label="Tambah" icon="plus" />
                <flux:radio value="out" label="Kurangi" icon="minus" />
            </flux:radio.group>

            <flux:input wire:model="adjustQty" label="Jumlah *" type="number" min="0" step="0.001" inputmode="decimal" />

            <flux:textarea wire:model="adjustReason" label="Alasan *" rows="2" placeholder="Mis. hasil hitung fisik 12 Nov berbeda." />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Simpan penyesuaian</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
