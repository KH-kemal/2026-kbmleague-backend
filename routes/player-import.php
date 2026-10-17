<?php

use App\Http\Controllers\Player\PlayerImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/player-import/preview', [PlayerImportController::class, 'preview']);
    Route::post('/player-import/commit', [PlayerImportController::class, 'store']);
});
