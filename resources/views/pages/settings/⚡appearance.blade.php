<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Tampilan')] class extends Component {
    //
}; ?>

@if (auth()->user()->isOutletStaff())
    {{-- Penjaga outlet: hanya pilihan tampilan, dibuka dari menu Profil. --}}
    <section class="flex flex-col gap-5">
        <div>
            <a href="{{ route('profile.edit') }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
                <flux:icon name="arrow-left" class="size-5" /> Profil
            </a>
            <h1 class="text-[26px] leading-tight font-bold">{{ __('Appearance') }}</h1>
            <p class="text-base text-muted">{{ __('Update the appearance settings for your account') }}</p>
        </div>

        <div class="rounded-xl border border-border bg-surface p-4">
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>
        </div>
    </section>
@else
    {{-- Pemilik & admin gudang. --}}
    <div>
        <x-pages::settings.layout>
            <section class="flex max-w-3xl flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
                        <flux:icon name="sun" class="size-5 text-brown" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-text">{{ __('Appearance') }}</h2>
                        <p class="text-sm text-muted">{{ __('Update the appearance settings for your account') }}. Pilihan berlaku di perangkat ini.</p>
                    </div>
                </div>
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="max-w-md">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>
            </section>
        </x-pages::settings.layout>
    </div>
@endif
