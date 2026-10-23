<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Team\TeamController;
use App\Http\Controllers\Player\PlayerController;
use App\Http\Controllers\Player\PlayerImportController;
use App\Http\Controllers\Fixture\FixtureController;
use App\Http\Controllers\LeagueTable\LeagueTableController;
use App\Http\Controllers\News\NewsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Statistics\StatisticsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Upload\UploadController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');

Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/fixtures', [FixtureController::class, 'index']);
Route::get('/fixtures/{fixture}', [FixtureController::class, 'show']);
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news}', [NewsController::class, 'show'])->whereNumber('news');
Route::get('/statistics', [StatisticsController::class, 'index']);
Route::get('/league-table', [LeagueTableController::class, 'index']);
Route::get('/dashboard-stats', [DashboardController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('admin')->group(function () {
        Route::post('/teams', [TeamController::class, 'store']);
        Route::put('/teams/{team}', [TeamController::class, 'update']);
        Route::delete('/teams/{team}', [TeamController::class, 'destroy']);

        Route::post('/players', [PlayerController::class, 'store']);
        Route::put('/players/{player}', [PlayerController::class, 'update']);
        Route::delete('/players/{player}', [PlayerController::class, 'destroy']);
        Route::post('/players/{player}/generate-qr', [PlayerController::class, 'generateQr']);
        Route::get('/players/by-qr/{qr_code}', [PlayerController::class, 'showByQr']);

        Route::post('/fixtures', [FixtureController::class, 'store']);
        Route::put('/fixtures/{fixture}', [FixtureController::class, 'update']);
        Route::delete('/fixtures/{fixture}', [FixtureController::class, 'destroy']);


        Route::post('/news', [NewsController::class, 'store']);
        Route::put('/news/{news}', [NewsController::class, 'update'])->whereNumber('news');
        Route::delete('/news/{news}', [NewsController::class, 'destroy'])->whereNumber('news');

        Route::post('/upload', [UploadController::class, 'store']);
    });
});

require __DIR__.'/player-import.php';
