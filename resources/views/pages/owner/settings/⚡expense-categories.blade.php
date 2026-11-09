<?php

use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('create', ExpenseCategory::class);
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', ExpenseCategory::class);

        $this->resetForm();
        $this->modal('category-form')->show();
    }

    public function edit(int $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $this->authorize('update', $category);

        $this->resetForm();
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->modal('category-form')->show();
    }

    public function save(ExpenseCategoryService $service): void
    {
        $category = $this->editingId ? ExpenseCategory::findOrFail($this->editingId) : null;
        $category ? $this->authorize('update', $category) : $this->authorize('create', ExpenseCategory::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($this->editingId)],
        ]);

        $category = $service->save($category, $validated + ['is_active' => $category?->is_active ?? true]);

        $this->modal('category-form')->close();
        $this->resetForm();
        $this->dispatch('notify', message: "Kategori {$category->name} tersimpan.");
    }

    /**
     * Toggling a category is harmless (old expenses keep their category), so no confirmation dialog.
     */
    public function toggleActive(int $id, ExpenseCategoryService $service): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $this->authorize('update', $category);

        $service->setActive($category, ! $category->is_active);

        $this->dispatch('notify', message: $category->is_active ? "Kategori {$category->name} diaktifkan." : "Kategori {$category->name} dinonaktifkan.");
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'name');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-page-header title="Pengaturan" description="Kelola pengguna, lokasi, bahan baku, produk, dan kategori pengeluaran." />

    <x-settings-tabs />

    <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text">Kategori pengeluaran</h2>
                <p class="text-sm text-muted">Pilihan kategori saat penjaga outlet mencatat pengeluaran.</p>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah kategori</flux:button>
        </div>

        @if ($this->categories->isEmpty())
            <x-empty-state icon="tag" title="Belum ada kategori" description="Tambahkan kategori seperti Gas/LPG atau Kebersihan.">
                <flux:button wire:click="create">Tambah kategori</flux:button>
            </x-empty-state>
        @else
            <x-data-table class="[&_table]:min-w-0">
                <x-slot:head>
                    <th>Nama kategori</th>
                    <th>Status</th>
                    <th class="text-end!">Aksi</th>
                </x-slot:head>

                @foreach ($this->categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td class="font-medium">{{ $category->name }}</td>
                        <td>
                            <x-status-badge :variant="$category->is_active ? 'success' : 'muted'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-status-badge>
                        </td>
                        <td class="text-end whitespace-nowrap">
                            <flux:button variant="ghost" size="sm" wire:click="edit({{ $category->id }})">Ubah</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $category->id }})" @class(['text-danger!' => $category->is_active])>
                                {{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </flux:button>
                        </td>
                    </tr>
                @endforeach

                <x-slot:footer>
                    <span>Menampilkan {{ $this->categories->count() }} kategori</span>
                </x-slot:footer>
            </x-data-table>
        @endif
    </section>

    <flux:modal name="category-form" class="w-full max-w-md">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? 'Ubah kategori' : 'Tambah kategori' }}</flux:heading>

            <flux:input wire:model="name" label="Nama kategori *" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
