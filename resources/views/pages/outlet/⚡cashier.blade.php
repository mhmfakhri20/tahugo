<?php

use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Kasir penjaga outlet (POS-1..9). Keranjang disimpan di browser (Alpine) supaya ketukan terasa cepat;
 * saat bayar hanya product_id + qty yang dikirim. Harga, total, dan stok dihitung ulang di server (SaleService).
 * Route ini berada di balik middleware `open-shift` (BR-4).
 */
new #[Title('Kasir')] class extends Component {
    use HandlesInventoryErrors;

    public function mount(): void
    {
        $this->authorize('create', Sale::class);
    }

    #[Computed]
    public function shift(): ?Shift
    {
        return app(ShiftService::class)->openShiftFor(auth()->user()->location);
    }

    /**
     * Product cards for Alpine (id, name, prices per channel, status).
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function products(): array
    {
        return app(SaleService::class)->catalog(auth()->user()->location)
            ->map(fn (array $row) => [
                'id' => $row['product']->id,
                'name' => $row['product']->name,
                'type' => $row['product']->type->label(),
                'prices' => $row['prices'],
                'status' => $row['status'],
            ])
            ->all();
    }

    /**
     * Save the sale. Only ids & quantities are trusted from the browser.
     *
     * @param  list<array{product_id: int|string, qty: int|string}>  $lines
     * @return array<string, mixed>|null summary for the success card
     */
    public function checkout(string $channel, array $lines, ?string $method = null, mixed $cashReceived = null, ?string $platformOrderId = null): ?array
    {
        $this->resetErrorBag();

        $channelEnum = SalesChannel::tryFrom($channel)
            ?? throw ValidationException::withMessages(['checkout' => 'Pilih kanal penjualan.']);
        $methodEnum = $method !== null ? PaymentMethod::tryFrom($method) : null;

        if ($channelEnum === SalesChannel::Offline && $method !== null && $methodEnum === null) {
            throw ValidationException::withMessages(['checkout' => 'Pilih metode bayar: Tunai atau QRIS.']);
        }

        $sale = $this->guardInventory(fn () => app(SaleService::class)->checkout(
            auth()->user(),
            $channelEnum,
            array_values(array_filter($lines, 'is_array')),
            $methodEnum,
            is_numeric($cashReceived) ? (int) $cashReceived : null,
            is_string($platformOrderId) ? $platformOrderId : null,
        ), 'checkout');

        unset($this->products);

        $this->dispatch('notify', message: "Transaksi {$sale->number} tersimpan. Total ".rupiah($sale->total).'.');

        return [
            'number' => $sale->number,
            'channel' => $sale->channelLabel(),
            'total' => $sale->total,
            'change' => $sale->change,
            'products' => $this->products,
        ];
    }
}; ?>

@php
    $channels = collect(\App\Enums\SalesChannel::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
@endphp

<div
    class="flex flex-col gap-4"
    x-data="{
        products: @js($this->products),
        channels: @js($channels),
        channel: 'offline',
        method: 'cash',
        cash: '',
        orderId: '',
        cart: {},
        saving: false,
        last: null,
        rp(n) { return 'Rp' + Math.max(0, Math.round(n || 0)).toLocaleString('id-ID') },
        price(p) { return p.prices[this.channel] || 0 },
        blocked(p) {
            if (p.status === 'no_recipe') return 'Resep belum diatur';
            if (! this.price(p)) return 'Harga belum diatur';
            if (p.status === 'no_stock') return 'Stok bahan kurang';
            return null;
        },
        add(p) { if (this.blocked(p)) return; this.cart[p.id] = (this.cart[p.id] || 0) + 1; this.last = null },
        inc(id) { this.cart[id] = Math.min(999, (this.cart[id] || 0) + 1) },
        dec(id) { this.cart[id] > 1 ? this.cart[id]-- : delete this.cart[id] },
        get lines() {
            return Object.entries(this.cart)
                .map(([id, qty]) => ({ product: this.products.find((p) => p.id == id), qty }))
                .filter((line) => line.product && line.qty > 0);
        },
        get count() { return this.lines.reduce((sum, line) => sum + line.qty, 0) },
        get total() { return this.lines.reduce((sum, line) => sum + this.price(line.product) * line.qty, 0) },
        get isOffline() { return this.channel === 'offline' },
        get isCash() { return this.isOffline && this.method === 'cash' },
        get change() { return Number(this.cash || 0) - this.total },
        get canPay() {
            return this.lines.length > 0
                && this.lines.every((line) => ! this.blocked(line.product))
                && (! this.isCash || Number(this.cash || 0) >= this.total);
        },
        async pay() {
            if (! this.canPay || this.saving) return;
            this.saving = true;
            const result = await $wire.checkout(
                this.channel,
                this.lines.map((line) => ({ product_id: line.product.id, qty: line.qty })),
                this.isOffline ? this.method : null,
                this.isCash ? Number(this.cash) : null,
                this.isOffline ? null : this.orderId,
            );
            this.saving = false;
            if (result) {
                this.products = result.products;
                this.last = result;
                this.cart = {};
                this.cash = '';
                this.orderId = '';
                $flux.modal('checkout').close();
            }
        },
    }"
