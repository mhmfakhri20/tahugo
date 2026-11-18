{{-- Halaman error tahugo. (tanpa query DB / Livewire supaya tetap tampil saat aplikasi bermasalah). --}}
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>@yield('title') - {{ config('app.name', 'Tahu Go') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-canvas p-4 text-base text-text">
        <main class="flex w-full max-w-md flex-col items-center gap-5 rounded-xl border border-border bg-surface p-8 text-center">
            <div class="flex items-center gap-2">
                <x-app-logo-icon class="size-10" />
                <span class="text-xl font-bold">tahugo.</span>
            </div>
            <div class="flex size-14 items-center justify-center rounded-xl bg-soft text-2xl font-bold text-brown">@yield('code')</div>
            <div>
                <h1 class="text-2xl font-bold">@yield('title')</h1>
                <p class="mt-2 text-muted">@yield('message')</p>
            </div>
            <a href="{{ url('/') }}" class="flex min-h-12 w-full items-center justify-center rounded-lg bg-primary px-4 font-semibold text-[#38332B]">
                Kembali ke beranda
            </a>
        </main>
    </body>
</html>
