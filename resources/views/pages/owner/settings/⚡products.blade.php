<?php

use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan')] class extends Component {
    public ?int $targetId = null;

    public function mount(): void
    {
        // Semua peran boleh membaca produk, tapi halaman pengelolaan ini khusus pemilik.
        $this->authorize('create', Product::class);
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->with('prices')
            ->withCount('recipes')
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function target(): ?Product
    {
        return $this->targetId ? Product::find($this->targetId) : null;
    }

    public function confirmToggle(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);

        $this->targetId = $product->id;
        $this->modal('product-toggle')->show();
    }

    public function toggleActive(ProductService $service): void
    {
        $product = Product::findOrFail($this->targetId);
        $this->authorize('update', $product);

        $service->setActive($product, ! $product->is_active);

        $this->modal('product-toggle')->close();
        $this->dispatch('notify', message: $product->is_active ? "{$product->name} diaktifkan." : "{$product->name} dinonaktifkan.");
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pengaturan" description="Kelola pengguna, lokasi, bahan baku, produk, dan kategori pengeluaran." />

    <x-settings-tabs />

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Produk & resep</h2>
                <p class="text-sm text-muted">Harga jual per kanal dan resep bahan untuk mengurangi stok otomatis saat terjual.</p>
            </div>
            <flux:button variant="primary" icon="plus" :href="route('owner.settings.products.create')" wire:navigate>Tambah produk</flux:button>
        </div>

        @if ($this->products->isEmpty())
            <x-empty-state icon="shopping-bag" title="Belum ada produk" description="Tambahkan produk yang dijual di outlet.">
                <flux:button :href="route('owner.settings.products.create')" wire:navigate>Tambah produk</flux:button>
            </x-empty-state>
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Produk</th>
                    <th>Tipe</th>
                    @foreach (SalesChannel::cases() as $channel)
                        <th>{{ $channel->label() }}</th>
                    @endforeach
                    <th>Resep</th>
                    <th>Status</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="font-medium">{{ $product->name }}</td>
                        <td>
                            <x-status-badge :variant="$product->type === ProductType::Paket ? 'soft' : 'muted'">{{ $product->type->label() }}</x-status-badge>
                        </td>
                        @foreach (SalesChannel::cases() as $channel)
                            <td class="whitespace-nowrap">{{ rupiah($product->priceFor($channel)) }}</td>
                        @endforeach
                        <td>
                            @if ($product->recipes_count > 0)
                                {{ $product->recipes_count }} bahan
                            @else
                                <x-status-badge variant="warning">Belum ada</x-status-badge>
                            @endif
                        </td>
                        <td>
                            <x-status-badge :variant="$product->is_active ? 'success' : 'muted'">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                        </td>
                        <td class="text-end whitespace-nowrap">
                            <flux:button variant="ghost" size="sm" :href="route('owner.settings.products.edit', $product)" wire:navigate>Ubah</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="confirmToggle({{ $product->id }})" @class(['text-danger!' => $product->is_active])>
                                {{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </flux:button>
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->products->count() }} produk</span>
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    <flux:modal name="product-toggle" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ $this->target?->is_active ? 'Nonaktifkan produk?' : 'Aktifkan produk?' }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($this->target?->is_active)
                        {{ $this->target?->name }} tidak akan tampil lagi di kasir. Riwayat penjualan tetap tersimpan.
                    @else
                        {{ $this->target?->name }} akan tampil lagi di kasir.
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
