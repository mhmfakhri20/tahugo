<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';

    public string $phone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->phone = Auth::user()->phone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     * Username, role and outlet can only be changed by the owner (AUTH-5).
     */
    public function updateProfileInformation(): void
    {
        $validated = $this->validate($this->profileRules());

        Auth::user()->update([
            'name' => $validated['name'],
            'phone' => filled($validated['phone']) ? $validated['phone'] : null,
        ]);

        Flux::toast(variant: 'success', text: 'Profil tersimpan.');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">Profil</flux:heading>

    <x-pages::settings.layout heading="Profil" subheading="Ubah nama dan nomor HP Anda">
        <div class="my-6 space-y-1">
            <flux:text>Username: <strong>{{ auth()->user()->username }}</strong></flux:text>
            <flux:text>Peran: <strong>{{ auth()->user()->role->label() }}</strong></flux:text>
            @if (auth()->user()->location)
                <flux:text>Lokasi: <strong>{{ auth()->user()->location->name }}</strong></flux:text>
            @endif
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" label="Nama" type="text" required autocomplete="name" />

            <flux:input wire:model="phone" label="No. HP (opsional)" type="tel" inputmode="tel" autocomplete="tel" />

            <flux:button variant="primary" type="submit" class="h-12 w-full text-base" data-test="update-profile-button">
                Simpan
            </flux:button>
        </form>

        @if (auth()->user()->isOutletStaff())
            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <flux:button type="submit" icon="arrow-right-start-on-rectangle" class="h-12 w-full text-base" data-test="logout-button">
                    Keluar
                </flux:button>
            </form>
        @endif
    </x-pages::settings.layout>
</section>
