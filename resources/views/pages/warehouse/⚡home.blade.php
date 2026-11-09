<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    //
}; ?>

<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">Halo, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1 text-base">Permintaan yang harus dikirim dan stok menipis akan tampil di sini.</flux:text>
    </div>
</div>
