<?php

use App\Enums\AttendanceType;
use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Services\AttendanceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Absen masuk / pulang (ATT-1..6): selfie langsung dari kamera + GPS. Jarak, waktu, dan tanda
 * terlambat/pulang awal dihitung di server. Outlet selalu outlet milik user (BR-7).
 */
new #[Title('Absen')] class extends Component {
    use HandlesInventoryErrors, WithFileUploads;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(): void
    {
        $state = $this->state;

        if ($state['step'] === 'done') {
            session()->flash('success', 'Anda sudah absen pulang hari ini. Sampai jumpa besok!');
            $this->redirectRoute('outlet.home', navigate: true);
        } elseif ($state['clockIn'] !== null && ! $state['canClockOut']) {
            session()->flash('error', 'Shift masih buka. Tutup shift dulu sebelum absen pulang.');
            $this->redirectRoute('outlet.home', navigate: true);
        }
    }

    #[Computed]
    public function state(): array
    {
        return app(AttendanceService::class)->todayState(auth()->user());
    }

    #[Computed]
    public function type(): AttendanceType
    {
        return $this->state['clockIn'] === null ? AttendanceType::ClockIn : AttendanceType::ClockOut;
    }

    /**
     * Coordinates come from the browser; time, distance and flags are decided by the server.
     */
    public function submit(float $latitude, float $longitude, float $accuracy): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Ambil foto selfie dulu.',
        ], [
            'photo' => 'foto selfie',
        ]);

        $service = app(AttendanceService::class);
        $type = $this->type;

        $attendance = $this->guardInventory(fn () => $type === AttendanceType::ClockIn
            ? $service->clockIn(auth()->user(), $this->photo, $latitude, $longitude, $accuracy, request()->userAgent())
            : $service->clockOut(auth()->user(), $this->photo, $latitude, $longitude, $accuracy, request()->userAgent()),
            'attendance',
        );

        $time = $attendance->server_time->format('H.i');
        $badge = $attendance->statusBadge();

        session()->flash('success', $type === AttendanceType::ClockIn
            ? "Absen masuk tercatat pukul {$time} ({$badge['label']})."
            : "Absen pulang tercatat pukul {$time}. Terima kasih!");

        $this->redirectRoute('outlet.home', navigate: true);
    }
}; ?>

@php
    $isClockIn = $this->type === \App\Enums\AttendanceType::ClockIn;
    $outlet = auth()->user()->location;
@endphp

<div class="flex flex-col gap-5" x-data="geoLocator()">
    <div>
        <a href="{{ route('outlet.home') }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
            <flux:icon name="arrow-left" class="size-5" /> Beranda
        </a>
        <h1 class="text-[26px] leading-tight font-bold">{{ $isClockIn ? 'Absen masuk' : 'Absen pulang' }}</h1>
        <p class="text-base text-muted">Foto wajah Anda langsung dari kamera di {{ $outlet->name }}.</p>
    </div>

    @if (! $outlet->hasCoordinates())
        <x-inline-error>Lokasi outlet belum diatur pemilik. Minta pemilik mengisi titik lokasi outlet di Pengaturan, lalu coba lagi.</x-inline-error>
    @else
        {{-- Lokasi (ATT-2) --}}
        <section class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-soft">
                    <flux:icon name="map-pin" class="size-6 text-brown" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">Lokasi Anda</div>
                    <div class="text-base text-muted" x-show="state === 'locating' || state === 'idle'">
                        <span class="inline-flex items-center gap-2"><flux:icon.loading class="size-4" /> Mencari lokasi...</span>
                    </div>
                    <div class="text-base text-success" x-show="state === 'ready'" x-cloak>
                        Lokasi ditemukan (akurasi ±<span x-text="Math.round(accuracy)"></span> m)
                    </div>
                    <div class="text-base text-danger" x-show="state === 'unavailable'" x-cloak>Lokasi belum terbaca.</div>
                    <div class="text-base text-danger" x-show="state === 'insecure'" x-cloak>Halaman harus dibuka lewat alamat https.</div>
                </div>
            </div>
            <flux:button
                icon="arrow-path"
                class="min-h-12"
                x-show="state === 'ready' || state === 'unavailable'"
                x-cloak
                x-on:click="locate()"
            >Cari lokasi lagi</flux:button>
        </section>

        <template x-if="state === 'denied' || state === 'unavailable'">
            <x-permission-help type="location" />
        </template>

        {{-- Selfie (ATT-1) --}}
        <section class="rounded-xl border border-border bg-surface p-4">
            <x-camera-capture field="photo" facing="user" label="Foto selfie" />
            @error('photo')
                <x-inline-error class="mt-3">{{ $message }}</x-inline-error>
            @enderror
        </section>

        @error('attendance')
            <x-inline-error>{{ $message }}</x-inline-error>
        @enderror

        <button
            type="button"
            x-on:click="$wire.submit(lat, lng, accuracy)"
            x-bind:disabled="! ready || ! $wire.photo"
            wire:loading.attr="disabled"
            wire:target="submit,photo"
            class="flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B] disabled:bg-border disabled:text-muted"
        >
            <flux:icon :name="$isClockIn ? 'arrow-right-end-on-rectangle' : 'arrow-right-start-on-rectangle'" class="size-6" />
            <span wire:loading.remove wire:target="submit">{{ $isClockIn ? 'Absen masuk sekarang' : 'Absen pulang sekarang' }}</span>
            <span wire:loading wire:target="submit">Menyimpan...</span>
        </button>
        <p class="text-center text-base text-muted">
            Waktu absen memakai jam server (WIB). Jam buka standar:
            {{ $outlet->open_time ? substr($outlet->open_time, 0, 5) : '—' }}, tutup: {{ $outlet->close_time ? substr($outlet->close_time, 0, 5) : '—' }}.
        </p>
    @endif
</div>
