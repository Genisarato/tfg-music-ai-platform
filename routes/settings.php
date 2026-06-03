<?php

/**
 * Settings Routes
 * ===============
 *
 * Routes for managing user account settings. Organized into two groups:
 *
 * 1. Basic auth group: Profile viewing and editing (requires login)
 * 2. Verified auth group: Sensitive operations like password changes,
 *    account deletion, 2FA setup, and appearance settings (requires
 *    login + email verification)
 */

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ──────────────────────────────────────────
// Profile Settings (authenticated users)
// ──────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    // Redirect /settings to the profile page by default
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// ──────────────────────────────────────────
// Sensitive Settings (authenticated + verified users)
// ──────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    // Account deletion
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Password management (throttled to 6 attempts per minute)
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // Appearance / theme settings
    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    // Two-Factor Authentication settings
    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
