<?php

use App\Support\HelpContent;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Bantuan (NFR-8): panduan teks bernomor sesuai peran + masalah umum. Sama dengan docs/USER_GUIDE.md.
 */
new #[Title('Bantuan')] class extends Component {
    //
}; ?>

@php
    $user = auth()->user();
    $tasks = HelpContent::tasks($user->role);
    $isOutlet = $user->isOutletStaff();
@endphp

<div @class(['flex flex-col', 'gap-5' => $isOutlet, 'gap-6' => ! $isOutlet])>
    <div>
        <h1 class="text-[26px] leading-tight font-bold">Bantuan</h1>
        <p class="text-base text-muted">Panduan untuk {{ $user->role->label() }}. Ikuti langkah sesuai nomor.</p>
    </div>

    <div class="flex items-start gap-3 rounded-xl bg-soft/60 p-4">
        <flux:icon name="light-bulb" class="size-6 shrink-0 text-brown" />
        <p class="text-base">{{ HelpContent::summary($user->role) }}</p>
    </div>

    <div @class(['grid items-start gap-4', 'lg:grid-cols-2' => ! $isOutlet])>
        @foreach ($tasks as $index => $task)
            <section class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:p-6">
                <h2 class="flex items-center gap-3 text-lg font-semibold">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-base text-[#38332B]">{{ $index + 1 }}</span>
                    {{ $task['title'] }}
                </h2>
                <p class="text-base text-muted"><span class="font-medium text-text">Kapan:</span> {{ $task['when'] }}</p>
                <ol class="flex list-decimal flex-col gap-2 ps-6 text-base marker:font-semibold marker:text-brown">
                    @foreach ($task['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
                @if ($task['tips'] !== [])
                    <div class="flex flex-col gap-1 rounded-lg bg-canvas p-3">
                        <div class="text-sm font-semibold text-brown">Perhatikan</div>
                        <ul class="flex list-disc flex-col gap-1 ps-5 text-base">
                            @foreach ($task['tips'] as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold">Masalah umum</h2>
        @foreach (HelpContent::problems() as $problem)
            <details class="rounded-xl border border-border bg-surface p-4">
                <summary class="min-h-8 cursor-pointer font-semibold">{{ $problem['title'] }}</summary>
                <p class="mt-2 text-base text-muted">{{ $problem['answer'] }}</p>
            </details>
        @endforeach
    </section>
</div>
