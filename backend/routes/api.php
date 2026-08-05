<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PersonalStatsController;
use App\Http\Controllers\PublicDashboardController;
use App\Http\Controllers\StatsFmController;
use Illuminate\Support\Facades\Route;

// --- Public: landing page dashboard, no login required ---------------------
Route::prefix('public')->group(function () {
    Route::get('/overview', [PublicDashboardController::class, 'overview']);
    Route::get('/top-tracks', [PublicDashboardController::class, 'topTracks']);
    Route::get('/top-artists', [PublicDashboardController::class, 'topArtists']);
});

// --- Auth --------------------------------------------------------------
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // --- Stats.fm account connection (one per user) ---------------------
    Route::get('/statsfm/connection', [StatsFmController::class, 'show']);
    Route::post('/statsfm/connect', [StatsFmController::class, 'connect']);
    Route::delete('/statsfm/connection', [StatsFmController::class, 'disconnect']);
    Route::post('/statsfm/sync', [StatsFmController::class, 'sync']);

    // --- Personal analytics view -----------------------------------------
    Route::get('/me/top-tracks', [PersonalStatsController::class, 'topTracks']);
    Route::get('/me/top-artists', [PersonalStatsController::class, 'topArtists']);
    Route::get('/me/recently-played', [PersonalStatsController::class, 'recentlyPlayed']);
    Route::get('/me/daily-activity', [PersonalStatsController::class, 'dailyActivity']);
});
