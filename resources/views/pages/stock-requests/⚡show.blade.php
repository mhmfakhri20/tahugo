<?php

use App\Enums\RequestStatus;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\StockRequest;
use App\Services\StockRequestService;
use App\Services\StockService;
use App\Support\Qty;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detail permintaan stok + aksi sesuai peran & status (INV-5..INV-8):
 * pemilik setujui/tolak/batalkan, admin gudang kirim, penjaga outlet konfirmasi terima.
 */
new #[Title('Permintaan stok')] class extends Component {
    use HandlesInventoryErrors;

    #[Locked]
    public int $requestId;

    /** @var array<int|string, string> stock_request_item_id => qty */
    public array $qtys = [];

    public string $actionNote = '';

    public string $rejectReason = '';

    public function mount(StockRequest $stockRequest): void
    {
        $this->authorize('view', $stockRequest);

        $this->requestId = $stockRequest->id;
        $this->fillQuantities();
    }

    #[Computed]
    public function request(): StockRequest
    {
        return StockRequest::query()
            ->with(['items.item', 'fromLocation', 'toLocation', 'requester', 'approver', 'rejecter', 'shipper', 'receiver', 'canceller'])
            ->findOrFail($this->requestId);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function warehouseStock(): array
    {
        return app(StockService::class)->balancesFor($this->request->from_location_id);
    }

    /**
     * Which form is shown: approve | ship | receive | null.
     */
    #[Computed]
    public function mode(): ?string
    {
        $user = auth()->user();

        return match (true) {
            $user->can('approve', $this->request) => 'approve',
            $user->can('ship', $this->request) => 'ship',
            $user->can('receive', $this->request) => 'receive',
            default => null,
        };
    }

    /**
     * @return list<array{label: string, by: ?string, at: ?\Carbon\CarbonInterface, note: ?string, variant: string}>
     */
    #[Computed]
    public function timeline(): array
    {
        $r = $this->request;

        return array_values(array_filter([
            ['label' => 'Diajukan', 'by' => $r->requester->name, 'at' => $r->created_at, 'note' => $r->note, 'variant' => 'muted'],
            $r->approved_at ? ['label' => 'Disetujui', 'by' => $r->approver?->name, 'at' => $r->approved_at, 'note' => null, 'variant' => 'success'] : null,
            $r->rejected_at ? ['label' => 'Ditolak', 'by' => $r->rejecter?->name, 'at' => $r->rejected_at, 'note' => $r->reject_reason, 'variant' => 'danger'] : null,
            $r->shipped_at ? ['label' => 'Dikirim', 'by' => $r->shipper?->name, 'at' => $r->shipped_at, 'note' => $r->ship_note, 'variant' => 'info'] : null,
            $r->received_at ? ['label' => 'Diterima', 'by' => $r->receiver?->name, 'at' => $r->received_at, 'note' => $r->receive_note, 'variant' => 'success'] : null,
            $r->cancelled_at ? ['label' => 'Dibatalkan', 'by' => $r->canceller?->name, 'at' => $r->cancelled_at, 'note' => null, 'variant' => 'muted'] : null,
        ]));
    }

    public function approve(StockRequestService $service): void
    {
        $this->authorize('approve', $this->request);
        $this->validateQuantities();

        $this->guardInventory(fn () => $service->approve($this->request, $this->qtys, auth()->user()));

        $this->finish("Permintaan {$this->request->number} disetujui. Gudang akan memprosesnya.");
    }

    public function confirmReject(): void
    {
        $this->authorize('reject', $this->request);

        $this->reset('rejectReason');
        $this->resetErrorBag();
        $this->modal('request-reject')->show();
    }

    public function reject(StockRequestService $service): void
    {
        $this->authorize('reject', $this->request);

        $this->validate(['rejectReason' => ['required', 'string', 'max:500']], attributes: ['rejectReason' => 'alasan penolakan']);

        $this->guardInventory(fn () => $service->reject($this->request, $this->rejectReason, auth()->user()), 'rejectReason');

        $this->modal('request-reject')->close();
        $this->finish("Permintaan {$this->request->number} ditolak.");
    }

    public function ship(StockRequestService $service): void
    {
        $this->authorize('ship', $this->request);
        $this->validateQuantities();

        $this->guardInventory(fn () => $service->ship($this->request, $this->qtys, $this->actionNote, auth()->user()));

        $this->finish("Pengiriman {$this->request->number} dicatat. Stok gudang sudah dikurangi.");
    }

    public function receive(StockRequestService $service): void
    {
        $this->authorize('receive', $this->request);
        $this->validateQuantities();

        $this->guardInventory(fn () => $service->receive($this->request, $this->qtys, $this->actionNote, auth()->user()));

        $this->finish('Kiriman diterima. Stok outlet sudah bertambah.');
    }

    public function confirmCancel(): void
    {
        $this->authorize('cancel', $this->request);
        $this->modal('request-cancel')->show();
    }

    public function cancel(StockRequestService $service): void
    {
        $this->authorize('cancel', $this->request);

        $this->guardInventory(fn () => $service->cancel($this->request, auth()->user()));

        $this->modal('request-cancel')->close();
        $this->finish("Permintaan {$this->request->number} dibatalkan.");
    }

    /**
     * Read-only view (no action for this user) still renders inside a form element.
     */
    public function noop(): void {}

    private function validateQuantities(): void
    {
        $this->validate([
            'qtys' => ['array'],
            'qtys.*' => ['required', 'numeric', 'min:0', 'max:999999'],
            'actionNote' => ['nullable', 'string', 'max:500'],
        ], attributes: ['qtys.*' => 'jumlah', 'actionNote' => 'catatan']);
    }

    private function finish(string $message): void
    {
        unset($this->request, $this->mode, $this->timeline, $this->warehouseStock);
        $this->reset('actionNote');
        $this->fillQuantities();
        $this->dispatch('notify', message: $message);
    }

    /**
     * Prefill the form with the quantity of the previous step.
     */
    private function fillQuantities(): void
    {
        $this->qtys = [];

        foreach ($this->request->items as $line) {
            $value = match ($this->request->status) {
                RequestStatus::Pending => $line->qty_requested,
                RequestStatus::Approved => $line->qty_approved,
                RequestStatus::Shipped => $line->qty_shipped,
                default => null,
            };

            $this->qtys[$line->id] = $value !== null ? (string) (float) $value : '';
        }
    }
}; ?>

