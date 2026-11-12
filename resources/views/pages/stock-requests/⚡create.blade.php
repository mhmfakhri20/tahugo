<?php

use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Item;
use App\Models\ItemMinStock;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\StockRequestService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Minta stok ke gudang (INV-4). Penjaga: untuk outletnya, status Menunggu persetujuan.
 * Pemilik: pilih outlet, permintaan langsung Disetujui (INV-5).
 */
new #[Title('Minta stok')] class extends Component {
    use HandlesInventoryErrors;

    public ?int $outletId = null;

    /** @var list<array{item_id: int|string|null, qty: string}> */
    public array $lines = [];

    public string $note = '';

    public function mount(): void
    {
        $this->authorize('create', StockRequest::class);

        $user = auth()->user();
        $this->outletId = $user->isOutletStaff() ? $user->location_id : null;

        $preselected = request()->integer('item');
        $this->lines = [['item_id' => $preselected ?: null, 'qty' => '']];
    }

    #[Computed]
    public function isOwner(): bool
    {
        return auth()->user()->isOwner();
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Location::query()->outlets()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function outlet(): ?Location
    {
        return $this->outletId ? Location::find($this->outletId) : null;
    }

    #[Computed]
    public function warehouse(): ?Location
    {
        return Location::query()->warehouses()->active()->orderBy('id')->first();
    }

    /**
     * @return Collection<int, Item>
     */
    #[Computed]
    public function items(): Collection
    {
        return Item::query()->active()->orderBy('name')->get();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function outletStock(): array
    {
        return $this->outletId ? app(StockService::class)->balancesFor($this->outletId) : [];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function warehouseStock(): array
    {
        return $this->warehouse ? app(StockService::class)->balancesFor($this->warehouse) : [];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function outletMin(): array
    {
        return $this->outletId
            ? ItemMinStock::query()->where('location_id', $this->outletId)->pluck('min_qty', 'item_id')->all()
            : [];
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => null, 'qty' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->resetErrorBag();
    }

    public function save(StockRequestService $service): void
    {
        $this->authorize('create', StockRequest::class);
        $user = auth()->user();

        // BR-7: outlet staff can only request for their own outlet, whatever the payload says.
        if ($user->isOutletStaff()) {
            $this->outletId = $user->location_id;
        }

        $validated = $this->validate([
            'outletId' => ['required', 'integer', Rule::exists('locations', 'id')->where('type', 'outlet')->where('is_active', true)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('items', 'id')->where('is_active', true)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'lines.*.item_id.distinct' => 'Bahan ini sudah dipilih. Gabungkan jumlahnya di satu baris.',
            'lines.*.item_id.exists' => 'Pilih bahan yang masih aktif.',
        ], [
            'outletId' => 'outlet tujuan',
            'lines.*.item_id' => 'bahan',
            'lines.*.qty' => 'jumlah',
            'note' => 'catatan',
        ]);

        $quantities = collect($validated['lines'])->mapWithKeys(fn (array $line) => [(int) $line['item_id'] => $line['qty']])->all();

        $request = $this->guardInventory(fn () => $service->create(
            Location::findOrFail($validated['outletId']),
            $quantities,
            $validated['note'] ?? null,
            $user,
            preApproved: $user->isOwner(),
        ));

        session()->flash('success', $user->isOwner()
            ? "Permintaan {$request->number} dibuat dan langsung disetujui. Gudang bisa segera mengirim."
            : "Permintaan {$request->number} terkirim. Tunggu persetujuan pemilik.");

        $this->redirectRoute($user->role->area().'.requests.show', $request, navigate: true);
    }
}; ?>

@php
    $isOutlet = auth()->user()->isOutletStaff();
    $backUrl = $isOutlet ? route('outlet.stock', ['tab' => 'requests']) : route('owner.verification');
@endphp

<div class="flex flex-col gap-6">
    <div>
        <a href="{{ $backUrl }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted hover:text-text sm:min-h-0 sm:text-sm">
            <flux:icon name="arrow-left" class="size-4" /> Kembali
        </a>
        <x-page-header
            class="mt-1"
            title="Minta stok ke gudang"
            :description="$this->isOwner ? 'Permintaan dari pemilik langsung disetujui dan masuk antrean kirim gudang.' : 'Permintaan dikirim ke pemilik untuk disetujui, lalu dikirim oleh gudang.'"
        />
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-border bg-surface p-4 sm:p-6">
        {{-- Dari gudang → ke outlet --}}
        <div class="flex items-center gap-3 rounded-xl bg-canvas p-4">
            <flux:icon name="archive-box" class="size-6 shrink-0 text-brown" />
            <div class="min-w-0 flex-1">
                <div class="text-sm text-muted">Dari</div>
                <div class="truncate font-semibold">{{ $this->warehouse?->name ?? 'Belum ada gudang aktif' }}</div>
            </div>
            <flux:icon name="arrow-right" class="size-5 shrink-0 text-brown" />
            <div class="min-w-0 flex-1 text-end">
                <div class="text-sm text-muted">Ke outlet</div>
                <div class="truncate font-semibold">{{ $this->outlet?->name ?? 'Pilih outlet' }}</div>
            </div>
        </div>

        @if ($this->isOwner)
            <flux:select wire:model.live="outletId" label="Outlet tujuan *" placeholder="Pilih outlet">
                @foreach ($this->outlets as $outlet)
                    <flux:select.option :value="$outlet->id">{{ $outlet->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="flex flex-col gap-4">
            <div>
                <flux:heading>Bahan yang diminta *</flux:heading>
                <flux:text @class(['text-base' => $isOutlet])>Tambah baris bila butuh lebih dari satu bahan.</flux:text>
            </div>

            @foreach ($lines as $index => $line)
                @php
                    $item = $this->items->firstWhere('id', (int) $line['item_id']);
                    $outletQty = $item ? ($this->outletStock[$item->id] ?? '0') : null;
                    $min = $item ? ($this->outletMin[$item->id] ?? null) : null;
                    $warehouseQty = $item ? ($this->warehouseStock[$item->id] ?? '0') : null;
                @endphp
                <div wire:key="line-{{ $index }}" class="flex flex-col gap-3 rounded-xl border border-border p-4">
                    <div class="grid gap-3 sm:grid-cols-[1fr_180px]">
                        <flux:select wire:model.live="lines.{{ $index }}.item_id" label="Bahan" placeholder="Pilih bahan">
                            @foreach ($this->items as $option)
                                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="lines.{{ $index }}.qty" label="Jumlah" type="number" min="0" step="0.001" inputmode="decimal">
                            <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $item?->unit ?? 'satuan' }}</span></x-slot>
                        </flux:input>
                    </div>

                    @if ($item)
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-soft/60 p-3">
                                <div class="text-sm text-brown">Stok outlet saat ini</div>
                                <div class="font-semibold text-text">{{ $this->outletId ? qty($outletQty).' '.$item->unit : '—' }}</div>
                                @if ($min !== null)
                                    <div class="text-sm text-muted">min. {{ qty($min) }} {{ $item->unit }}</div>
                                @endif
                            </div>
                            <div class="rounded-lg bg-canvas p-3">
                                <div class="text-sm text-muted">Tersedia di gudang</div>
                                <div class="font-semibold text-text">{{ qty($warehouseQty) }} {{ $item->unit }}</div>
                            </div>
                        </div>
                    @endif

                    @if (count($lines) > 1)
                        <div>
                            <flux:button variant="ghost" size="sm" icon="trash" class="text-danger!" wire:click="removeLine({{ $index }})">Hapus baris</flux:button>
                        </div>
                    @endif
                </div>
            @endforeach

            <div>
                <flux:button icon="plus" wire:click="addLine">Tambah bahan</flux:button>
            </div>
        </div>

        <flux:textarea wire:model="note" label="Catatan" rows="3" placeholder="Mis. stok tahu menipis, mohon dikirim sebelum outlet buka besok." />

        @error('inventory')
            <x-inline-error>{{ $message }}</x-inline-error>
        @enderror

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:button :href="$backUrl" wire:navigate class="min-h-12 sm:min-h-0">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="paper-airplane" class="min-h-12 sm:min-h-0">
                {{ $this->isOwner ? 'Buat & setujui' : 'Kirim permintaan' }}
            </flux:button>
        </div>
    </form>
</div>
