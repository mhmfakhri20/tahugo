{{-- Placeholder for menus whose feature is built in a later phase (F1–F7). --}}
<x-layouts::app :title="$title">
    <div class="flex flex-col gap-6">
        <h1 class="text-[26px] leading-tight font-bold text-text">{{ $title }}</h1>

        <div class="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <x-empty-state
                icon="wrench-screwdriver"
                title="Fitur ini sedang disiapkan"
                description="Menu ini akan aktif pada tahap pengembangan berikutnya."
                class="[&_p]:text-base"
            />
        </div>
    </div>
</x-layouts::app>
