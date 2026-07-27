<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Team\TeamController;
use App\Http\Controllers\Player\PlayerController;
use App\Http\Controllers\Fixture\FixtureController;
use App\Http\Controllers\PlayerStat\PlayerStatController;
use App\Http\Controllers\LeagueTable\LeagueTableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Leaderboard\LeaderboardController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/{team}', [TeamController::class, 'show']);
Route::get('/players', [PlayerController::class, 'index']);
Route::get('/players/{player}', [PlayerController::class, 'show']);
Route::get('/fixtures', [FixtureController::class, 'index']);
Route::get('/fixtures/{fixture}', [FixtureController::class, 'show']);
Route::get('/player-stats', [PlayerStatController::class, 'index']);
Route::get('/player-stats/{playerStat}', [PlayerStatController::class, 'show']);
Route::get('/league-table', [LeagueTableController::class, 'index']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/teams', [TeamController::class, 'store']);
    Route::put('/teams/{team}', [TeamController::class, 'update']);
    Route::delete('/teams/{team}', [TeamController::class, 'destroy']);

    Route::post('/players', [PlayerController::class, 'store']);
    Route::put('/players/{player}', [PlayerController::class, 'update']);
    Route::delete('/players/{player}', [PlayerController::class, 'destroy']);

    Route::post('/fixtures', [FixtureController::class, 'store']);
    Route::put('/fixtures/{fixture}', [FixtureController::class, 'update']);
    Route::delete('/fixtures/{fixture}', [FixtureController::class, 'destroy']);

    Route::post('/player-stats', [PlayerStatController::class, 'store']);
    Route::put('/player-stats/{playerStat}', [PlayerStatController::class, 'update']);
    Route::delete('/player-stats/{playerStat}', [PlayerStatController::class, 'destroy']);
});