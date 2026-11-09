{{-- Placeholder for menus whose feature is built in a later phase (F1–F7). --}}
<x-layouts::app :title="$title">
    <div class="flex flex-col items-center gap-3 py-16 text-center">
        <flux:icon name="wrench-screwdriver" class="size-12 text-zinc-400" />
        <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
        <flux:text class="text-base">Fitur ini sedang disiapkan.</flux:text>
    </div>
</x-layouts::app>
