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
    <div>
        <flux:heading size="xl" level="1">Halo, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1 text-base">Ringkasan stok, penjualan, dan absensi akan tampil di sini.</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->locations as $location)
            <flux:card wire:key="location-{{ $location->id }}">
                <flux:badge size="sm" :color="$location->isOutlet() ? 'lime' : 'sky'">{{ $location->type->label() }}</flux:badge>
                <flux:heading size="lg" class="mt-2">{{ $location->name }}</flux:heading>
                <flux:text>Kode: {{ $location->code }}</flux:text>
            </flux:card>
        @endforeach
    </div>
</div>
