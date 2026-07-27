<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\Player\Player;
use App\Models\PlayerStat\PlayerStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function index(): JsonResponse
    {
        $topScorers = PlayerStat::select('player_id', 'team_id', DB::raw('SUM(goals) as total_goals'), DB::raw('SUM(minutes_played) as total_minutes'))
            ->with(['player', 'team'])
            ->groupBy('player_id', 'team_id')
            ->having('total_goals', '>', 0)
            ->orderByDesc('total_goals')
            ->orderBy('total_minutes')
            ->take(5)
            ->get();

        $topAssists = PlayerStat::select('player_id', 'team_id', DB::raw('SUM(assists) as total_assists'), DB::raw('SUM(minutes_played) as total_minutes'))
            ->with(['player', 'team'])
            ->groupBy('player_id', 'team_id')
            ->having('total_assists', '>', 0)
            ->orderByDesc('total_assists')
            ->orderBy('total_minutes')
            ->take(5)
            ->get();

        $topRated = Player::with('team')
            ->where('overall_rating', '>', 0)
            ->orderByDesc('overall_rating')
            ->take(5)
            ->get();

        $topCleanSheets = PlayerStat::select('player_id', 'team_id', DB::raw('COUNT(CASE WHEN clean_sheet = 1 THEN 1 END) as total_clean_sheets'))
            ->with(['player', 'team'])
            ->whereHas('player', function ($query) {
                $query->where('position', 'Goalkeeper');
            })
            ->groupBy('player_id', 'team_id')
            ->having('total_clean_sheets', '>', 0)
            ->orderByDesc('total_clean_sheets')
            ->take(5)
            ->get();

        return response()->json([
            'top_scorers' => $topScorers,
            'top_assists' => $topAssists,
            'top_rated' => $topRated,
            'top_clean_sheets' => $topCleanSheets,
        ]);
    }
}