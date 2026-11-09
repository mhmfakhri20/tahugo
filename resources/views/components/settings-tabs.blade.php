{{-- Tab menu Pengaturan pemilik: teks muted, tab aktif semibold + garis bawah primary. Bisa digeser di layar kecil. --}}
@php
    $tabs = [
        ['route' => 'owner.settings.users', 'label' => 'Pengguna'],
        ['route' => 'owner.settings.locations', 'label' => 'Outlet & gudang'],
        ['route' => 'owner.settings.items', 'label' => 'Bahan baku'],
        ['route' => 'owner.settings.products', 'label' => 'Produk & resep', 'active' => 'owner.settings.products*'],
        ['route' => 'owner.settings.expense-categories', 'label' => 'Kategori pengeluaran'],
    ];
@endphp

<nav {{ $attributes->class('-mx-1 overflow-x-auto border-b border-border') }} aria-label="Menu pengaturan">
    <ul class="flex min-w-max gap-6 px-1">
        @foreach ($tabs as $tab)
            @php $isActive = request()->routeIs($tab['active'] ?? $tab['route']); @endphp
            <li>
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if ($isActive) aria-current="page" @endif
                    @class([
                        '-mb-px flex h-12 items-center border-b-2 text-sm',
                        'border-primary font-semibold text-text' => $isActive,
                        'border-transparent text-muted hover:text-text' => ! $isActive,
                    ])
                >{{ $tab['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
