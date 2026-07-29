<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Fixture\Fixture;
use App\Models\Player\Player;
use App\Models\PlayerStat\PlayerStat;
use App\Models\Team\Team;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalTeams = Team::count();
        $totalPlayers = Player::count();
        $totalFixtures = Fixture::count();
        $completedFixtures = Fixture::where('status', 'completed')->count();
        $scheduledFixtures = Fixture::where('status', 'scheduled')->count();
        $totalGoals = PlayerStat::sum('goals');
        $totalAssists = PlayerStat::sum('assists');
        $averageRating = Player::where('overall_rating', '>', 0)->avg('overall_rating') ?? 0;

        $recentFixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->latest('match_date')
            ->take(5)
            ->get();

        return response()->json([
            'total_teams' => $totalTeams,
            'total_players' => $totalPlayers,
            'total_fixtures' => $totalFixtures,
            'completed_fixtures' => $completedFixtures,
            'scheduled_fixtures' => $scheduledFixtures,
            'total_goals' => (int) $totalGoals,
            'total_assists' => (int) $totalAssists,
            'average_rating' => round($averageRating, 2),
            'recent_fixtures' => $recentFixtures,
        ]);
    }
}