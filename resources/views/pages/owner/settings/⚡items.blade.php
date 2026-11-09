<?php

use App\Enums\ItemCategory;
use App\Models\Item;
use App\Models\Location;
use App\Services\ItemService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengaturan')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $categoryFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $unit = '';

    public string $category = '';

    public string $last_purchase_price = '0';

    /** @var array<int|string, string> location_id => minimum qty */
    public array $minStocks = [];

    public ?int $targetId = null;

    public function mount(): void
    {
        // Semua peran boleh membaca bahan, tapi halaman pengelolaan ini khusus pemilik.
        $this->authorize('create', Item::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Item::query()
            ->with('minStocks.location')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category', $this->categoryFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15);
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
    public function target(): ?Item
    {
        return $this->targetId ? Item::find($this->targetId) : null;
    }

    public function create(): void
    {
        $this->authorize('create', Item::class);

        $this->resetForm();
        $this->modal('item-form')->show();
    }

    public function edit(int $id): void
    {
        $item = Item::with('minStocks')->findOrFail($id);
        $this->authorize('update', $item);

        $this->resetForm();
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->unit = $item->unit;
        $this->category = $item->category->value;
        $this->last_purchase_price = (string) $item->last_purchase_price;

        foreach ($item->minStocks as $minStock) {
            $this->minStocks[$minStock->location_id] = (string) (float) $minStock->min_qty;
        }

        $this->modal('item-form')->show();
    }

    public function save(ItemService $service): void
    {
        $item = $this->editingId ? Item::findOrFail($this->editingId) : null;
        $item ? $this->authorize('update', $item) : $this->authorize('create', Item::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('items', 'name')->ignore($this->editingId)],
            'unit' => ['required', 'string', 'max:20'],
            'category' => ['required', Rule::enum(ItemCategory::class)],
            'last_purchase_price' => ['required', 'integer', 'min:0'],
            'minStocks' => ['array'],
            'minStocks.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], attributes: [
            'unit' => 'satuan',
            'category' => 'kategori',
            'last_purchase_price' => 'harga beli terakhir',
            'minStocks.*' => 'stok minimum',
        ]);

        $minStocks = collect($this->locations->modelKeys())
            ->mapWithKeys(fn (int $id) => [$id => $validated['minStocks'][$id] ?? null])
            ->all();

        $item = $service->save($item, $validated + ['is_active' => $item?->is_active ?? true], $minStocks);

        $this->modal('item-form')->close();
        $this->resetForm();
        $this->dispatch('notify', message: "{$item->name} tersimpan.");
    }

    public function confirmToggle(int $id): void
    {
        $item = Item::findOrFail($id);
        $this->authorize('update', $item);

        $this->targetId = $item->id;
        $this->modal('item-toggle')->show();
    }

    public function toggleActive(ItemService $service): void
    {
        $item = Item::findOrFail($this->targetId);
        $this->authorize('update', $item);

        $service->setActive($item, ! $item->is_active);

        $this->modal('item-toggle')->close();
        $this->dispatch('notify', message: $item->is_active ? "{$item->name} diaktifkan." : "{$item->name} dinonaktifkan.");
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'name', 'unit', 'category', 'last_purchase_price', 'minStocks');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pengaturan" description="Kelola pengguna, lokasi, bahan baku, produk, dan kategori pengeluaran." />

    <x-settings-tabs />

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Bahan baku</h2>
                <p class="text-sm text-muted">Bahan yang disimpan di gudang dan outlet. Stok minimum dipakai untuk tanda stok menipis.</p>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah bahan</flux:button>
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
                <flux:select wire:model.live="statusFilter" aria-label="Filter status">
                    <flux:select.option value="">Semua status</flux:select.option>
                    <flux:select.option value="active">Aktif</flux:select.option>
                    <flux:select.option value="inactive">Nonaktif</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if ($this->items->isEmpty())
            <x-empty-state icon="cube" title="Bahan tidak ditemukan" description="Ubah kata kunci atau filter, atau tambah bahan baru.">
                <flux:button wire:click="create">Tambah bahan</flux:button>
            </x-empty-state>
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Bahan</th>
                    <th>Kategori</th>
                    <th>Harga beli terakhir</th>
                    <th>Stok minimum</th>
                    <th>Status</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->items as $item)
                    <tr wire:key="item-{{ $item->id }}">
                        <td>
                            <div class="font-medium">{{ $item->name }}</div>
                            <div class="text-xs text-muted">Satuan: {{ $item->unit }}</div>
                        </td>
                        <td>{{ $item->category->label() }}</td>
                        <td>{{ rupiah($item->last_purchase_price) }}<span class="text-muted"> / {{ $item->unit }}</span></td>
                        <td>
                            @if ($item->minStocks->isEmpty())
                                <span class="text-muted">Belum diatur</span>
                            @else
                                <span class="text-sm">{{ $item->minStocks->map(fn ($min) => $min->location->code.' '.qty($min->min_qty))->implode(' · ') }}</span>
                            @endif
                        </td>
                        <td>
                            <x-status-badge :variant="$item->is_active ? 'success' : 'muted'">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                        </td>
                        <td class="text-end whitespace-nowrap">
                            <flux:button variant="ghost" size="sm" wire:click="edit({{ $item->id }})">Ubah</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="confirmToggle({{ $item->id }})" @class(['text-danger!' => $item->is_active])>
                                {{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </flux:button>
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->items->count() }} dari {{ $this->items->total() }} bahan</span>
                    {{ $this->items->links() }}
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    <flux:modal name="item-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah bahan' : 'Tambah bahan' }}</flux:heading>

            <flux:input wire:model="name" label="Nama bahan *" />

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input wire:model.live.debounce.300ms="unit" label="Satuan *" list="unit-options" placeholder="pcs" description:trailing="Mis. pcs, kg, gram, liter." />
                <datalist id="unit-options">
                    @foreach (['pcs', 'kg', 'gram', 'pack', 'liter', 'sachet'] as $option)
                        <option value="{{ $option }}"></option>
                    @endforeach
                </datalist>

                <flux:select wire:model="category" label="Kategori *" placeholder="Pilih kategori">
                    @foreach (ItemCategory::options() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="last_purchase_price" label="Harga beli terakhir *" type="number" min="0" inputmode="numeric" description:trailing="Per satuan. Nanti terisi otomatis dari pembelian.">
                    <x-slot name="iconLeading"><span class="text-sm text-muted">Rp</span></x-slot>
                </flux:input>
            </div>

            <div class="flex flex-col gap-3">
                <div>
                    <flux:heading>Stok minimum per lokasi</flux:heading>
                    <flux:text class="text-xs">Kosongkan bila tidak perlu diawasi di lokasi tersebut.</flux:text>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->locations as $location)
                        <flux:input
                            wire:key="min-{{ $location->id }}"
                            wire:model="minStocks.{{ $location->id }}"
                            :label="$location->name"
                            type="number"
                            min="0"
                            step="0.001"
                            inputmode="decimal"
                        >
                            <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $unit ?: 'satuan' }}</span></x-slot>
                        </flux:input>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="item-toggle" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ $this->target?->is_active ? 'Nonaktifkan bahan?' : 'Aktifkan bahan?' }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($this->target?->is_active)
                        {{ $this->target?->name }} tidak bisa dipilih lagi untuk resep, pembelian, dan permintaan baru. Riwayat lama tetap tersimpan.
                    @else
                        {{ $this->target?->name }} bisa dipakai lagi.
                    @endif
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                @if ($this->target?->is_active)
                    <flux:button variant="ghost" icon="no-symbol" class="btn-danger" wire:click="toggleActive">Nonaktifkan</flux:button>
                @else
                    <flux:button variant="primary" wire:click="toggleActive">Aktifkan</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
