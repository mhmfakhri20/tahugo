<?php

use App\Models\Purchase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Daftar pembelian bahan baku (INV-3).
 */
new #[Title('Pembelian')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $until = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Purchase::class);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function purchases(): LengthAwarePaginator
    {
        return Purchase::query()
            ->with(['items.item', 'user:id,name', 'location:id,name'])
            ->when($this->search !== '', fn ($query) => $query->where('supplier', 'like', "%{$this->search}%"))
            ->when($this->from !== '', fn ($query) => $query->whereDate('purchase_date', '>=', $this->from))
            ->when($this->until !== '', fn ($query) => $query->whereDate('purchase_date', '<=', $this->until))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(15);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pembelian" description="Catat bahan yang dibeli. Stok gudang bertambah otomatis.">
        @can('create', Purchase::class)
            <flux:button variant="primary" icon="plus" :href="route('warehouse.purchases.create')" wire:navigate>Catat pembelian</flux:button>
        @endcan
    </x-page-header>

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari supplier..." class="md:max-w-sm" aria-label="Cari supplier" />
            <div class="grid grid-cols-2 gap-3 md:ms-auto">
                <flux:input type="date" wire:model.live="from" label="Dari tanggal" />
                <flux:input type="date" wire:model.live="until" label="Sampai tanggal" />
            </div>
        </div>

        @if ($this->purchases->isEmpty())
            <x-empty-state icon="shopping-bag" title="Belum ada pembelian" description="Pembelian bahan yang dicatat akan tampil di sini.">
                @can('create', Purchase::class)
                    <flux:button :href="route('warehouse.purchases.create')" wire:navigate>Catat pembelian</flux:button>
                @endcan
            </x-empty-state>
        @else
            <x-data-table>
                <x-slot:head>
                    <th>Nomor / tanggal</th>
                    <th>Supplier</th>
                    <th>Bahan</th>
                    <th>Total</th>
                    <th>Nota</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->purchases as $purchase)
                    <tr wire:key="purchase-{{ $purchase->id }}">
                        <td>
                            <div class="font-medium">{{ $purchase->number() }}</div>
                            <div class="text-xs text-muted">{{ $purchase->purchase_date->translatedFormat('d M Y') }} · {{ $purchase->user->name }}</div>
                        </td>
                        <td>{{ $purchase->supplier }}</td>
                        <td class="text-sm">{{ $purchase->items->map(fn ($line) => $line->item->name.' '.qty($line->qty).' '.$line->item->unit)->implode(' · ') }}</td>
                        <td class="font-semibold">{{ rupiah($purchase->total) }}</td>
                        <td>
                            @if ($purchase->receipt_photo_path)
                                <x-status-badge variant="success">Ada</x-status-badge>
                            @else
                                <x-status-badge variant="muted">Tidak ada</x-status-badge>
                            @endif
                        </td>
                        <td class="text-end">
                            <flux:button variant="ghost" size="sm" :href="route('warehouse.purchases.show', $purchase)" wire:navigate>Detail</flux:button>
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->purchases->count() }} dari {{ $this->purchases->total() }} pembelian</span>
                    {{ $this->purchases->links() }}
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>
</div>