>
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[26px] leading-tight font-bold">Kasir</h1>
            @if ($this->shift)
                <x-status-badge variant="success">Shift aktif sejak {{ $this->shift->opened_at->format('H.i') }}</x-status-badge>
            @endif
        </div>
        <a href="{{ route('outlet.sales') }}" wire:navigate class="flex min-h-12 items-center gap-2 rounded-lg border border-border bg-surface px-3 text-base font-semibold">
            <flux:icon name="receipt-percent" class="size-5" /> Riwayat
        </a>
    </div>

    {{-- Kanal (POS-2) --}}
    <div class="-mx-4 overflow-x-auto px-4" role="radiogroup" aria-label="Kanal penjualan">
        <div class="flex min-w-max gap-2">
            <template x-for="(label, value) in channels" :key="value">
                <button
                    type="button"
                    role="radio"
                    :aria-checked="channel === value"
                    x-on:click="channel = value"
                    class="min-h-12 rounded-lg border px-4 text-base font-semibold"
                    :class="channel === value ? 'border-primary bg-primary text-[#38332B]' : 'border-border bg-surface text-text'"
                    x-text="label"
                ></button>
            </template>
        </div>
    </div>

    {{-- Ringkasan transaksi terakhir --}}
    <div x-show="last" x-cloak class="flex items-start gap-3 rounded-xl bg-success/10 p-4" role="status">
        <flux:icon name="check-circle" class="size-7 shrink-0 text-success" />
        <div class="min-w-0 flex-1">
            <div class="font-semibold text-success">Transaksi tersimpan</div>
            <div class="text-base"><span x-text="last?.number"></span> · <span x-text="last?.channel"></span></div>
            <div class="text-base">Total <strong x-text="rp(last?.total)"></strong>
                <template x-if="last?.change !== null && last?.change !== undefined">
                    <span> · Kembalian <strong x-text="rp(last?.change)"></strong></span>
                </template>
            </div>
        </div>
        <button type="button" x-on:click="last = null" aria-label="Tutup" class="-me-2 -mt-2 flex size-11 items-center justify-center text-muted">
            <flux:icon name="x-mark" class="size-5" />
        </button>
    </div>

    {{-- Grid produk (POS-1) --}}
    @if (count($this->products) === 0)
        <x-empty-state icon="shopping-bag" title="Belum ada produk" description="Pemilik perlu menambahkan produk di Pengaturan." size="lg" class="border border-border" />
    @else
        <div class="grid grid-cols-2 gap-3">
            <template x-for="p in products" :key="p.id">
                <button
                    type="button"
                    x-on:click="add(p)"
                    :disabled="!! blocked(p)"
                    :aria-label="'Tambah ' + p.name"
                    class="relative flex min-h-28 flex-col justify-between gap-2 rounded-xl border bg-surface p-4 text-start disabled:bg-canvas"
                    :class="cart[p.id] ? 'border-primary ring-2 ring-primary' : 'border-border'"
                >
                    <div>
                        <div class="text-sm text-muted" x-text="p.type"></div>
                        <div class="text-lg leading-tight font-semibold" x-text="p.name"></div>
                    </div>
                    <div x-show="! blocked(p)" class="text-xl font-bold" x-text="rp(price(p))"></div>
                    <div x-show="blocked(p)" class="flex items-center gap-1 text-sm font-medium text-danger">
                        <flux:icon name="exclamation-circle" class="size-4 shrink-0" /> <span x-text="blocked(p)"></span>
                    </div>
                    <span
                        x-show="cart[p.id]"
                        x-text="cart[p.id]"
                        class="absolute end-3 top-3 flex size-8 items-center justify-center rounded-full bg-primary text-base font-bold text-[#38332B]"
                    ></span>
                </button>
            </template>
        </div>
    @endif

    <div class="h-20" aria-hidden="true"></div>

    {{-- Bar total sticky di atas bottom nav --}}
    <div class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-10 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur">
        <div class="mx-auto flex max-w-2xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <div class="text-sm text-muted"><span x-text="count"></span> item · <span x-text="channels[channel]"></span></div>
                <div class="text-2xl font-bold" x-text="rp(total)"></div>
            </div>
            <button
                type="button"
                x-on:click="$flux.modal('checkout').show()"
                :disabled="count === 0"
                class="flex min-h-14 items-center gap-2 rounded-lg bg-primary px-6 text-lg font-semibold text-[#38332B] disabled:bg-border disabled:text-muted"
            >
                <flux:icon name="wallet" class="size-6" /> Bayar
            </button>
        </div>
    </div>

    {{-- Pembayaran (POS-3/4) --}}
    <flux:modal name="checkout" flyout position="bottom" class="max-h-[92dvh] p-4! sm:p-6!">
        <div class="mx-auto flex max-w-2xl flex-col gap-4">
            <div>
                <flux:heading size="lg">Pembayaran</flux:heading>
                <p class="text-base text-muted"><span x-text="channels[channel]"></span> · <span x-text="count"></span> item</p>
            </div>

            <ul class="flex flex-col divide-y divide-border">
                <template x-for="line in lines" :key="line.product.id">
                    <li class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold" x-text="line.product.name"></div>
                            <div class="text-sm text-muted" x-show="! blocked(line.product)" x-text="rp(price(line.product)) + ' × ' + line.qty + ' = ' + rp(price(line.product) * line.qty)"></div>
                            <div class="text-sm text-danger" x-show="blocked(line.product)" x-text="blocked(line.product) + ' untuk kanal ini'"></div>
                        </div>
                        <div class="flex items-center rounded-lg border border-border">
                            <button type="button" x-on:click="dec(line.product.id)" class="flex size-12 items-center justify-center" :aria-label="'Kurangi ' + line.product.name">
                                <flux:icon name="minus" class="size-5" />
                            </button>
                            <span class="w-8 text-center text-lg font-semibold" x-text="line.qty"></span>
                            <button type="button" x-on:click="inc(line.product.id)" class="flex size-12 items-center justify-center" :aria-label="'Tambah ' + line.product.name">
                                <flux:icon name="plus" class="size-5" />
                            </button>
                        </div>
                    </li>
                </template>
            </ul>

            <div class="flex items-center justify-between rounded-xl bg-soft/60 p-4">
                <span class="text-base">Total</span>
                <span class="text-[26px] font-bold" x-text="rp(total)"></span>
            </div>

            {{-- Offline: metode bayar --}}
            <div x-show="isOffline" class="flex flex-col gap-3">
                <div class="font-medium">Metode bayar</div>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['cash' => ['Tunai', 'banknotes'], 'qris' => ['QRIS', 'qr-code']] as $value => [$label, $icon])
                        <button
                            type="button"
                            x-on:click="method = '{{ $value }}'"
                            class="flex min-h-12 items-center justify-center gap-2 rounded-lg border-2 text-base font-semibold"
                            :class="method === '{{ $value }}' ? 'border-primary bg-soft/60' : 'border-border bg-surface'"
                        >
                            <flux:icon :name="$icon" class="size-5 text-brown" /> {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div x-show="isCash" class="flex flex-col gap-3">
                    <label for="cash-received" class="font-medium">Uang diterima *</label>
                    <div class="flex items-center gap-2 rounded-lg border border-border bg-surface px-4 focus-within:border-primary">
                        <span class="text-base text-muted">Rp</span>
                        <input id="cash-received" x-model="cash" type="number" min="0" inputmode="numeric" placeholder="0" class="min-h-14 w-full border-0 bg-transparent text-2xl font-semibold outline-none focus:ring-0" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-on:click="cash = String(total)" class="min-h-12 rounded-lg border border-border bg-surface px-4 text-base font-semibold">Uang pas</button>
                        @foreach ([10000, 20000, 50000, 100000] as $amount)
                            <button
                                type="button"
                                x-on:click="cash = '{{ $amount }}'"
                                class="min-h-12 rounded-lg border px-4 text-base font-semibold"
                                :class="cash === '{{ $amount }}' ? 'border-primary bg-primary text-[#38332B]' : 'border-border bg-surface'"
                            >{{ $amount / 1000 }}rb</button>
                        @endforeach
                    </div>
                    <div class="flex items-center justify-between rounded-xl p-4" :class="change >= 0 ? 'bg-success/10' : 'bg-danger/10'">
                        <span class="text-base" :class="change >= 0 ? 'text-success' : 'text-danger'" x-text="change >= 0 ? 'Kembalian' : 'Uang kurang'"></span>
                        <span class="text-2xl font-bold" :class="change >= 0 ? 'text-success' : 'text-danger'" x-text="rp(Math.abs(change))"></span>
                    </div>
                </div>

                <p x-show="method === 'qris'" class="text-base text-muted">Pastikan pembayaran QRIS sudah masuk sebelum menyimpan. QRIS tidak dihitung ke uang di laci.</p>
            </div>

            {{-- Platform: nomor pesanan opsional --}}
            <div x-show="! isOffline" class="flex flex-col gap-2">
                <label for="platform-order" class="font-medium">Nomor pesanan <span x-text="channels[channel]"></span> (opsional)</label>
                <input id="platform-order" x-model="orderId" type="text" maxlength="50" class="min-h-12 rounded-lg border border-border bg-surface px-4 text-base outline-none focus:border-primary" placeholder="Mis. F-123456" />
            </div>

            @error('checkout')
                <x-inline-error>{{ $message }}</x-inline-error>
            @enderror

            <button
                type="button"
                x-on:click="pay()"
                :disabled="! canPay || saving"
                class="flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B] disabled:bg-border disabled:text-muted"
            >
                <flux:icon name="check" class="size-6" />
                <span x-show="! saving">Selesaikan · <span x-text="rp(total)"></span></span>
                <span x-show="saving">Menyimpan...</span>
            </button>
        </div>
    </flux:modal>
</div>
