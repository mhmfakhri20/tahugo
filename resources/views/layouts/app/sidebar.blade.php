{{-- Shell for owner & warehouse admin: desktop-first sidebar + top bar, collapsible drawer on phones. --}}
@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-text">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-border bg-surface">
            <flux:sidebar.header>
                <x-app-logo :href="route('dashboard')" wire:navigate class="px-2" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <div class="px-2 pt-2">
                <div class="text-xs font-semibold tracking-wide text-muted uppercase">Workspace</div>
                <div class="mt-1 font-semibold text-text">{{ $user->role->label() }}</div>
                <div class="text-xs text-muted">
                    {{ $user->isOwner() ? 'Seluruh operasional' : 'Persediaan & distribusi' }}
                </div>
            </div>

            <flux:sidebar.nav>
                @if ($user->isOwner())
                    @include('partials.nav.owner')
                @elseif ($user->isWarehouseAdmin())
                    @include('partials.nav.warehouse')
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex h-11 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium text-text hover:bg-canvas"
                    data-test="logout-button"
                >
                    <flux:icon name="arrow-right-start-on-rectangle" class="size-5 text-muted" />
                    Keluar
                </button>
            </form>
            <div class="px-3 text-xs text-muted">Tahu Go Business &middot; v1.0</div>
        </flux:sidebar>

        <flux:header class="min-h-16 border-b border-border bg-surface">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="Buka menu" />

            <x-app-logo :href="route('dashboard')" wire:navigate class="ms-2 lg:hidden" />

            <div class="hidden min-w-0 py-3 leading-tight lg:block">
                <div class="text-xs text-muted">Workspace / {{ $user->role->label() }}</div>
                @if (filled($title ?? null))
                    <div class="mt-1 truncate text-base font-semibold text-text">{{ $title }}</div>
                @endif
            </div>

            <flux:spacer />

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="flex items-center gap-3 rounded-lg p-1 text-start hover:bg-canvas" aria-label="Menu akun">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-soft text-sm font-semibold text-brown">
                        {{ $user->initials() }}
                    </span>
                    <span class="hidden leading-tight sm:block">
                        <span class="block text-sm font-semibold text-text">{{ $user->name }}</span>
                        <span class="block text-xs text-muted">{{ $user->role->label() }}</span>
                    </span>
                    <flux:icon name="chevron-down" class="size-4 text-muted" />
                </button>

                <x-user-menu-items />
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
