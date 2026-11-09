{{-- Shell for outlet staff: compact header + fixed bottom navigation (max 5 menus, PRD §11). --}}
@php
    $menus = [
        ['route' => 'outlet.home', 'active' => 'outlet.home', 'icon' => 'home', 'label' => 'Beranda'],
        ['route' => 'outlet.cashier', 'active' => 'outlet.cashier*', 'icon' => 'shopping-cart', 'label' => 'Kasir'],
        ['route' => 'outlet.expenses', 'active' => 'outlet.expenses*', 'icon' => 'banknotes', 'label' => 'Pengeluaran'],
        ['route' => 'outlet.stock', 'active' => 'outlet.stock*', 'icon' => 'archive-box', 'label' => 'Stok'],
        ['route' => 'profile.edit', 'active' => ['profile.edit', 'security.edit', 'appearance.edit'], 'icon' => 'user-circle', 'label' => 'Akun'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-base text-text">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-surface px-4">
            <x-app-logo-icon class="size-9 shrink-0" />
            <div class="min-w-0 leading-tight">
                <div class="truncate font-semibold">{{ auth()->user()->location?->name }}</div>
                <div class="truncate text-sm text-muted">{{ auth()->user()->name }}</div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-2xl px-4 pt-4 pb-28">
            {{ $slot }}
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)]" aria-label="Menu utama">
            <ul class="mx-auto grid max-w-2xl grid-cols-5">
                @foreach ($menus as $menu)
                    @php $isActive = request()->routeIs($menu['active']); @endphp
                    <li>
                        <a
                            href="{{ route($menu['route']) }}"
                            wire:navigate
                            @if ($isActive) aria-current="page" @endif
                            @class([
                                'flex min-h-16 flex-col items-center justify-center gap-1 px-1 py-2 text-xs',
                                'font-semibold text-text' => $isActive,
                                'font-medium text-muted' => ! $isActive,
                            ])
                        >
                            <span @class([
                                'flex h-8 w-14 items-center justify-center rounded-full',
                                'bg-primary text-[#38332B]' => $isActive,
                            ])>
                                <flux:icon :name="$menu['icon']" :variant="$isActive ? 'solid' : 'outline'" class="size-6" />
                            </span>
                            <span class="w-full truncate text-center">{{ $menu['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
