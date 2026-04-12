<?php

use App\Http\Controllers\Auth\MfaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// MFA routes — authenticated but MFA not yet verified
Route::middleware('auth')->prefix('mfa')->name('mfa.')->group(function () {
    Route::get('/setup',     fn () => view('mfa.setup'))->name('setup');
    Route::post('/setup',    [MfaController::class, 'setup'])->name('setup.store');
    Route::post('/verify',   [MfaController::class, 'verify'])->name('verify');
    Route::get('/challenge', fn () => view('mfa.challenge'))->name('challenge');
    Route::post('/challenge', [MfaController::class, 'challenge'])->name('challenge.verify');
});
