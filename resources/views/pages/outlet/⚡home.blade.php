<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">Halo, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1 text-base">Outlet: {{ auth()->user()->location->name }}</flux:text>
    </div>

    <flux:callout icon="information-circle" color="sky">
        <flux:callout.text class="text-base">
            Tombol Absen, Buka Shift, dan Tutup Shift akan tampil di halaman ini.
        </flux:callout.text>
    </flux:callout>
</div>
