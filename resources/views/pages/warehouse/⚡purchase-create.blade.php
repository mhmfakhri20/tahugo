<?php

use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Item;
use App\Models\Location;
use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Support\Qty;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Catat pembelian bahan baku (INV-3): stok gudang bertambah (purchase_in) dan harga beli terakhir diperbarui.
 */
new #[Title('Catat pembelian')] class extends Component {
    use HandlesInventoryErrors, WithFileUploads;

    public ?int $warehouseId = null;

    public string $supplier = '';

    public string $purchaseDate = '';

    /** @var list<array{item_id: int|string|null, qty: string, unit_price: string}> */
    public array $lines = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $receipt = null;

    public function mount(): void
    {
        $this->authorize('create', Purchase::class);

        $this->warehouseId = $this->warehouses->first()?->id;
        $this->purchaseDate = now()->toDateString();

        $item = Item::query()->active()->find(request()->integer('item'));
        $this->lines = [[
            'item_id' => $item?->id,
            'qty' => '',
            'unit_price' => $item ? (string) $item->last_purchase_price : '',
        ]];
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function warehouses(): Collection
    {
        return Location::query()->warehouses()->active()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Item>
     */
    #[Computed]
    public function items(): Collection
    {
        return Item::query()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function total(): int
    {
        return collect($this->lines)->sum(fn (array $line) => is_numeric($line['qty']) && is_numeric($line['unit_price'])
            ? Qty::money($line['qty'], (int) $line['unit_price'])
            : 0);
    }

    /**
     * Prefill the unit price with the last purchase price when an item is picked.
     */
    public function updatedLines(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            return;
        }

        [$index, $field] = explode('.', $key) + [null, null];

        if ($field === 'item_id' && blank($this->lines[$index]['unit_price'] ?? null)) {
            $price = $this->items->firstWhere('id', (int) $value)?->last_purchase_price;
            $this->lines[$index]['unit_price'] = $price ? (string) $price : '';
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => null, 'qty' => '', 'unit_price' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->resetErrorBag();
    }

    public function save(PurchaseService $service): void
    {
        $this->authorize('create', Purchase::class);

        $validated = $this->validate([
            'warehouseId' => ['required', 'integer', Rule::exists('locations', 'id')->where('type', 'warehouse')->where('is_active', true)],
            'supplier' => ['required', 'string', 'max:255'],
            'purchaseDate' => ['required', 'date', 'before_or_equal:today'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('is_active', true)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'lines.*.item_id.distinct' => 'Bahan ini sudah ada. Gabungkan jumlahnya di satu baris.',
            'lines.*.item_id.exists' => 'Pilih bahan yang masih aktif.',
            'purchaseDate.before_or_equal' => 'Tanggal pembelian tidak boleh setelah hari ini.',
        ], [
            'warehouseId' => 'gudang',
            'purchaseDate' => 'tanggal pembelian',
            'lines.*.item_id' => 'bahan',
            'lines.*.qty' => 'jumlah',
            'lines.*.unit_price' => 'harga satuan',
            'receipt' => 'foto nota',
        ]);

        $purchase = $this->guardInventory(fn () => $service->record(
            Location::findOrFail($validated['warehouseId']),
            ['supplier' => $validated['supplier'], 'purchase_date' => $validated['purchaseDate']],
            array_values($validated['lines']),
            $this->receipt,
            auth()->user(),
        ));

        session()->flash('success', "Pembelian {$purchase->number()} tersimpan. Stok gudang sudah bertambah.");

        $this->redirectRoute('warehouse.purchases.show', $purchase, navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <a href="{{ route('warehouse.purchases') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-muted hover:text-text">
            <flux:icon name="arrow-left" class="size-4" /> Pembelian
        </a>
        <x-page-header class="mt-2" title="Catat pembelian" description="Stok gudang bertambah dan harga beli terakhir bahan diperbarui." />
    </div>

    <form wire:submit="save" class="grid items-start gap-6 lg:grid-cols-3">
        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-text">Bahan yang dibeli</h2>

            @foreach ($lines as $index => $line)
                @php $item = $this->items->firstWhere('id', (int) $line['item_id']); @endphp
                <div wire:key="line-{{ $index }}" class="grid gap-3 rounded-xl border border-border p-4 sm:grid-cols-[1fr_140px_160px_auto] sm:items-start">
                    <flux:select wire:model.live="lines.{{ $index }}.item_id" label="Bahan *" placeholder="Pilih bahan">
                        @foreach ($this->items as $option)
                            <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model.live.debounce.400ms="lines.{{ $index }}.qty" label="Jumlah *" type="number" min="0" step="0.001" inputmode="decimal">
                        <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $item?->unit ?? 'satuan' }}</span></x-slot>
                    </flux:input>
                    <flux:input wire:model.live.debounce.400ms="lines.{{ $index }}.unit_price" label="Harga satuan *" type="number" min="0" inputmode="numeric">
                        <x-slot name="iconLeading"><span class="text-sm text-muted">Rp</span></x-slot>
                    </flux:input>
                    <div class="flex items-end sm:h-full sm:pt-6">
                        @if (count($lines) > 1)
                            <flux:button variant="ghost" icon="trash" class="text-danger!" wire:click="removeLine({{ $index }})" aria-label="Hapus baris" />
                        @endif
                    </div>
                </div>
            @endforeach

            <div>
                <flux:button icon="plus" wire:click="addLine">Tambah bahan</flux:button>
            </div>
        </section>

        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold text-text">Informasi pembelian</h2>

            @if ($this->warehouses->count() > 1)
                <flux:select wire:model="warehouseId" label="Gudang *">
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option :value="$warehouse->id">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <div>
                    <div class="text-sm text-muted">Gudang</div>
                    <div class="font-semibold">{{ $this->warehouses->first()?->name ?? 'Belum ada gudang aktif' }}</div>
                </div>
            @endif

            <flux:input wire:model="supplier" label="Supplier *" placeholder="Mis. Pak Darto (pabrik tahu)" />
            <flux:input wire:model="purchaseDate" label="Tanggal pembelian *" type="date" :max="now()->toDateString()" />

            <div class="flex flex-col gap-2">
                <flux:input type="file" wire:model="receipt" label="Foto nota" accept="image/*" description:trailing="Opsional. JPG/PNG, maks. 5 MB." />
                <div wire:loading wire:target="receipt" class="text-sm text-muted">Mengunggah foto...</div>
                @if ($receipt && ! $errors->has('receipt') && method_exists($receipt, 'isPreviewable') && $receipt->isPreviewable())
                    <img src="{{ $receipt->temporaryUrl() }}" alt="Pratinjau nota" class="max-h-48 rounded-lg border border-border object-contain" />
                @endif
            </div>

            <div class="flex items-center justify-between rounded-lg bg-canvas p-3">
                <span class="text-muted">Total</span>
                <span class="text-xl font-bold">{{ rupiah($this->total) }}</span>
            </div>

            @error('inventory')
                <x-inline-error>{{ $message }}</x-inline-error>
            @enderror

            <div class="flex justify-end gap-2">
                <flux:button :href="route('warehouse.purchases')" wire:navigate>Batal</flux:button>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="receipt,save">Simpan pembelian</flux:button>
            </div>
        </section>
    </form>
</div>
