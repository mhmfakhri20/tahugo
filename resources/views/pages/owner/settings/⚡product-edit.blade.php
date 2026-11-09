<?php

use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Models\Item;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Produk & resep')] class extends Component {
    #[Locked]
    public ?int $productId = null;

    public string $name = '';

    public string $type = '';

    /** @var array<string, string> channel => price (rupiah) */
    public array $prices = [];

    /** @var list<array{item_id: string|int|null, qty: string}> */
    public array $recipe = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->authorize('update', $product);

            $product->load('prices', 'recipes');

            $this->productId = $product->id;
            $this->name = $product->name;
            $this->type = $product->type->value;

            foreach (SalesChannel::cases() as $channel) {
                $this->prices[$channel->value] = (string) ($product->priceFor($channel) ?? '');
            }

            $this->recipe = $product->recipes
                ->map(fn ($line) => ['item_id' => $line->item_id, 'qty' => (string) (float) $line->qty])
                ->all();
        } else {
            $this->authorize('create', Product::class);

            $this->type = ProductType::Single->value;
            $this->prices = array_fill_keys(SalesChannel::values(), '');
        }
    }

    /**
     * Active items, plus inactive ones already in this recipe so they stay visible.
     *
     * @return Collection<int, Item>
     */
    #[Computed]
    public function items(): Collection
    {
        $usedIds = array_filter(array_column($this->recipe, 'item_id'));

        return Item::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $usedIds))
            ->orderBy('name')
            ->get();
    }

    public function addLine(): void
    {
        $this->recipe[] = ['item_id' => null, 'qty' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->recipe[$index]);
        $this->recipe = array_values($this->recipe);
        $this->resetErrorBag();
    }

    public function save(ProductService $service): void
    {
        $product = $this->productId ? Product::findOrFail($this->productId) : null;
        $product ? $this->authorize('update', $product) : $this->authorize('create', Product::class);

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($this->productId)],
            'type' => ['required', Rule::enum(ProductType::class)],
            'recipe' => ['array'],
            'recipe.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('is_active', true)],
            'recipe.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ];
        $attributes = [
            'type' => 'tipe',
            'recipe.*.item_id' => 'bahan',
            'recipe.*.qty' => 'jumlah',
        ];

        foreach (SalesChannel::cases() as $channel) {
            $rules["prices.{$channel->value}"] = ['required', 'integer', 'min:0', 'max:100000000'];
            $attributes["prices.{$channel->value}"] = "harga {$channel->label()}";
        }

        $validated = $this->validate($rules, [
            'recipe.*.item_id.distinct' => 'Bahan ini sudah ada di resep. Gabungkan jumlahnya di satu baris.',
            'recipe.*.item_id.exists' => 'Pilih bahan yang masih aktif.',
        ], $attributes);

        $product = $service->save(
            $product,
            ['name' => $validated['name'], 'type' => $validated['type'], 'is_active' => $product?->is_active ?? true],
            $validated['prices'],
            array_values($validated['recipe'] ?? []),
        );

        session()->flash('success', "{$product->name} tersimpan.");

        $this->redirectRoute('owner.settings.products', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <a href="{{ route('owner.settings.products') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-muted hover:text-text">
            <flux:icon name="arrow-left" class="size-4" /> Produk & resep
        </a>
        <x-page-header class="mt-2" :title="$productId ? 'Ubah produk' : 'Tambah produk'" description="Isi harga di semua kanal dan bahan yang dipakai untuk 1 produk." />
    </div>

    <form wire:submit="save" class="grid items-start gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            {{-- Informasi produk --}}
            <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-text">Informasi produk</h2>

                <div class="grid gap-5 sm:grid-cols-3">
                    <flux:input wire:model="name" label="Nama produk *" class="sm:col-span-2" />
                    <flux:select wire:model="type" label="Tipe *">
                        @foreach (ProductType::options() as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </section>

            {{-- Resep / BOM --}}
            <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold text-text">Resep (per 1 produk)</h2>
                    <p class="text-sm text-muted">Stok bahan di outlet berkurang sesuai resep ini setiap produk terjual.</p>
                </div>

                @if ($recipe === [])
                    <div class="flex items-start gap-3 rounded-xl bg-soft p-4 text-sm text-text">
                        <flux:icon name="exclamation-triangle" class="size-5 shrink-0 text-brown" />
                        <div>
                            <div class="font-semibold">Resep masih kosong</div>
                            <div>Stok tidak akan berkurang saat produk ini terjual. Tambahkan bahan yang dipakai.</div>
                        </div>
                    </div>
                @endif

                <div class="flex flex-col gap-4">
                    @foreach ($recipe as $index => $line)
                        @php $unit = $this->items->firstWhere('id', (int) $line['item_id'])?->unit; @endphp
                        <div wire:key="recipe-{{ $index }}" class="grid grid-cols-[1fr_auto] items-start gap-3 border-b border-border pb-4 sm:grid-cols-[1fr_12rem_auto]">
                            <flux:select wire:model.live="recipe.{{ $index }}.item_id" :label="$index === 0 ? 'Bahan *' : null" placeholder="Pilih bahan" aria-label="Bahan baris {{ $index + 1 }}" class="col-span-2 sm:col-span-1">
                                @foreach ($this->items as $item)
                                    <flux:select.option :value="$item->id">{{ $item->name }}{{ $item->is_active ? '' : ' (nonaktif)' }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input
                                wire:model="recipe.{{ $index }}.qty"
                                :label="$index === 0 ? 'Jumlah *' : null"
                                type="number"
                                min="0"
                                step="0.001"
                                inputmode="decimal"
                                aria-label="Jumlah baris {{ $index + 1 }}"
                            >
                                <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $unit ?? '' }}</span></x-slot>
                            </flux:input>

                            <flux:button
                                variant="ghost"
                                icon="trash"
                                class="btn-danger {{ $index === 0 ? 'sm:mt-7' : '' }}"
                                wire:click="removeLine({{ $index }})"
                                aria-label="Hapus bahan baris {{ $index + 1 }}"
                            />
                        </div>
                    @endforeach
                </div>

                <div>
                    <flux:button icon="plus" wire:click="addLine">Tambah bahan</flux:button>
                </div>
            </section>
        </div>

        {{-- Harga per kanal --}}
        <aside class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Harga per kanal</h2>
                <p class="text-sm text-muted">Wajib diisi semua. Harga aplikasi pesan-antar boleh berbeda.</p>
            </div>

            @foreach (SalesChannel::cases() as $channel)
                <flux:input wire:model="prices.{{ $channel->value }}" :label="$channel->label().' *'" type="number" min="0" inputmode="numeric">
                    <x-slot name="iconLeading"><span class="text-sm text-muted">Rp</span></x-slot>
                </flux:input>
            @endforeach

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <flux:button :href="route('owner.settings.products')" wire:navigate>Batal</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Simpan produk</flux:button>
            </div>
        </aside>
    </form>
</div>
