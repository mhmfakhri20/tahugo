<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-text antialiased">
        <div class="flex min-h-svh flex-col px-4 py-6 sm:px-8">
            <header>
                <x-app-logo size="lg" :href="route('home')" wire:navigate />
            </header>

            <main class="flex flex-1 flex-col items-center justify-center gap-6 py-10">
                <div class="w-full max-w-md rounded-xl border border-border bg-surface p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <p class="flex items-center gap-2 text-sm text-muted">
                    <flux:icon name="shield-check" class="size-4" />
                    Akses aman sesuai peran dan cabang Anda.
                </p>
            </main>

            <footer class="flex flex-col gap-1 text-xs text-muted sm:flex-row sm:justify-between">
                <span>&copy; {{ now()->year }} Tahu Go. Usaha tertata, tumbuh bersama.</span>
                <span>Tahu Go Business &middot; v1.0</span>
            </footer>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
