{{--
    Kerangka halaman Akun untuk pemilik & admin gudang: judul halaman + tab (Profil · Password · Tampilan),
    gaya sama dengan tab Pengaturan/Laporan. Isi halaman (kartu) ada di slot.
--}}
@php
    $tabs = [
        ['route' => 'profile.edit', 'label' => 'Profil', 'icon' => 'user'],
        ['route' => 'security.edit', 'label' => 'Password', 'icon' => 'key'],
        ['route' => 'appearance.edit', 'label' => 'Tampilan', 'icon' => 'sun'],
    ];
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Akun" description="Kelola profil, password, dan tampilan akun Anda." />

    <nav class="-mx-1 overflow-x-auto border-b border-border" aria-label="Menu akun">
        <ul class="flex min-w-max gap-6 px-1">
            @foreach ($tabs as $tab)
                @php $isActive = request()->routeIs($tab['route']); @endphp
                <li>
                    <a
                        href="{{ route($tab['route']) }}"
                        wire:navigate
                        @if ($isActive) aria-current="page" @endif
                        @class([
                            '-mb-px flex h-12 items-center gap-2 border-b-2 text-sm',
                            'border-primary font-semibold text-text' => $isActive,
                            'border-transparent text-muted hover:text-text' => ! $isActive,
                        ])
                    >
                        <flux:icon :name="$tab['icon']" class="size-4" /> {{ $tab['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{ $slot }}
</div>
