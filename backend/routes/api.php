<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\MusicatController;
use App\Http\Controllers\PersonalStatsController;
use App\Http\Controllers\PublicDashboardController;
use App\Http\Controllers\StatsFmController;
use Illuminate\Support\Facades\Route;

// --- Public: landing page dashboard, no login required ---------------------
// Every route below expects ?platform=spotify|apple_music so the public
// tab switcher (Spotify/statsfm vs Apple Music/musicat) always reads a
// single platform's data — see PublicDashboardController.
Route::prefix('public')->group(function () {
    Route::get('/overview', [PublicDashboardController::class, 'overview']);
    Route::get('/top-tracks', [PublicDashboardController::class, 'topTracks']);
    Route::get('/top-artists', [PublicDashboardController::class, 'topArtists']);
    Route::get('/recently-played', [PublicDashboardController::class, 'recentlyPlayed']);
});

// --- Auth --------------------------------------------------------------
// Registration is a 3-step Gmail + OTP flow: register() only validates
// the Gmail address and emails a code; verifyRegistration() is what
// actually creates the `users` row, once the code checks out.
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/register/verify', [AuthController::class, 'verifyRegistration']);
Route::post('/auth/register/resend', [AuthController::class, 'resendCode']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // --- Stats.fm account connection — Spotify only, one per user -------
    Route::get('/statsfm/connection', [StatsFmController::class, 'show']);
    Route::post('/statsfm/connect', [StatsFmController::class, 'connect']);
    Route::delete('/statsfm/connection', [StatsFmController::class, 'disconnect']);
    Route::post('/statsfm/sync', [StatsFmController::class, 'sync']);

    // --- Musicat account connection — exclusive Apple Music source ------
    Route::get('/musicat/connection', [MusicatController::class, 'show']);
    Route::post('/musicat/connect', [MusicatController::class, 'connect']);
    Route::delete('/musicat/connection', [MusicatController::class, 'disconnect']);
    Route::post('/musicat/sync', [MusicatController::class, 'sync']);

    // --- Personal analytics view -----------------------------------------
    Route::get('/me/top-tracks', [PersonalStatsController::class, 'topTracks']);
    Route::get('/me/top-artists', [PersonalStatsController::class, 'topArtists']);
    Route::get('/me/recently-played', [PersonalStatsController::class, 'recentlyPlayed']);
    Route::get('/me/daily-activity', [PersonalStatsController::class, 'dailyActivity']);
});
