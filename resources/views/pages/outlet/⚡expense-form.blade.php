<?php

use App\Enums\FundSource;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Services\CashService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Catat / ubah pengeluaran outlet (CASH-1). Foto nota wajib dari kamera langsung saat mencatat
 * (P3: mencegah nota palsu dari galeri). Mengubah hanya untuk milik sendiri, masih menunggu, shift masih buka (BR-6).
 */
new #[Title('Catat pengeluaran')] class extends Component {
    use HandlesInventoryErrors, WithFileUploads;

    #[Locked]
    public ?int $entryId = null;

    public ?string $categoryId = null;

    public string $amount = '';

    public string $description = '';

    public string $fundSource = 'outlet_cash';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $receipt = null;

    public function mount(?CashEntry $cashEntry = null): void
    {
        if ($cashEntry?->exists) {
            $this->authorize('update', $cashEntry->load('shift'));

            $this->entryId = $cashEntry->id;
            $this->categoryId = (string) $cashEntry->category_id;
            $this->amount = (string) $cashEntry->amount;
            $this->description = $cashEntry->description;
            $this->fundSource = $cashEntry->fund_source->value;
        } else {
            $this->authorize('createOutlet', CashEntry::class);
        }
    }

    #[Computed]
    public function entry(): ?CashEntry
    {
        return $this->entryId ? CashEntry::find($this->entryId) : null;
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function save(CashService $cash): void
    {
        $validated = $this->validate([
            'categoryId' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.CashService::MAX_AMOUNT],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'fundSource' => ['required', 'in:outlet_cash,personal'],
            'receipt' => [$this->entryId ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'receipt.required' => 'Foto nota wajib. Ambil foto nota dengan kamera.',
        ], [
            'categoryId' => 'kategori',
            'amount' => 'nominal',
            'description' => 'keterangan',
            'fundSource' => 'sumber dana',
            'receipt' => 'foto nota',
        ]);

        $data = [
            'category_id' => $validated['categoryId'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'fund_source' => $validated['fundSource'],
        ];

        $entry = $this->guardInventory(fn () => $this->entryId
            ? $cash->updateExpense(CashEntry::findOrFail($this->entryId), auth()->user(), $data, $this->receipt)
            : $cash->recordExpense(auth()->user(), $data, $this->receipt), 'form');

        session()->flash('success', $this->entryId
            ? "Pengeluaran {$entry->number()} diperbarui."
            : "Pengeluaran {$entry->number()} terkirim. Menunggu verifikasi pemilik.");

        $this->redirectRoute('outlet.expenses', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <div>
        <a href="{{ route('outlet.expenses') }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
            <flux:icon name="arrow-left" class="size-5" /> Pengeluaran
        </a>
        <h1 class="text-[26px] leading-tight font-bold">{{ $entryId ? 'Ubah pengeluaran' : 'Catat pengeluaran' }}</h1>
        <p class="text-base text-muted">Pengeluaran baru dihitung sebagai biaya setelah disetujui pemilik.</p>
    </div>

    <form wire:submit="save" class="flex flex-col gap-5">
        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4">
            <flux:select wire:model="categoryId" label="Kategori *" placeholder="Pilih kategori">
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="amount" type="number" min="1" inputmode="numeric" label="Nominal *" placeholder="0">
                <x-slot name="iconLeading"><span class="text-base text-muted">Rp</span></x-slot>
            </flux:input>

            <flux:textarea wire:model="description" label="Keterangan *" rows="2" placeholder="Mis. beli cabai 1/4 kg di pasar" />

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-2 font-medium">Sumber dana *</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([\App\Enums\FundSource::OutletCash, \App\Enums\FundSource::Personal] as $fund)
                        <label @class([
                            'flex min-h-14 cursor-pointer items-center justify-center gap-2 rounded-lg border-2 px-3 text-center text-base font-semibold',
                            'border-primary bg-soft/60' => $fundSource === $fund->value,
                            'border-border bg-surface' => $fundSource !== $fund->value,
                        ])>
                            <input type="radio" wire:model.live="fundSource" value="{{ $fund->value }}" class="sr-only" />
                            <flux:icon :name="$fund === \App\Enums\FundSource::OutletCash ? 'banknotes' : 'user'" class="size-5 text-brown" />
                            {{ $fund->label() }}
                        </label>
                    @endforeach
                </div>
                <p class="text-sm text-muted">
                    {{ $fundSource === 'personal' ? 'Uang pribadi tidak mengurangi uang di laci (dicatat sebagai talangan).' : 'Kas outlet mengurangi uang di laci.' }}
                </p>
                <flux:error name="fundSource" />
            </fieldset>
        </section>

        <section class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
            @if ($this->entry?->receipt_photo_path)
                <div class="flex items-center gap-3">
                    <img src="{{ route('cash-entries.receipt', $this->entry) }}" alt="Foto nota saat ini" class="size-16 rounded-lg border border-border object-cover" />
                    <p class="text-base text-muted">Foto nota saat ini. Ambil foto baru hanya bila perlu diganti.</p>
                </div>
            @endif
            <x-camera-capture field="receipt" facing="environment" :label="$entryId ? 'Foto nota baru (opsional)' : 'Foto nota'" :required="! $entryId" />
            @error('receipt')
                <x-inline-error>{{ $message }}</x-inline-error>
            @enderror
        </section>

        @error('form')
            <x-inline-error>{{ $message }}</x-inline-error>
        @enderror

        <button type="submit" wire:loading.attr="disabled" wire:target="save,receipt" class="flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B] disabled:bg-border disabled:text-muted">
            <flux:icon name="paper-airplane" class="size-6" /> {{ $entryId ? 'Simpan perubahan' : 'Kirim pengeluaran' }}
        </button>
    </form>
</div>
