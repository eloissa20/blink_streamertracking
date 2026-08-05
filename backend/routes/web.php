<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Stream Overview PH API. See /api/public/overview.']);
});
