<?php

test('the root url redirects guests to the login page', function () {
    $this->followingRedirects()
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Selamat datang kembali');
});

test('the login page uses the tahugo design system in light mode by default', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('tahugo.')
        ->assertDontSee('class="dark"', false);
});
