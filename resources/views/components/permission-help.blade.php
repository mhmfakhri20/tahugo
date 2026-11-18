{{--
    Panduan sederhana bergambar (ikon langkah) saat izin kamera/lokasi ditolak (ATT-6).
    type: camera | location
--}}
@props([
    'type' => 'camera',
])

@php
    $isCamera = $type === 'camera';
    $steps = [
        ['icon' => 'lock-closed', 'text' => 'Ketuk ikon gembok di sebelah kiri alamat web (paling atas layar).'],
        ['icon' => 'adjustments-horizontal', 'text' => 'Pilih "Izin" atau "Setelan situs".'],
        ['icon' => $isCamera ? 'camera' : 'map-pin', 'text' => $isCamera ? 'Ubah "Kamera" menjadi "Izinkan".' : 'Ubah "Lokasi" menjadi "Izinkan".'],
        ['icon' => 'arrow-path', 'text' => 'Kembali ke halaman ini lalu ketuk "Muat ulang" di bawah.'],
    ];
@endphp

<div {{ $attributes->class('flex flex-col gap-4 rounded-xl border border-warning/40 bg-soft/50 p-4') }} role="alert">
    <div class="flex items-start gap-3">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-soft">
            <flux:icon :name="$isCamera ? 'video-camera-slash' : 'map-pin'" class="size-6 text-brown" />
        </div>
        <div>
            <div class="font-semibold text-text">{{ $isCamera ? 'Izin kamera ditolak' : 'Lokasi belum bisa dibaca' }}</div>
            <p class="text-base text-muted">
                {{ $isCamera ? 'Absen wajib memakai foto langsung dari kamera. Aktifkan izin kamera dengan cara berikut:' : 'Absen wajib di lokasi outlet. Nyalakan lokasi (GPS) HP, lalu izinkan lokasi dengan cara berikut:' }}
            </p>
        </div>
    </div>

    <ol class="flex flex-col gap-3">
        @unless ($isCamera)
            <li class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface"><flux:icon name="device-phone-mobile" class="size-5 text-brown" /></span>
                <span class="text-base">Tarik layar HP dari atas, pastikan ikon <strong>Lokasi</strong> menyala.</span>
            </li>
        @endunless
        @foreach ($steps as $step)
            <li class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface"><flux:icon :name="$step['icon']" class="size-5 text-brown" /></span>
                <span class="text-base">{{ $step['text'] }}</span>
            </li>
        @endforeach
    </ol>

    <flux:button icon="arrow-path" class="min-h-12 w-full" x-on:click="window.location.reload()">Muat ulang</flux:button>
</div>
