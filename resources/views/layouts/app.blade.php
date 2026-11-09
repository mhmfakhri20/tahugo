{{-- Picks the shell for the signed-in user's role (PRD §11). --}}
@if (auth()->user()->isOutletStaff())
    <x-layouts::app.outlet :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app.outlet>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif
