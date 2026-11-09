<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-5">
    <x-greeting :name="auth()->user()->name" />

    <div class="flex items-center gap-3 rounded-xl border border-border bg-surface p-4">
        <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-soft">
            <flux:icon name="building-storefront" class="size-6 text-brown" />
        </div>
        <div class="min-w-0">
            <div class="text-sm text-muted">Outlet Anda</div>
            <div class="truncate text-lg font-semibold">{{ auth()->user()->location->name }}</div>
        </div>
    </div>

    <x-empty-state
        icon="clock"
        title="Absen & shift segera hadir"
        description="Tombol Absen, Buka Shift, dan Tutup Shift akan tampil di halaman ini."
        class="border border-border [&_p]:text-base"
    />
</div>
