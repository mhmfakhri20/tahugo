{{-- Shell for owner & warehouse admin: desktop-first sidebar, collapsible drawer on phones. --}}
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
                <div class="mt-1 font-semibold text-text">{{ auth()->user()->role->label() }}</div>
                <div class="text-xs text-muted">
                    {{ auth()->user()->isOwner() ? 'Seluruh operasional' : 'Persediaan & distribusi' }}
                </div>
            </div>

            <flux:sidebar.nav>
                <div class="px-2 pt-2 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">Menu utama</div>
                @if (auth()->user()->isOwner())
                    @include('partials.nav.owner')
                @elseif (auth()->user()->isWarehouseAdmin())
                    @include('partials.nav.warehouse')
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
            <div class="px-2 text-xs text-muted">Tahu Go Business &middot; v1.0</div>
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="border-b border-border bg-surface lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="Buka menu" />

            <x-app-logo :href="route('dashboard')" wire:navigate class="ms-2" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

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
