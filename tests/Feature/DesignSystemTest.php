<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

test('metric card shows label, value and an upward trend in success color', function () {
    $html = (string) $this->blade(
        '<x-metric-card label="Total penjualan" value="Rp4.850.000" icon="wallet" trend="12,8% dari kemarin" />'
    );

    expect($html)
        ->toContain('Total penjualan')
        ->toContain('Rp4.850.000')
        ->toContain('12,8% dari kemarin')
        ->toContain('text-success')
        ->not->toContain('text-danger');
});

test('metric card shows a downward trend in danger color and falls back to hint', function () {
    $down = (string) $this->blade('<x-metric-card label="Transaksi" value="128" trend="5% dari kemarin" trend-direction="down" />');
    $hint = (string) $this->blade('<x-metric-card label="Stok perlu perhatian" value="3 item" hint="Di bawah batas minimum" />');

    expect($down)->toContain('text-danger')->not->toContain('text-success');
    expect($hint)->toContain('Di bawah batas minimum')->toContain('text-muted');
});

test('toast shows title, detail and a close button', function () {
    $html = (string) $this->blade('<x-toast title="Permintaan berhasil dikirim">200 pcs Tahu Susu</x-toast>');

    expect($html)
        ->toContain('Permintaan berhasil dikirim')
        ->toContain('200 pcs Tahu Susu')
        ->toContain('aria-label="Tutup"')
        ->toContain('bg-success/10');
});

test('success flash message is shown as a toast in the owner shell', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->withSession(['success' => 'Data berhasil disimpan.'])
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Data berhasil disimpan.')
        ->assertSee('aria-label="Tutup"', false);
});

test('owner shell shows breadcrumb, menu groups and logout button', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Workspace / Pemilik')
        ->assertSee('Menu utama')
        ->assertSee('Lainnya')
        ->assertSee('data-test="logout-button"', false);
});

test('warehouse shell shows breadcrumb and an inventory shortcut on the home page', function () {
    $this->actingAs(User::factory()->warehouseAdmin()->create())
        ->get(route('warehouse.home'))
        ->assertOk()
        ->assertSee('Workspace / Admin Gudang')
        ->assertSee('Periksa inventori');
});

test('outlet shell keeps the five-item bottom navigation', function () {
    $this->actingAs(User::factory()->outletStaff()->create())
        ->get(route('outlet.home'))
        ->assertOk()
        ->assertSeeInOrder(['Beranda', 'Kasir', 'Pengeluaran', 'Stok', 'Akun'])
        ->assertDontSee('Workspace /');
});

test('views do not use default tailwind palette colors', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'flux'.DIRECTORY_SEPARATOR))
        ->filter(fn ($file) => preg_match('/\b(?:bg|text|border|from|to|ring)-(?:neutral|gray|slate|stone|red|green|blue|amber|yellow)-\d{2,3}\b/', $file->getContents()))
        ->map(fn ($file) => $file->getFilename())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
