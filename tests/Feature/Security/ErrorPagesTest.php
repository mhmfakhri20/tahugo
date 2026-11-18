<?php

use App\Models\User;

test('404 and 403 pages are in Indonesian with a way home', function () {
    $this->get('/halaman-tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee('Kembali ke beranda');

    $this->actingAs(User::factory()->outletStaff()->create())
        ->get(route('owner.dashboard'))
        ->assertForbidden()
        ->assertSee('Akses ditolak')
        ->assertSee('Kembali ke beranda');
});

test('419 and 500 views render', function (string $code, string $title) {
    expect(view("errors.{$code}")->render())->toContain($title)->toContain('Kembali ke beranda');
})->with([
    ['419', 'Sesi sudah habis'],
    ['500', 'Terjadi kesalahan'],
]);
