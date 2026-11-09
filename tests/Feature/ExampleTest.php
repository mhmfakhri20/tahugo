<?php

test('the root url redirects guests to the login page', function () {
    $this->followingRedirects()
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Masuk ke Tahu Go');
});
