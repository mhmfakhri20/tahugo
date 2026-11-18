<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ganti password')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }
}; ?>

{{-- Satu elemen root (syarat Livewire); tampilan dibedakan per peran di dalamnya. --}}
<div>
    @php $rules = \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString(); @endphp

    @if (auth()->user()->isOutletStaff())
        {{-- Penjaga outlet: dibuka dari menu Profil. --}}
        <section class="flex flex-col gap-5">
            <div>
                <a href="{{ route('profile.edit') }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
                    <flux:icon name="arrow-left" class="size-5" /> Profil
                </a>
                <h1 class="text-[26px] leading-tight font-bold">Ubah password</h1>
                <p class="text-base text-muted">Gunakan password yang panjang dan tidak mudah ditebak.</p>
            </div>

            <form method="POST" wire:submit="updatePassword" class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4">
                <flux:input wire:model="current_password" label="Password sekarang *" type="password" required autocomplete="current-password" viewable />
                <flux:input wire:model="password" label="Password baru *" type="password" required autocomplete="new-password" :passwordrules="$rules" viewable />
                <flux:input wire:model="password_confirmation" label="Ulangi password baru *" type="password" required autocomplete="new-password" :passwordrules="$rules" viewable />
                <flux:button variant="primary" type="submit" icon="check" class="h-12 w-full text-base" data-test="update-password-button">
                    Simpan password
                </flux:button>
            </form>
        </section>
    @else
        {{-- Pemilik & admin gudang. --}}
        <x-pages::settings.layout>
            <section class="flex max-w-3xl flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
                        <flux:icon name="key" class="size-5 text-brown" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-text">Ubah password</h2>
                        <p class="text-sm text-muted">Gunakan password yang panjang dan tidak mudah ditebak. Setelah diganti, gunakan password baru saat masuk.</p>
                    </div>
                </div>

                <form method="POST" wire:submit="updatePassword" class="flex flex-col gap-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="current_password" label="Password sekarang *" type="password" required autocomplete="current-password" viewable />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="password" label="Password baru *" type="password" required autocomplete="new-password" :passwordrules="$rules" viewable />
                        <flux:input wire:model="password_confirmation" label="Ulangi password baru *" type="password" required autocomplete="new-password" :passwordrules="$rules" viewable />
                    </div>

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit" icon="check" data-test="update-password-button">
                            Simpan password
                        </flux:button>
                    </div>
                </form>
            </section>
        </x-pages::settings.layout>
    @endif
</div>
