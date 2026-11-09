<?php

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * Active outlets & warehouses (dashboard widgets arrive in F6).
     *
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::where('is_active', true)->orderBy('type')->orderBy('name')->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-greeting :name="auth()->user()->name" description="Pantau usaha Anda hari ini dari satu tempat." />

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
            title="Ringkasan penjualan & stok segera hadir"
            description="Total penjualan, stok yang perlu perhatian, dan absensi tiap cabang akan tampil di sini."
        />
    </div>
</div>
