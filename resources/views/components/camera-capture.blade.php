{{--
    Ambil foto langsung dari kamera (ATT-1, SHF-3) lalu kompres di browser (maks 1280 px, JPEG) dan unggah ke
    properti Livewire `field`. Tidak ada pilihan galeri: fallback memakai <input capture> yang membuka kamera.
    facing: user (selfie) | environment (kamera belakang). Butuh resources/js/camera.js.
--}}
@props([
    'field',
    'facing' => 'user',
    'label' => 'Foto',
    'required' => true,
])

<div
    wire:ignore
    x-data="cameraCapture({ field: @js($field), facing: @js($facing) })"
    {{ $attributes->class('flex flex-col gap-3') }}
>
    <div class="font-medium text-text">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</div>

    <div class="relative aspect-[3/4] w-full overflow-hidden rounded-xl border border-border bg-canvas sm:aspect-video">
        {{-- Kamera langsung --}}
        <video
            x-ref="video"
            x-show="state === 'live' || state === 'starting'"
            playsinline
            muted
            @class(['size-full object-cover', '-scale-x-100' => $facing === 'user'])
        ></video>

        {{-- Hasil foto --}}
        <img x-show="previewUrl && ['uploading', 'preview', 'error'].includes(state)" :src="previewUrl" alt="Foto yang diambil" class="size-full object-cover" />

        <div x-show="state === 'starting'" class="absolute inset-0 flex items-center justify-center gap-2 text-base text-muted">
            <flux:icon.loading class="size-5" /> Membuka kamera...
        </div>
        <div x-show="state === 'uploading'" class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-2 bg-surface/90 p-3 text-base">
            <flux:icon.loading class="size-5" /> Mengirim foto...
        </div>
        <div x-show="state === 'preview'" class="absolute top-3 start-3">
            <x-status-badge variant="success">Foto siap</x-status-badge>
        </div>

        {{-- Fallback: input kamera (bukan galeri) --}}
        <div x-show="state === 'fallback'" class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
            <div class="flex size-14 items-center justify-center rounded-xl bg-soft"><flux:icon name="camera" class="size-7 text-brown" /></div>
            <p class="text-base text-muted">Ketuk tombol di bawah untuk membuka kamera HP.</p>
        </div>
    </div>

    <template x-if="state === 'denied'">
        <x-permission-help type="camera" />
    </template>

    <div x-show="state === 'error'" x-cloak>
        <x-inline-error><span x-text="message"></span></x-inline-error>
    </div>

    <div class="flex gap-2">
        <button
            type="button"
            x-show="state === 'live'"
            x-on:click="capture()"
            class="flex min-h-14 flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B]"
        >
            <flux:icon name="camera" class="size-6" /> Ambil foto
        </button>

        <label
            x-show="state === 'fallback'"
            class="flex min-h-14 flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B]"
        >
            <flux:icon name="camera" class="size-6" /> Buka kamera
            <input type="file" accept="image/*" capture="{{ $facing }}" class="sr-only" x-on:change="fromFile($event)" />
        </label>

        <button
            type="button"
            x-show="['preview', 'error'].includes(state)"
            x-on:click="retake()"
            class="flex min-h-12 flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 text-base font-semibold text-text"
        >
            <flux:icon name="arrow-path" class="size-5" /> Ulangi foto
        </button>
    </div>
</div>
