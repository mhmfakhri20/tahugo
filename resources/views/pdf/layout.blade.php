<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Laporan' }} · tahugo.</title>
    {{-- dompdf tidak mendukung Tailwind: gaya inline yang meniru tampilan cetak (token design system). --}}
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #38332B; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 14px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #F4C542; }
        .muted { color: #8B8173; }
        .brand { font-weight: bold; font-size: 13px; }
        .brand span { background: #F4C542; padding: 1px 5px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th { background: #FFF9E9; color: #8B8173; font-weight: normal; text-align: left; padding: 4px 6px; }
        td { padding: 4px 6px; border-bottom: 1px solid #EAE3D5; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .bold { font-weight: bold; }
        .danger { color: #D9534F; }
        .success { color: #4F8A62; }
        .grid td { border: none; padding: 2px 6px 2px 0; }
        .footer { margin-top: 18px; font-size: 8px; color: #8B8173; }
    </style>
</head>
<body>
    <div class="brand"><span>&nbsp;</span> tahugo.</div>
    @yield('content')
    <div class="footer">Dibuat {{ now()->translatedFormat('d F Y, H.i') }} WIB · Sistem Operasional & Inventory Tahu Go</div>
</body>
</html>