@php
    $r = $this->request;
    $isOutlet = auth()->user()->isOutletStaff();
    $area = auth()->user()->role->area();
    $backUrl = match ($area) {
        'owner' => route('owner.verification'),
        'warehouse' => route('warehouse.shipments'),
        default => route('outlet.stock', ['tab' => 'requests']),
    };
    $mode = $this->mode;
    $qtyLabel = ['approve' => 'Disetujui', 'ship' => 'Dikirim', 'receive' => 'Diterima'][$mode] ?? null;
@endphp

<div class="flex flex-col gap-6">
    <div>
        <a href="{{ $backUrl }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted hover:text-text sm:min-h-0 sm:text-sm">
            <flux:icon name="arrow-left" class="size-4" /> Kembali
        </a>
        <div @class(['mt-1 flex flex-col gap-3', 'sm:flex-row sm:items-end sm:justify-between' => ! $isOutlet])>
            <div>
                <h1 class="text-[26px] leading-tight font-bold text-text lg:text-3xl">{{ $r->number }}</h1>
                <p class="mt-1 text-base text-muted">{{ $r->fromLocation->name }} → {{ $r->toLocation->name }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :variant="$r->status->badge()" class="text-base">{{ $r->status->label() }}</x-status-badge>
                @if ($r->status === \App\Enums\RequestStatus::Received && $r->hasDiscrepancy())
                    <x-status-badge variant="warning" class="text-base">Ada selisih kirim-terima</x-status-badge>
                @endif
            </div>
        </div>
    </div>

    {{-- Penjaga outlet: kontainer mobile sempit, jadi selalu bertumpuk. --}}
    <div @class(['grid items-start gap-6', 'lg:grid-cols-3' => ! $isOutlet])>
        <form wire:submit="{{ $mode ?? 'noop' }}" @class(['flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6', 'lg:col-span-2' => ! $isOutlet])>
            <div>
                <h2 class="text-lg font-semibold text-text">
                    @switch($mode)
                        @case('approve') Tinjau permintaan @break
                        @case('ship') Siapkan pengiriman @break
                        @case('receive') Konfirmasi barang diterima @break
                        @default Rincian bahan
                    @endswitch
                </h2>
                <p @class(['text-muted', 'text-base' => $isOutlet, 'text-sm' => ! $isOutlet])>
                    @switch($mode)
                        @case('approve') Ubah jumlah bila perlu. Isi 0 untuk bahan yang tidak disetujui. @break
                        @case('ship') Jumlah kirim tidak boleh melebihi stok gudang. Bila kurang dari yang disetujui, tulis catatan. @break
                        @case('receive') Hitung barang yang datang. Bila jumlahnya berbeda, isi sesuai yang benar-benar diterima. @break
                        @default Jumlah di setiap tahap permintaan.
                    @endswitch
                </p>
            </div>

            @if ($isOutlet)
                {{-- Mobile: satu kartu per bahan --}}
                <ul class="flex flex-col gap-3">
                    @foreach ($r->items as $line)
                        <li wire:key="line-{{ $line->id }}" class="flex flex-col gap-3 rounded-xl border border-border p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="font-semibold">{{ $line->item->name }}</div>
                                @if ($line->hasReceiveDiscrepancy())
                                    <x-status-badge variant="warning">Selisih {{ qty($line->receiveDifference()) }}</x-status-badge>
                                @endif
                            </div>
                            <dl class="grid grid-cols-2 gap-2 text-base">
                                <div><dt class="text-sm text-muted">Diminta</dt><dd>{{ qty($line->qty_requested) }} {{ $line->item->unit }}</dd></div>
                                @if ($line->qty_approved !== null)
                                    <div><dt class="text-sm text-muted">Disetujui</dt><dd>{{ qty($line->qty_approved) }} {{ $line->item->unit }}</dd></div>
                                @endif
                                @if ($line->qty_shipped !== null)
                                    <div><dt class="text-sm text-muted">Dikirim</dt><dd>{{ qty($line->qty_shipped) }} {{ $line->item->unit }}</dd></div>
                                @endif
                                @if ($line->qty_received !== null)
                                    <div><dt class="text-sm text-muted">Diterima</dt><dd>{{ qty($line->qty_received) }} {{ $line->item->unit }}</dd></div>
                                @endif
                            </dl>
                            @if ($mode)
                                <flux:input wire:model="qtys.{{ $line->id }}" :label="$qtyLabel.' *'" type="number" min="0" step="0.001" inputmode="decimal">
                                    <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $line->item->unit }}</span></x-slot>
                                </flux:input>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <x-data-table>
                    <x-slot:head>
                        <th>Bahan</th>
                        <th>Diminta</th>
                        <th>Disetujui</th>
                        <th>Dikirim</th>
                        <th>Diterima</th>
                        @if ($mode === 'ship')
                            <th>Stok gudang</th>
                        @endif
                        @if ($mode)
                            <th class="w-44">{{ $qtyLabel }} *</th>
                        @endif
                    </x-slot:head>

                    @foreach ($r->items as $line)
                        @php $stock = $this->warehouseStock[$line->item_id] ?? '0'; @endphp
                        <tr wire:key="line-{{ $line->id }}">
                            <td>
                                <div class="font-medium">{{ $line->item->name }}</div>
                                @if ($line->hasReceiveDiscrepancy())
                                    <x-status-badge variant="warning" class="mt-1">Selisih {{ qty($line->receiveDifference()) }} {{ $line->item->unit }}</x-status-badge>
                                @elseif ($line->isShortShipped())
                                    <x-status-badge variant="warning" class="mt-1">Dikirim kurang</x-status-badge>
                                @endif
                            </td>
                            <td>{{ qty($line->qty_requested) }} {{ $line->item->unit }}</td>
                            <td>{{ $line->qty_approved !== null ? qty($line->qty_approved).' '.$line->item->unit : '—' }}</td>
                            <td>{{ $line->qty_shipped !== null ? qty($line->qty_shipped).' '.$line->item->unit : '—' }}</td>
                            <td>{{ $line->qty_received !== null ? qty($line->qty_received).' '.$line->item->unit : '—' }}</td>
                            @if ($mode === 'ship')
                                <td>
                                    <div>{{ qty($stock) }} {{ $line->item->unit }}</div>
                                    @if (Qty::cmp($stock, $line->qty_approved) >= 0)
                                        <x-status-badge variant="success">Cukup</x-status-badge>
                                    @else
                                        <x-status-badge variant="danger">Kurang</x-status-badge>
                                    @endif
                                </td>
                            @endif
                            @if ($mode)
                                <td>
                                    <flux:input wire:model="qtys.{{ $line->id }}" type="number" min="0" step="0.001" inputmode="decimal" size="sm" :aria-label="$qtyLabel.' '.$line->item->name">
                                        <x-slot name="iconTrailing"><span class="text-sm text-muted">{{ $line->item->unit }}</span></x-slot>
                                    </flux:input>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </x-data-table>
            @endif

            @if ($mode === 'ship')
                <flux:textarea wire:model="actionNote" label="Catatan pengiriman" rows="3" description:trailing="Wajib bila jumlah kirim kurang dari yang disetujui." />
            @elseif ($mode === 'receive')
                <flux:textarea wire:model="actionNote" label="Catatan penerimaan" rows="3" description:trailing="Wajib bila jumlah yang diterima berbeda dengan yang dikirim." />
            @endif

            @if ($errors->has('qtys.*'))
                <x-inline-error>{{ $errors->first('qtys.*') }}</x-inline-error>
            @endif
            @error('inventory')
                <x-inline-error>{{ $message }}</x-inline-error>
            @enderror

            @if ($mode)
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    @if ($mode === 'approve')
                        <flux:button variant="ghost" icon="x-mark" class="btn-danger" wire:click="confirmReject">Tolak</flux:button>
                    @endif
                    <flux:button type="submit" variant="primary" :icon="$mode === 'ship' ? 'truck' : 'check'" class="min-h-12 sm:min-h-0">
                        @switch($mode)
                            @case('approve') Setujui permintaan @break
                            @case('ship') Catat pengiriman @break
                            @case('receive') Konfirmasi terima @break
                        @endswitch
                    </flux:button>
                </div>
            @elseif ($r->status === \App\Enums\RequestStatus::Pending && ! auth()->user()->isOwner())
                <div class="flex items-center gap-2 rounded-lg bg-canvas p-3 text-muted">
                    <flux:icon name="lock-closed" class="size-5 shrink-0" />
                    <span>Menunggu persetujuan pemilik.</span>
                </div>
            @endif
        </form>

        <aside class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Tahapan</h2>
                <p @class(['text-muted', 'text-base' => $isOutlet, 'text-sm' => ! $isOutlet])>Siapa dan kapan setiap status berubah.</p>
            </div>

            <ol class="flex flex-col gap-4">
                @foreach ($this->timeline as $step)
                    <li class="flex flex-col gap-1">
                        <div><x-status-badge :variant="$step['variant']">{{ $step['label'] }}</x-status-badge></div>
                        <div @class(['text-text', 'text-base' => $isOutlet, 'text-sm' => ! $isOutlet])>{{ $step['by'] ?? '—' }} · {{ $step['at']?->translatedFormat('d M Y, H.i') }}</div>
                        @if ($step['note'])
                            <div @class(['rounded-lg bg-canvas p-3 text-muted', 'text-base' => $isOutlet, 'text-sm' => ! $isOutlet])>{{ $step['note'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ol>

            @can('cancel', $r)
                <flux:button variant="ghost" icon="no-symbol" class="btn-danger min-h-12 w-full" wire:click="confirmCancel">Batalkan permintaan</flux:button>
            @endcan
        </aside>
    </div>

    <flux:modal name="request-reject" class="w-full max-w-md">
        <form wire:submit="reject" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Tolak permintaan?</flux:heading>
                <flux:text class="mt-1">Alasan akan terlihat oleh penjaga outlet.</flux:text>
            </div>
            <flux:textarea wire:model="rejectReason" label="Alasan penolakan *" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="ghost" icon="x-mark" class="btn-danger">Tolak permintaan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="request-cancel" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Batalkan permintaan?</flux:heading>
                <flux:text class="mt-1">Permintaan {{ $r->number }} tidak akan diproses gudang. Tindakan ini tidak bisa dibatalkan.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button>Kembali</flux:button>
                </flux:modal.close>
                <flux:button variant="ghost" icon="no-symbol" class="btn-danger" wire:click="cancel">Batalkan permintaan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
