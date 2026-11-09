@props([
    'title',
    'description',
    'icon' => 'lock-closed',
])

<div class="flex w-full flex-col gap-2">
    <div class="mb-4 flex size-14 items-center justify-center rounded-lg bg-soft">
        <flux:icon :name="$icon" class="size-7 text-brown" />
    </div>
    <h1 class="text-[26px] leading-tight font-bold text-text">{{ $title }}</h1>
    <p class="text-base text-muted">{{ $description }}</p>
</div>
