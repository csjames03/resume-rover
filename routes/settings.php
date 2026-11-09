<?php

use App\Http\Controllers\Settings\AiProviderController;
use App\Http\Controllers\Settings\EmailConnectionController;
use App\Http\Controllers\Settings\PreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::put('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    Route::get('settings/ai-providers', [AiProviderController::class, 'show'])->name('ai-providers.show');

    Route::get('settings/email', [EmailConnectionController::class, 'show'])->name('email-connection.show');
    Route::get('settings/email/connect', [EmailConnectionController::class, 'redirect'])->name('email-connection.redirect');
    Route::get('settings/email/callback', [EmailConnectionController::class, 'callback'])->name('email-connection.callback');
    Route::delete('settings/email', [EmailConnectionController::class, 'destroy'])->name('email-connection.destroy');
});
