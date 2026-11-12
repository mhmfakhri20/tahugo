<?php

use App\Models\Purchase;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail pembelian')] class extends Component {
    #[Locked]
    public int $purchaseId;

    public function mount(Purchase $purchase): void
    {
        $this->authorize('view', $purchase);
        $this->purchaseId = $purchase->id;
    }

    #[Computed]
    public function purchase(): Purchase
    {
        return Purchase::with(['items.item', 'user:id,name', 'location:id,name'])->findOrFail($this->purchaseId);
    }
}; ?>

@php $purchase = $this->purchase; @endphp

<div class="flex flex-col gap-6">
    <div>
        <a href="{{ route('warehouse.purchases') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-muted hover:text-text">
            <flux:icon name="arrow-left" class="size-4" /> Pembelian
        </a>
        <x-page-header class="mt-2" :title="'Pembelian '.$purchase->number()" :description="$purchase->supplier.' · '.$purchase->purchase_date->translatedFormat('d F Y')" />
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-text">Rincian bahan</h2>
            <x-data-table>
                <x-slot:head>
                    <th>Bahan</th>
                    <th class="text-end!">Jumlah</th>
                    <th class="text-end!">Harga satuan</th>
                    <th class="text-end!">Subtotal</th>
                </x-slot:head>
                @foreach ($purchase->items as $line)
                    <tr wire:key="line-{{ $line->id }}">
                        <td class="font-medium">{{ $line->item->name }}</td>
                        <td class="text-end">{{ qty($line->qty) }} {{ $line->item->unit }}</td>
                        <td class="text-end">{{ rupiah($line->unit_price) }}</td>
                        <td class="text-end">{{ rupiah($line->line_total) }}</td>
                    </tr>
                @endforeach
                <x-slot:footer>
                    <span>Dicatat {{ $purchase->user->name }} · {{ $purchase->created_at->translatedFormat('d M Y, H.i') }} · masuk ke {{ $purchase->location->name }}</span>
                    <span class="text-lg font-bold text-text">Total {{ rupiah($purchase->total) }}</span>
                </x-slot:footer>
            </x-data-table>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold text-text">Foto nota</h2>
            @if ($purchase->receipt_photo_path)
                <a href="{{ route('purchases.receipt', $purchase) }}" target="_blank" rel="noopener" class="block">
                    <img src="{{ route('purchases.receipt', $purchase) }}" alt="Foto nota {{ $purchase->number() }}" class="w-full rounded-lg border border-border object-contain" />
                </a>
                <flux:text>Ketuk foto untuk memperbesar.</flux:text>
            @else
                <x-empty-state icon="photo" title="Tanpa foto nota" description="Foto nota tidak diunggah untuk pembelian ini." />
            @endif
        </section>
    </div>
</div>
