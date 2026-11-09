{{-- Picks the shell for the signed-in user's role (PRD §11). --}}
@if (auth()->user()->isOutletStaff())
    <x-layouts::app.outlet :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app.outlet>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main class="flex flex-col">
            <x-flash class="mb-6" />

            <div class="flex-1">
                {{ $slot }}
            </div>

            <footer class="mt-10 flex flex-col gap-1 text-xs text-muted sm:flex-row sm:justify-between">
                <span>&copy; {{ now()->year }} Tahu Go. Usaha tertata, tumbuh bersama.</span>
                <span>{{ now()->translatedFormat('l, d F Y') }}</span>
            </footer>
        </flux:main>
    </x-layouts::app.sidebar>
@endif
