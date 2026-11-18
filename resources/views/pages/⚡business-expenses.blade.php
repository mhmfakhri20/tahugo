<?php

use App\Enums\CashDirection;
use App\Enums\FundSource;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\Purchase;
use App\Services\CashService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/*
 * Pengeluaran usaha (CASH-6, CASH-7) untuk pemilik & admin gudang: sewa, gaji, listrik, dll.
 * Tidak terikat shift dan langsung disetujui. Pembelian bahan gudang otomatis tampil di sini.
 */
new #[Title('Pengeluaran usaha')] class extends Component {
    use HandlesInventoryErrors, WithFileUploads, WithPagination;

    public string $locationId = '';

    public string $categoryId = '';

    public string $amount = '';

    public string $description = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $receipt = null;

    public function mount(): void
    {
        $this->authorize('createBusiness', CashEntry::class);
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->active()->orderBy('type')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return CashEntry::query()
            ->with(['location', 'category', 'user'])
            ->where('direction', CashDirection::Out)
            ->where('fund_source', FundSource::Business)
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);
    }

    #[Computed]
    public function monthTotal(): int
    {
        return (int) CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->where('fund_source', FundSource::Business)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
    }

    public function save(CashService $cash): void
    {
        $validated = $this->validate([
            'locationId' => ['nullable', 'integer', 'exists:locations,id'],
            'categoryId' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.CashService::MAX_AMOUNT],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], [
            'locationId' => 'lokasi',
            'categoryId' => 'kategori',
            'amount' => 'nominal',
            'description' => 'keterangan',
            'receipt' => 'foto nota',
        ]);

        $entry = $this->guardInventory(fn () => $cash->recordBusinessExpense(auth()->user(), [
            'location_id' => $validated['locationId'] ?: null,
            'category_id' => $validated['categoryId'] ?: null,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
        ], $this->receipt), 'form');

        $this->reset('locationId', 'categoryId', 'amount', 'description', 'receipt');
        unset($this->entries, $this->monthTotal);
        $this->resetPage();
        $this->dispatch('notify', message: "Pengeluaran usaha {$entry->number()} tercatat.");
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pengeluaran usaha" description="Biaya tingkat usaha (sewa, gaji, listrik, dll.) dan pembelian bahan gudang. Tidak terikat shift." />

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <h2 class="text-lg font-semibold">Catat pengeluaran usaha</h2>

            <flux:select wire:model="locationId" label="Lokasi" description:trailing="Opsional. Kosongkan bila untuk seluruh usaha.">
                <flux:select.option value="">Seluruh usaha</flux:select.option>
                @foreach ($this->locations as $location)
                    <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="categoryId" label="Kategori">
                <flux:select.option value="">Tanpa kategori</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="amount" type="number" min="1" inputmode="numeric" label="Nominal *">
                <x-slot name="iconLeading"><span class="text-sm text-muted">Rp</span></x-slot>
            </flux:input>

            <flux:textarea wire:model="description" label="Keterangan *" rows="2" placeholder="Mis. sewa tempat outlet November" />

            <flux:input type="file" wire:model="receipt" label="Foto nota" accept="image/*" description:trailing="Opsional. JPG/PNG, maks. 5 MB." />

            @error('form')
                <x-inline-error>{{ $message }}</x-inline-error>
            @enderror

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save,receipt">Simpan</flux:button>
            </div>
        </form>

        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Riwayat pengeluaran usaha</h2>
                    <p class="text-sm text-muted">Termasuk pembelian bahan oleh gudang (otomatis).</p>
                </div>
                <div class="text-end">
                    <div class="text-sm text-muted">Bulan ini</div>
                    <div class="text-xl font-bold">{{ rupiah($this->monthTotal) }}</div>
                </div>
            </div>

            @if ($this->entries->isEmpty())
                <x-empty-state icon="building-office" title="Belum ada pengeluaran usaha" description="Pengeluaran yang dicatat akan tampil di sini." />
            @else
                <x-data-table>
                    <x-slot:head>
                        <th>ID / tanggal</th>
                        <th>Keterangan</th>
                        <th>Lokasi</th>
                        <th>Nominal</th>
                        <th class="text-end!">Nota</th>
                    </x-slot:head>
                    @foreach ($this->entries as $entry)
                        <tr wire:key="biz-{{ $entry->id }}">
                            <td>
                                <div class="font-medium">{{ $entry->number() }}</div>
                                <div class="text-xs text-muted">{{ $entry->created_at->translatedFormat('d M Y') }} · {{ $entry->user->name }}</div>
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1">
                                    @if ($entry->source_type === (new Purchase)->getMorphClass())
                                        <x-status-badge variant="info">Pembelian</x-status-badge>
                                    @elseif ($entry->category)
                                        <x-status-badge variant="soft">{{ $entry->category->name }}</x-status-badge>
                                    @endif
                                </div>
                                <div class="text-sm">{{ $entry->description }}</div>
                            </td>
                            <td>{{ $entry->location?->name ?? 'Seluruh usaha' }}</td>
                            <td class="font-medium whitespace-nowrap">{{ rupiah($entry->amount) }}</td>
                            <td class="text-end">
                                @if ($entry->receipt_photo_path && auth()->user()->can('viewReceipt', $entry))
                                    <flux:button variant="ghost" size="sm" :href="route('cash-entries.receipt', $entry)" target="_blank">Lihat</flux:button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <x-slot:footer>
                        <span>Menampilkan {{ $this->entries->count() }} dari {{ $this->entries->total() }} pengeluaran</span>
                        {{ $this->entries->links() }}
                    </x-slot:footer>
                </x-data-table>
            @endif
        </section>
    </div>
</div>
