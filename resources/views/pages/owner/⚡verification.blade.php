<?php

use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\RequestStatus;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\CashEntry;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\CashService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Verifikasi pemilik (§11). F2: persetujuan permintaan bahan (INV-5).
 * Tab pengeluaran diisi di F5.
 */
new #[Title('Verifikasi')] class extends Component {
    use HandlesInventoryErrors, WithPagination;

    #[Url(except: 'requests')]
    public string $tab = 'requests';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $outletFilter = '';

    #[Url(except: 'pending')]
    public string $statusFilter = 'pending';

    // Pengeluaran outlet (CASH-3, CASH-4)
    #[Url(except: 'pending')]
    public string $expenseStatus = 'pending';

    #[Url(except: '')]
    public string $expenseOutlet = '';

    #[Url(except: '')]
    public string $expenseFrom = '';

    #[Url(except: '')]
    public string $expenseTo = '';

    #[Locked]
    public ?int $reviewId = null;

    public string $rejectReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockRequest::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'outletFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }

        if (in_array($property, ['expenseStatus', 'expenseOutlet', 'expenseFrom', 'expenseTo'], true)) {
            $this->resetPage('expensesPage');
        }
    }

    /**
     * Outlet expenses (out + shift), filtered by outlet & date (not by status).
     */
    private function expenseQuery(): Builder
    {
        $date = fn (string $value) => rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', $value), null, false);

        return CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->whereNotNull('shift_id')
            ->when($this->expenseOutlet !== '', fn ($q) => $q->where('location_id', $this->expenseOutlet))
            ->when($this->expenseFrom !== '' && $date($this->expenseFrom), fn ($q) => $q->where('created_at', '>=', $date($this->expenseFrom)->startOfDay()))
            ->when($this->expenseTo !== '' && $date($this->expenseTo), fn ($q) => $q->where('created_at', '<=', $date($this->expenseTo)->endOfDay()));
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function expenseCounts(): array
    {
        return $this->expenseQuery()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Metric cards (all outlets, independent of the filters).
     *
     * @return array{pending_total: int, pending_count: int, today_total: int, today_count: int, rejected_total: int, rejected_count: int}
     */
    #[Computed]
    public function expenseMetrics(): array
    {
        $base = fn () => CashEntry::query()->where('direction', CashDirection::Out)->whereNotNull('shift_id');
        $today = [today(), today()->endOfDay()];

        return [
            'pending_total' => (int) $base()->where('status', ExpenseStatus::Pending)->sum('amount'),
            'pending_count' => $base()->where('status', ExpenseStatus::Pending)->count(),
            'today_total' => (int) $base()->where('status', ExpenseStatus::Approved)->whereBetween('verified_at', $today)->sum('amount'),
            'today_count' => $base()->where('status', ExpenseStatus::Approved)->whereBetween('verified_at', $today)->count(),
            'rejected_total' => (int) $base()->where('status', ExpenseStatus::Rejected)->where('verified_at', '>=', today()->subDays(6))->sum('amount'),
            'rejected_count' => $base()->where('status', ExpenseStatus::Rejected)->where('verified_at', '>=', today()->subDays(6))->count(),
        ];
    }

    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        return $this->expenseQuery()
            ->with(['location', 'user', 'category'])
            ->when($this->expenseStatus !== '', fn ($q) => $q->where('status', $this->expenseStatus))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(15, pageName: 'expensesPage');
    }

    #[Computed]
    public function reviewing(): ?CashEntry
    {
        return $this->reviewId
            ? CashEntry::with(['location', 'user', 'category', 'shift.opener', 'verifier'])->find($this->reviewId)
            : null;
    }

    public function review(int $id): void
    {
        $entry = CashEntry::findOrFail($id);
        $this->authorize('view', $entry);

        $this->reviewId = $entry->id;
        $this->rejectReason = '';
        $this->resetErrorBag();
        unset($this->reviewing);

        Flux::modal('expense-review')->show();
    }

    public function approveExpense(CashService $cash): void
    {
        $entry = $this->guardInventory(fn () => $cash->approve(CashEntry::findOrFail($this->reviewId), auth()->user()), 'review');

        $this->afterVerification("Pengeluaran {$entry->number()} disetujui dan masuk laporan.");
    }

    public function rejectExpense(CashService $cash): void
    {
        $this->validate(
            ['rejectReason' => ['required', 'string', 'min:3', 'max:500']],
            ['rejectReason.required' => 'Alasan wajib diisi bila pengeluaran ditolak.'],
            ['rejectReason' => 'alasan penolakan'],
        );

        $entry = $this->guardInventory(fn () => $cash->reject(CashEntry::findOrFail($this->reviewId), auth()->user(), $this->rejectReason), 'rejectReason');

        $this->afterVerification("Pengeluaran {$entry->number()} ditolak. Penjaga akan melihat alasannya.");
    }

    private function afterVerification(string $message): void
    {
        Flux::modal('expense-review')->close();
        $this->reset('reviewId', 'rejectReason');
        unset($this->expenses, $this->expenseCounts, $this->expenseMetrics, $this->reviewing);
        $this->dispatch('notify', message: $message);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return StockRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Location::query()->outlets()->orderBy('name')->get();
    }

    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return StockRequest::query()
            ->with(['items.item', 'toLocation', 'requester'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'like', "%{$this->search}%")
                ->orWhereHas('requester', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))))
            ->when($this->outletFilter !== '', fn ($query) => $query->where('to_location_id', $this->outletFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(15);
    }
}; ?>

