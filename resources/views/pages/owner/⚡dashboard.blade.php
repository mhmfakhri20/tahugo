<?php

use App\Enums\RequestStatus;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\StockOverviewService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * Active outlets & warehouses.
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::where('is_active', true)->orderBy('type')->orderBy('name')->get();
    }

    /**
     * INV-2: stock that needs attention is the top widget for the owner.
     */
    #[Computed]
    public function lowStock(): \Illuminate\Support\Collection
    {
        return app(StockOverviewService::class)->attention($this->locations);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return StockRequest::query()->inStatus(RequestStatus::Pending)->count();
    }

    /**
     * @return Collection<int, StockRequest>
     */
    #[Computed]
    public function pendingRequests(): Collection
    {
        return StockRequest::query()
            ->inStatus(RequestStatus::Pending)
            ->with(['items.item', 'toLocation', 'requester'])
            ->orderBy('id')
            ->limit(5)
            ->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-greeting :name="auth()->user()->name" description="Pantau usaha Anda hari ini dari satu tempat." />

    <div class="grid items-start gap-6 lg:grid-cols-2">
        {{-- Stok menipis (INV-2) --}}
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-text">Stok menipis</h2>
                    <p class="text-sm text-muted">Bahan di bawah stok minimum atau habis.</p>
                </div>
                @if ($this->lowStock->isNotEmpty())
                    <x-status-badge variant="warning">{{ $this->lowStock->count() }} bahan</x-status-badge>
                @endif
            </div>

            @if ($this->lowStock->isEmpty())
                <x-empty-state icon="check-circle" title="Semua stok aman" description="Tidak ada bahan di bawah batas minimum." />
            @else
                <ul class="flex flex-col divide-y divide-border">
                    @foreach ($this->lowStock->take(8) as $row)
                        <li wire:key="low-{{ $row['location']->id }}-{{ $row['item']->id }}">
                            <a href="{{ route('owner.stock.card', [$row['location'], $row['item']]) }}" wire:navigate class="flex items-center justify-between gap-3 py-3 hover:bg-canvas">
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $row['item']->name }}</div>
                                    <div class="truncate text-sm text-muted">{{ $row['location']->name }}</div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="text-sm font-semibold">{{ qty($row['qty']) }} {{ $row['item']->unit }}
                                        @if ($row['min'] !== null)<span class="font-normal text-muted">/ min. {{ qty($row['min']) }}</span>@endif
                                    </span>
                                    <x-status-badge :variant="$row['level']->badge()">{{ $row['level']->label() }}</x-status-badge>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div>
                    <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('owner.stock', ['attentionOnly' => 1])" wire:navigate>Lihat semua stok</flux:button>
                </div>
            @endif
        </section>

        {{-- Permintaan menunggu --}}
        <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-text">Permintaan menunggu</h2>
                    <p class="text-sm text-muted">Permintaan bahan dari outlet yang perlu Anda setujui.</p>
                </div>
                @if ($this->pendingCount > 0)
                    <x-status-badge variant="soft">{{ $this->pendingCount }} baru</x-status-badge>
                @endif
            </div>

            @if ($this->pendingRequests->isEmpty())
                <x-empty-state icon="clipboard-document-check" title="Tidak ada permintaan menunggu" description="Permintaan baru dari outlet akan muncul di sini." />
            @else
                <ul class="flex flex-col divide-y divide-border">
                    @foreach ($this->pendingRequests as $request)
                        <li wire:key="pending-{{ $request->id }}">
                            <a href="{{ route('owner.requests.show', $request) }}" wire:navigate class="flex items-center gap-3 py-3 hover:bg-canvas">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
                                    <flux:icon name="building-storefront" class="size-5 text-brown" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ $request->toLocation->name }}</div>
                                    <div class="truncate text-sm text-muted">{{ $request->items->map(fn ($line) => $line->item->name.' '.qty($line->qty_requested).' '.$line->item->unit)->implode(' · ') }}</div>
                                    <div class="text-xs text-muted">{{ $request->number }} · {{ $request->created_at->translatedFormat('d M, H.i') }}</div>
                                </div>
                                <flux:icon name="chevron-right" class="size-5 shrink-0 text-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div>
                    <flux:button variant="ghost" size="sm" icon:trailing="arrow-right" :href="route('owner.verification')" wire:navigate>Buka verifikasi</flux:button>
                </div>
            @endif
        </section>
    </div>

    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold text-text">Cabang & gudang aktif</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->locations as $location)
                <div wire:key="location-{{ $location->id }}" class="flex items-start justify-between gap-3 rounded-xl border border-border bg-surface p-5">
                    <div class="min-w-0">
                        <x-status-badge :variant="$location->isOutlet() ? 'soft' : 'muted'">{{ $location->type->label() }}</x-status-badge>
                        <div class="mt-3 truncate text-lg font-semibold text-text">{{ $location->name }}</div>
                        <div class="text-sm text-muted">Kode: {{ $location->code }}</div>
                    </div>
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
                        <flux:icon :name="$location->isOutlet() ? 'building-storefront' : 'archive-box'" class="size-5 text-brown" />
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <x-empty-state
            icon="chart-bar"
            title="Ringkasan penjualan segera hadir"
            description="Total penjualan, pengeluaran, dan absensi tiap cabang akan tampil di sini."
        />
    </div>
</div>
