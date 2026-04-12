<?php

use App\Livewire\MfaChallenge;
use App\Livewire\MfaSetup;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// MFA routes — authenticated but MFA not yet verified
Route::middleware('auth')->prefix('mfa')->name('mfa.')->group(function () {
    Route::get('/setup',     MfaSetup::class)->name('setup');
    Route::get('/challenge', MfaChallenge::class)->name('challenge');
});