@php
    $counts = $this->counts;
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Verifikasi" description="Tinjau pengeluaran dan permintaan bahan dari outlet.">
        @if ($tab === 'expenses')
            <flux:button icon="building-office" :href="route('owner.business-expenses')" wire:navigate>Pengeluaran usaha</flux:button>
        @else
            <flux:button variant="primary" icon="plus" :href="route('owner.requests.create')" wire:navigate>Buat permintaan</flux:button>
        @endif
    </x-page-header>

    <nav class="-mx-1 overflow-x-auto border-b border-border" aria-label="Jenis verifikasi">
        <ul class="flex min-w-max gap-6 px-1">
            @foreach (['requests' => 'Permintaan bahan', 'expenses' => 'Pengeluaran'] as $key => $label)
                <li>
                    <button
                        type="button"
                        wire:click="$set('tab', '{{ $key }}')"
                        @if ($tab === $key) aria-current="page" @endif
                        @class([
                            '-mb-px flex h-12 items-center gap-2 border-b-2 text-sm',
                            'border-primary font-semibold text-text' => $tab === $key,
                            'border-transparent text-muted hover:text-text' => $tab !== $key,
                        ])
                    >
                        {{ $label }}
                        @if ($key === 'requests' && ($counts['pending'] ?? 0) > 0)
                            <span class="rounded-full bg-primary px-2 text-xs font-semibold text-[#38332B]">{{ $counts['pending'] }}</span>
                        @endif
                        @if ($key === 'expenses' && $this->expenseMetrics['pending_count'] > 0)
                            <span class="rounded-full bg-primary px-2 text-xs font-semibold text-[#38332B]">{{ $this->expenseMetrics['pending_count'] }}</span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($tab === 'expenses')
        @php
            $metrics = $this->expenseMetrics;
            $expenseCounts = $this->expenseCounts;
        @endphp
        <div class="grid gap-4 sm:grid-cols-3">
            <x-metric-card label="Menunggu verifikasi" :value="rupiah($metrics['pending_total'])" icon="clipboard-document-list" :hint="$metrics['pending_count'].' pengeluaran perlu ditinjau'" />
            <x-metric-card label="Disetujui hari ini" :value="rupiah($metrics['today_total'])" icon="check-circle" :hint="$metrics['today_count'].' pengeluaran masuk laporan'" />
            <x-metric-card label="Ditolak 7 hari" :value="$metrics['rejected_count'].' pengeluaran'" icon="x-circle" :hint="rupiah($metrics['rejected_total']).' harus dipertanggungjawabkan'" />
        </div>

        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Antrean pengeluaran</h2>
                <p class="text-sm text-muted">Pilih pengeluaran untuk meninjau foto nota dan detailnya.</p>
            </div>

            <nav class="-mx-1 overflow-x-auto border-b border-border" aria-label="Status pengeluaran">
                <ul class="flex min-w-max gap-6 px-1">
                    @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', '' => 'Semua'] as $value => $label)
                        <li>
                            <button
                                type="button"
                                wire:click="$set('expenseStatus', '{{ $value }}')"
                                @class([
                                    '-mb-px flex h-11 items-center border-b-2 text-sm',
                                    'border-primary font-semibold text-text' => $expenseStatus === $value,
                                    'border-transparent text-muted hover:text-text' => $expenseStatus !== $value,
                                ])
                            >{{ $label }} ({{ $value === '' ? array_sum($expenseCounts) : ($expenseCounts[$value] ?? 0) }})</button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <flux:select wire:model.live="expenseOutlet" aria-label="Filter cabang">
                    <flux:select.option value="">Semua cabang</flux:select.option>
                    @foreach ($this->outlets as $outlet)
                        <flux:select.option :value="$outlet->id">{{ $outlet->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live="expenseFrom" type="date" aria-label="Dari tanggal" :max="now()->toDateString()" />
                <flux:input wire:model.live="expenseTo" type="date" aria-label="Sampai tanggal" :max="now()->toDateString()" />
            </div>

            @if ($this->expenses->isEmpty())
                <x-empty-state icon="receipt-percent" title="Tidak ada pengeluaran" description="Belum ada pengeluaran outlet dengan filter ini." />
            @else
                <x-data-table>
                    <x-slot:head>
                        <th>ID / tanggal</th>
                        <th>Cabang / penjaga</th>
                        <th>Kategori</th>
                        <th>Nominal</th>
                        <th>Sumber dana</th>
                        <th>Status</th>
                        <th class="text-end!">Aksi</th>
                    </x-slot:head>

                    @foreach ($this->expenses as $expense)
                        @php $badge = $expense->statusBadge(); @endphp
                        <tr wire:key="exp-{{ $expense->id }}">
                            <td>
                                <div class="font-medium">{{ $expense->number() }}</div>
                                <div class="text-xs text-muted">{{ $expense->created_at->translatedFormat('d M · H.i') }} WIB</div>
                            </td>
                            <td>
                                <div>{{ $expense->location?->name }}</div>
                                <div class="text-xs text-muted">{{ $expense->user->name }}</div>
                            </td>
                            <td>
                                <div>{{ $expense->category?->name }}</div>
                                <div class="max-w-48 truncate text-xs text-muted">{{ $expense->description }}</div>
                            </td>
                            <td class="font-medium whitespace-nowrap">{{ rupiah($expense->amount) }}</td>
                            <td>{{ $expense->fund_source?->label() }}</td>
                            <td><x-status-badge :variant="$badge['variant']">{{ $badge['label'] }}</x-status-badge></td>
                            <td class="text-end whitespace-nowrap">
                                <flux:button :variant="$expense->isPending() ? 'primary' : 'ghost'" size="sm" wire:click="review({{ $expense->id }})">
                                    {{ $expense->isPending() ? 'Tinjau' : 'Detail' }}
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        <span>Menampilkan {{ $this->expenses->count() }} dari {{ $this->expenses->total() }} pengeluaran</span>
                        {{ $this->expenses->links() }}
                    </x-slot:footer>
                </x-data-table>
            @endif
        </section>

        {{-- Tinjau pengeluaran (CASH-4) --}}
        <flux:modal name="expense-review" class="w-full max-w-3xl">
            @if ($entry = $this->reviewing)
                @php $badge = $entry->statusBadge(); @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-start justify-between gap-3 pe-8">
                        <div>
                            <flux:heading size="lg">Tinjau {{ $entry->number() }}</flux:heading>
                            <p class="text-sm text-muted">Diajukan {{ $entry->user->name }} · {{ $entry->location?->name }} · {{ $entry->created_at->translatedFormat('d F Y, H.i') }} WIB</p>
                        </div>
                        <x-status-badge :variant="$badge['variant']">{{ $badge['label'] }}</x-status-badge>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="flex flex-col gap-2 rounded-xl bg-canvas p-3">
                            @if ($entry->receipt_photo_path)
                                <a href="{{ route('cash-entries.receipt', $entry) }}" target="_blank" rel="noopener" title="Buka ukuran asli">
                                    <img src="{{ route('cash-entries.receipt', $entry) }}" alt="Foto nota {{ $entry->number() }}" class="max-h-[60vh] w-full rounded-lg object-contain" />
                                </a>
                                <flux:button size="sm" icon="arrows-pointing-out" :href="route('cash-entries.receipt', $entry)" target="_blank">Buka bukti asli (zoom)</flux:button>
                            @else
                                <x-empty-state icon="photo" title="Tanpa foto nota" />
                            @endif
                        </div>

                        <div class="flex flex-col gap-4">
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <dt class="text-muted">Kategori</dt><dd class="text-end font-medium">{{ $entry->category?->name }}</dd>
                                <dt class="text-muted">Nominal</dt><dd class="text-end text-lg font-bold">{{ rupiah($entry->amount) }}</dd>
                                <dt class="text-muted">Sumber dana</dt><dd class="text-end font-medium">{{ $entry->fund_source?->label() }}</dd>
                                <dt class="text-muted">Penjaga</dt><dd class="text-end font-medium">{{ $entry->user->name }}</dd>
                                <dt class="text-muted">Shift</dt>
                                <dd class="text-end font-medium">
                                    {{ $entry->shift?->opened_at->translatedFormat('d M, H.i') }}
                                    · {{ $entry->shift?->isOpen() ? 'masih buka' : 'sudah ditutup' }}
                                </dd>
                            </dl>

                            <div>
                                <div class="mb-1 text-sm font-medium">Keterangan</div>
                                <div class="rounded-lg bg-canvas p-3 text-sm">{{ $entry->description }}</div>
                            </div>

                            @if ($entry->isPending())
                                <flux:textarea wire:model="rejectReason" label="Alasan penolakan" rows="2" placeholder="Wajib diisi bila ditolak. Mis. nota tidak terbaca." />
                                @error('review')
                                    <x-inline-error>{{ $message }}</x-inline-error>
                                @enderror
                                <p class="text-xs text-muted">Pengeluaran yang ditolak menjadi tanggungan penjaga dan menambah kekurangan kas shift.</p>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <flux:button variant="ghost" icon="x-mark" class="btn-danger" wire:click="rejectExpense">Tolak pengeluaran</flux:button>
                                    <flux:button variant="primary" icon="check" wire:click="approveExpense">Setujui</flux:button>
                                </div>
                            @else
                                <div @class(['rounded-lg p-3 text-sm', 'bg-success/10' => ! $entry->reject_reason, 'bg-danger/10 text-danger' => $entry->reject_reason])>
                                    {{ $entry->status->label() }} oleh {{ $entry->verifier?->name }} · {{ $entry->verified_at?->translatedFormat('d M, H.i') }}
                                    @if ($entry->reject_reason) — {{ $entry->reject_reason }} @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </flux:modal>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-metric-card label="Menunggu keputusan" :value="($counts['pending'] ?? 0).' permintaan'" icon="clipboard-document-list" hint="Perlu Anda tinjau" />
            <x-metric-card label="Disetujui, belum dikirim" :value="($counts['approved'] ?? 0).' permintaan'" icon="clipboard-document-check" :hint="($counts['shipped'] ?? 0).' sedang dikirim · '.($counts['received'] ?? 0).' diterima'" />
            <x-metric-card label="Ditolak" :value="($counts['rejected'] ?? 0).' permintaan'" icon="x-circle" hint="Alasan terlihat oleh outlet" />
        </div>

        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Daftar permintaan outlet</h2>
                <p class="text-sm text-muted">Persetujuan pemilik dan pengiriman gudang ditampilkan terpisah.</p>
            </div>

            <div class="flex flex-col gap-3 md:flex-row">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nomor atau pengaju..." class="md:max-w-sm" aria-label="Cari permintaan" />
                <div class="grid grid-cols-2 gap-3 md:ms-auto md:flex">
                    <flux:select wire:model.live="outletFilter" aria-label="Filter cabang">
                        <flux:select.option value="">Semua cabang</flux:select.option>
                        @foreach ($this->outlets as $outlet)
                            <flux:select.option :value="$outlet->id">{{ $outlet->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="statusFilter" aria-label="Filter status">
                        <flux:select.option value="">Semua status</flux:select.option>
                        @foreach (RequestStatus::options() as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            @if ($this->requests->isEmpty())
                <x-empty-state icon="clipboard-document-check" title="Tidak ada permintaan" description="Belum ada permintaan dengan filter ini. Ubah filter atau buat permintaan baru." />
            @else
                <x-data-table>
                    <x-slot:head>
                        <th>Permintaan / pengaju</th>
                        <th>Cabang</th>
                        <th>Bahan diminta</th>
                        <th>Status</th>
                        <th class="text-end!">Aksi</th>
                    </x-slot:head>

                    @foreach ($this->requests as $request)
                        <tr wire:key="req-{{ $request->id }}">
                            <td>
                                <div class="font-medium">{{ $request->number }}</div>
                                <div class="text-xs text-muted">{{ $request->requester->name }} · {{ $request->created_at->translatedFormat('d M, H.i') }}</div>
                            </td>
                            <td>{{ $request->toLocation->name }}</td>
                            <td class="text-sm">
                                {{ $request->items->map(fn ($line) => $line->item->name.' '.qty($line->qty_requested).' '.$line->item->unit)->implode(' · ') }}
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <x-status-badge :variant="$request->status->badge()">{{ $request->status->label() }}</x-status-badge>
                                    @if ($request->status === RequestStatus::Received && $request->hasDiscrepancy())
                                        <x-status-badge variant="warning">Selisih</x-status-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end whitespace-nowrap">
                                @if ($request->status === RequestStatus::Pending)
                                    <flux:button variant="primary" size="sm" :href="route('owner.requests.show', $request)" wire:navigate>Tinjau</flux:button>
                                @else
                                    <flux:button variant="ghost" size="sm" :href="route('owner.requests.show', $request)" wire:navigate>Detail</flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        <span>Menampilkan {{ $this->requests->count() }} dari {{ $this->requests->total() }} permintaan</span>
                        {{ $this->requests->links() }}
                    </x-slot:footer>
                </x-data-table>
            @endif
        </section>
    @endif
</div>
