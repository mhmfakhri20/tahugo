<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    //
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-greeting :name="auth()->user()->name" description="Kelola persediaan dan pengiriman ke outlet." />

    <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
        <x-empty-state
            icon="cube"
            title="Belum ada permintaan stok"
            description="Permintaan baru dari outlet dan stok yang menipis akan muncul di sini."
        />
    </div>
</div>
