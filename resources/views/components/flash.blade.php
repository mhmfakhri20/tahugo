{{--
    Pesan untuk pengguna dalam bentuk toast tahugo.:
    - session('success') setelah redirect (tampil di atas konten).
    - Event browser `notify` dari Livewire: $this->dispatch('notify', message: '...', variant: 'success'|'danger').
      Muncul melayang di pojok, hilang sendiri setelah 5 detik.
    (session('status') milik Fortify berisi kode, bukan kalimat, jadi tidak dibaca di sini.)
--}}
@props([
    'size' => 'md',
])

@php
    $message = session('success');
@endphp

@if (filled($message))
    <x-toast title="Berhasil" :size="$size" :class="$attributes->get('class')">{{ $message }}</x-toast>
@endif

<div
    x-data="{
        items: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message: detail.message, danger: detail.variant === 'danger' });
            setTimeout(() => this.remove(id), 5000);
        },
        remove(id) { this.items = this.items.filter((item) => item.id !== id) },
    }"
    x-on:notify.window="add($event.detail)"
    @class([
        'pointer-events-none fixed inset-x-4 z-50 flex flex-col gap-2 sm:start-auto sm:end-6 sm:w-96',
        'bottom-24' => $size === 'lg',
        'bottom-6' => $size !== 'lg',
    ])
    aria-live="polite"
>
    <template x-for="item in items" :key="item.id">
        {{-- Lapisan surface di bawah supaya latar status/10 tetap terbaca saat melayang di atas konten. --}}
        <div class="pointer-events-auto overflow-hidden rounded-xl bg-surface shadow-lg">
        <div
            class="flex items-start gap-3 p-4"
            :class="item.danger ? 'bg-danger/10' : 'bg-success/10'"
            :role="item.danger ? 'alert' : 'status'"
        >
            <span x-show="! item.danger"><flux:icon name="check-circle" class="mt-0.5 size-6 shrink-0 text-success" /></span>
            <span x-show="item.danger"><flux:icon name="exclamation-circle" class="mt-0.5 size-6 shrink-0 text-danger" /></span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold" :class="item.danger ? 'text-danger' : 'text-success'" x-text="item.danger ? 'Gagal' : 'Berhasil'"></div>
                <div @class(['mt-0.5 text-text', 'text-base' => $size === 'lg', 'text-sm' => $size !== 'lg']) x-text="item.message"></div>
            </div>
            <button
                type="button"
                x-on:click="remove(item.id)"
                aria-label="Tutup"
                class="-me-2 -mt-2 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:text-text"
            >
                <flux:icon name="x-mark" class="size-5" />
            </button>
        </div>
        </div>
    </template>
</div>
