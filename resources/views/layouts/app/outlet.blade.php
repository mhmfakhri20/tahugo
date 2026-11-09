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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-base text-zinc-900 dark:bg-zinc-800 dark:text-white">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-4 dark:border-zinc-700 dark:bg-zinc-900">
            <x-app-logo-icon class="size-7 fill-current" />
            <div class="min-w-0 leading-tight">
                <div class="truncate font-semibold">{{ auth()->user()->location?->name }}</div>
                <div class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ auth()->user()->name }}</div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-2xl px-4 pt-4 pb-24">
            {{ $slot }}
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-zinc-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-zinc-700 dark:bg-zinc-900" aria-label="Menu utama">
            <ul class="mx-auto grid max-w-2xl grid-cols-5">
                @foreach ($menus as $menu)
                    @php $isActive = request()->routeIs($menu['active']); @endphp
                    <li>
                        <a
                            href="{{ route($menu['route']) }}"
                            wire:navigate
                            @if ($isActive) aria-current="page" @endif
                            @class([
                                'flex min-h-16 flex-col items-center justify-center gap-1 px-1 py-2 text-xs font-medium',
                                'text-accent-content bg-zinc-100 dark:bg-zinc-800' => $isActive,
                                'text-zinc-600 dark:text-zinc-300' => ! $isActive,
                            ])
                        >
                            <flux:icon :name="$menu['icon']" :variant="$isActive ? 'solid' : 'outline'" class="size-6" />
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
