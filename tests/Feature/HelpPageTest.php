<?php

use App\Models\User;

test('each role sees its own guide and the common problems (NFR-8)', function (string $factory, string $expected, string $notExpected) {
    $user = User::factory()->{$factory}()->create();

    $this->actingAs($user)->get(route('help'))
        ->assertOk()
        ->assertSee($expected)
        ->assertDontSee($notExpected)
        ->assertSee('Masalah umum')
        ->assertSee('Lupa password')
        ->assertSee('Kapan:')
        ->assertDontSee('images/help/', escape: false);
})->with([
    'outlet staff' => ['outletStaff', 'Buka shift', 'Mencatat pembelian bahan'],
    'warehouse admin' => ['warehouseAdmin', 'Mencatat pembelian bahan', 'Tutup shift & absen pulang'],
    'owner' => ['owner', 'Memeriksa pengeluaran outlet', 'Mencatat penjualan (Kasir)'],
]);

test('guests cannot open the help page', function () {
    $this->get(route('help'))->assertRedirect(route('login'));
});

test('outlet staff open Bantuan, password and logout from the Profil page', function () {
    $staff = User::factory()->outletStaff()->create(['name' => 'Siti Rahma', 'username' => 'siti']);

    $this->actingAs($staff)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Profil')
        ->assertSee('Siti Rahma')
        ->assertSee('@siti')
        ->assertSee($staff->location->name)
        ->assertSee('Bantuan &amp; panduan', escape: false)
        ->assertSee(route('help'), escape: false)
        ->assertSee(route('security.edit'), escape: false)
        ->assertSee('data-test="logout-button"', escape: false);
});

test('outlet staff see only the theme choice on the Tampilan page', function () {
    $this->actingAs(User::factory()->outletStaff()->create())->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Terang')
        ->assertSee(route('profile.edit'), escape: false)
        ->assertDontSee('Kelola profil dan akun Anda')
        ->assertDontSee('>Password<', escape: false);
});

test('owner and warehouse admin get the restyled Akun pages with their data filled in', function (string $factory) {
    $user = User::factory()->{$factory}()->create(['name' => 'Admin Contoh', 'username' => 'contoh']);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Akun')
        ->assertSee('Data diri')
        ->assertSee('@contoh')
        ->assertSee('Simpan perubahan')
        ->assertSee('wire:model="name"', escape: false);

    Livewire\Livewire::actingAs($user)->test('pages::settings.profile')->assertSet('name', 'Admin Contoh');

    // The password page asks to confirm the current password first (password.confirm).
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))->assertOk()->assertSee('Ubah password')->assertSee('Simpan password');
    $this->actingAs($user)->get(route('appearance.edit'))->assertOk()->assertSee('Terang');
})->with(['owner', 'warehouseAdmin']);
