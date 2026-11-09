<?php

use Illuminate\Support\Facades\Route;

test('auth features that are not used are not registered', function (string $routeName) {
    expect(Route::has($routeName))->toBeFalse();
})->with([
    'register',
    'register.store',
    'password.request',
    'password.email',
    'password.reset',
    'password.update',
    'verification.notice',
    'two-factor.login',
    'two-factor.enable',
]);

test('registration and forgot password urls return 404', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with(['/register', '/forgot-password', '/two-factor-challenge']);
