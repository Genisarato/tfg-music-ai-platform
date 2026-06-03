<?php

/**
 * Web Routes
 * ==========
 *
 * This file defines all HTTP routes for the application. Routes are organized
 * into logical groups: landing, Spotify OAuth, chatbot, and liked songs.
 *
 * Authentication is enforced via the 'auth' middleware on routes that require
 * a logged-in user. The Spotify OAuth flow (redirect/callback) is publicly
 * accessible as it handles the login process itself.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

use App\Http\Controllers\StartController;
use App\Http\Controllers\SpotifyController;
use App\Http\Controllers\ChatBotController;
use App\Http\Controllers\SongController;

// ──────────────────────────────────────────
// Landing Page
// ──────────────────────────────────────────
Route::get('/', [StartController::class, 'index'])->name('landing');

// ──────────────────────────────────────────
// Spotify OAuth Authentication
// ──────────────────────────────────────────
Route::get('/auth/spotify', [SpotifyController::class, 'redirect'])->name('spotify.login');
Route::post('/logout', [SpotifyController::class, 'logout'])->name('logout');
Route::get('/callback', [SpotifyController::class, 'callback']);

// ──────────────────────────────────────────
// AI Music Chatbot
// ──────────────────────────────────────────
Route::get('/chatbot', [ChatBotController::class, 'index'])->name('chatbot')->middleware('auth');
Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])->name('chatbot.ask');
Route::post('/chatbot/like', [ChatbotController::class, 'like'])->name('chatbot.like');

// ──────────────────────────────────────────
// Liked Songs Library
// ──────────────────────────────────────────
Route::get('/liked-songs', [SongController::class, 'index'])->name('liked-songs')->middleware('auth');
Route::post('/liked-songs/sync-songs', [SongController::class, 'sync'])->name('sync-songs')->middleware('auth');

// Include user settings routes (profile, password, 2FA, appearance)
require __DIR__.'/settings.php';