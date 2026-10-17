<?php
namespace App\Http\Controllers\Dashboard;
use App\Http\Controllers\Controller;
use App\Models\Fixture\Fixture;
use App\Models\Player\Player;
use App\Models\Team\Team;
use Illuminate\Http\JsonResponse;
class DashboardController extends Controller {
    public function index(): JsonResponse {
        return response()->json([
            'total_teams' => Team::count(),
            'total_players' => Player::count(),
            'total_fixtures' => Fixture::count(),
            'live_fixtures_count' => Fixture::where('status', 'live')->count(),
            'live_fixtures' => Fixture::with(['homeTeam', 'awayTeam'])->where('status', 'live')->orderBy('match_date')->get(),
            'completed_fixtures' => Fixture::where('status', 'completed')->count(),
            'scheduled_fixtures' => Fixture::where('status', 'scheduled')->count(),
            'recent_fixtures' => Fixture::with(['homeTeam', 'awayTeam'])->where('status', '!=', 'live')->latest('match_date')->take(5)->get(),
        ]);
    }
}
