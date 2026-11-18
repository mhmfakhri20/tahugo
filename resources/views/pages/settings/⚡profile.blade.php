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

{{-- Satu elemen root (syarat Livewire); tampilan dibedakan per peran di dalamnya. --}}
<div>
    @php $user = auth()->user(); @endphp

    @if ($user->isOutletStaff())
        {{-- Penjaga outlet: halaman Profil mobile-first (menu "Profil" di bottom nav). --}}
        <div class="flex flex-col gap-5">
            <h1 class="text-[26px] leading-tight font-bold">Profil</h1>

            {{-- Kartu identitas --}}
            <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
                <div class="flex items-center gap-4">
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-primary text-2xl font-bold text-[#38332B]" aria-hidden="true">
                        {{ $user->initials() }}
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xl font-semibold">{{ $user->name }}</div>
                        <div class="truncate text-base text-muted">{{ '@'.$user->username }}</div>
                        <x-status-badge variant="soft" class="mt-1">{{ $user->role->label() }}</x-status-badge>
                    </div>
                </div>
                <dl class="grid grid-cols-1 gap-3 rounded-lg bg-canvas p-4 text-base">
                    <div class="flex items-center gap-3">
                        <flux:icon name="building-storefront" class="size-5 shrink-0 text-brown" />
                        <dt class="sr-only">Outlet</dt>
                        <dd class="min-w-0 truncate">{{ $user->location?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:icon name="phone" class="size-5 shrink-0 text-brown" />
                        <dt class="sr-only">No. HP</dt>
                        <dd class="min-w-0 truncate">{{ $user->phone ?: 'No. HP belum diisi' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Menu --}}
            <nav aria-label="Menu profil" class="overflow-hidden rounded-xl border border-border bg-surface">
                <ul class="flex flex-col divide-y divide-border">
                    @foreach ([
                        ['route' => 'help', 'icon' => 'question-mark-circle', 'label' => 'Bantuan & panduan', 'hint' => 'Cara absen, kasir, pengeluaran, tutup shift'],
                        ['route' => 'security.edit', 'icon' => 'key', 'label' => 'Ubah password', 'hint' => 'Ganti password untuk masuk'],
                        ['route' => 'appearance.edit', 'icon' => 'sun', 'label' => 'Tampilan', 'hint' => 'Mode terang atau gelap'],
                    ] as $item)
                        <li>
                            <a href="{{ route($item['route']) }}" wire:navigate class="flex min-h-16 items-center gap-4 px-4 py-3 hover:bg-canvas">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-soft">
                                    <flux:icon :name="$item['icon']" class="size-6 text-brown" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold">{{ $item['label'] }}</span>
                                    <span class="block truncate text-sm text-muted">{{ $item['hint'] }}</span>
                                </span>
                                <flux:icon name="chevron-right" class="size-5 shrink-0 text-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Data diri --}}
            <section class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-5">
                <div>
                    <h2 class="text-lg font-semibold">Data diri</h2>
                    <p class="text-base text-muted">Username dan outlet hanya bisa diubah pemilik.</p>
                </div>
                <form wire:submit="updateProfileInformation" class="flex flex-col gap-5">
                    <flux:input wire:model="name" label="Nama *" type="text" required autocomplete="name" />
                    <flux:input wire:model="phone" label="No. HP" type="tel" inputmode="tel" autocomplete="tel" placeholder="Mis. 0812xxxxxxxx" description:trailing="Opsional." />
                    <flux:button variant="primary" type="submit" icon="check" class="h-12 w-full text-base" data-test="update-profile-button">
                        Simpan
                    </flux:button>
                </form>
            </section>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle" class="btn-danger h-12 w-full text-base" data-test="logout-button">
                    Keluar
                </flux:button>
            </form>

            <p class="text-center text-sm text-muted">tahugo. · Lupa password atau pindah outlet? Hubungi pemilik.</p>
        </div>
    @else
        {{-- Pemilik & admin gudang: kartu identitas + kartu data diri (desktop-first). --}}
        <x-pages::settings.layout>
            <div class="grid items-start gap-6 lg:grid-cols-3">
                <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
                    <div class="flex items-center gap-4">
                        <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-soft text-2xl font-bold text-brown" aria-hidden="true">
                            {{ $user->initials() }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-lg font-semibold">{{ $user->name }}</div>
                            <div class="truncate text-sm text-muted">{{ '@'.$user->username }}</div>
                            <x-status-badge variant="soft" class="mt-1">{{ $user->role->label() }}</x-status-badge>
                        </div>
                    </div>

                    <dl class="flex flex-col divide-y divide-border text-sm">
                        <div class="flex items-center justify-between gap-3 py-3">
                            <dt class="flex items-center gap-2 text-muted"><flux:icon name="map-pin" class="size-4 text-brown" /> Cakupan</dt>
                            <dd class="text-end font-medium">{{ $user->location?->name ?? ($user->isOwner() ? 'Seluruh operasional' : 'Gudang & distribusi') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-3">
                            <dt class="flex items-center gap-2 text-muted"><flux:icon name="phone" class="size-4 text-brown" /> No. HP</dt>
                            <dd class="text-end font-medium">{{ $user->phone ?: '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-3">
                            <dt class="flex items-center gap-2 text-muted"><flux:icon name="clock" class="size-4 text-brown" /> Terakhir masuk</dt>
                            <dd class="text-end font-medium">{{ $user->last_login_at?->translatedFormat('d M Y, H.i') ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div class="flex items-start gap-3 rounded-lg bg-canvas p-3 text-sm text-muted">
                        <flux:icon name="information-circle" class="size-5 shrink-0 text-brown" />
                        <span>
                            @if ($user->isOwner())
                                Username dan peran diatur di Pengaturan › Pengguna.
                            @else
                                Username dan peran hanya bisa diubah pemilik.
                            @endif
                        </span>
                    </div>
                </section>

                <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6 lg:col-span-2">
                    <div>
                        <h2 class="text-lg font-semibold text-text">Data diri</h2>
                        <p class="text-sm text-muted">Nama tampil di menu, laporan, dan audit log.</p>
                    </div>

                    <form wire:submit="updateProfileInformation" class="flex flex-col gap-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <flux:input wire:model="name" label="Nama *" type="text" required autocomplete="name" />
                            <flux:input wire:model="phone" label="No. HP" type="tel" inputmode="tel" autocomplete="tel" placeholder="Mis. 0812xxxxxxxx" description:trailing="Opsional." />
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <flux:input label="Username" :value="$user->username" disabled />
                            <flux:input label="Peran" :value="$user->role->label()" disabled />
                        </div>

                        <div class="flex justify-end">
                            <flux:button variant="primary" type="submit" icon="check" data-test="update-profile-button">
                                Simpan perubahan
                            </flux:button>
                        </div>
                    </form>
                </section>
            </div>
        </x-pages::settings.layout>
    @endif
</div>
