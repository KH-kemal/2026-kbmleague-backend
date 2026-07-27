<?php

namespace App\Http\Controllers\PlayerStat;

use App\Http\Controllers\Controller;
use App\Models\Player\Player;
use App\Models\PlayerStat\PlayerStat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlayerStatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlayerStat::with(['player', 'team', 'fixture']);

        if ($request->has('fixture_id') && $request->fixture_id != '') {
            $query->where('fixture_id', $request->fixture_id);
        }

        if ($request->has('player_id') && $request->player_id != '') {
            $query->where('player_id', $request->player_id);
        }

        $stats = $query->latest()->get();

        return response()->json($stats);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fixture_id' => 'required|exists:fixtures,id',
            'player_id' => 'required|exists:players,id',
            'team_id' => 'required|exists:teams,id',
            'goals' => 'integer|min:0',
            'assists' => 'integer|min:0',
            'minutes_played' => 'integer|min:0|max:120',
            'shots' => 'integer|min:0',
            'shots_on_target' => 'integer|min:0',
            'passes_accuracy' => 'integer|min:0|max:100',
            'tackles' => 'integer|min:0',
            'interceptions' => 'integer|min:0',
            'dribble_success' => 'integer|min:0',
            'saves' => 'integer|min:0',
            'clean_sheet' => 'boolean',
            'yellow_cards' => 'integer|min:0',
            'red_cards' => 'integer|min:0',
            'own_goals' => 'integer|min:0',
        ]);

        $validated['rating'] = $this->calculateRating($validated);

        $stat = PlayerStat::updateOrCreate(
            [
                'fixture_id' => $validated['fixture_id'],
                'player_id' => $validated['player_id'],
            ],
            $validated
        );

        $this->updatePlayerOverallRating($validated['player_id']);

        return response()->json($stat->load(['player', 'team']), 201);
    }

    public function show(PlayerStat $playerStat): JsonResponse
    {
        return response()->json($playerStat->load(['player', 'team', 'fixture']));
    }

    public function update(Request $request, PlayerStat $playerStat): JsonResponse
    {
        $validated = $request->validate([
            'goals' => 'integer|min:0',
            'assists' => 'integer|min:0',
            'minutes_played' => 'integer|min:0|max:120',
            'shots' => 'integer|min:0',
            'shots_on_target' => 'integer|min:0',
            'passes_accuracy' => 'integer|min:0|max:100',
            'tackles' => 'integer|min:0',
            'interceptions' => 'integer|min:0',
            'dribble_success' => 'integer|min:0',
            'saves' => 'integer|min:0',
            'clean_sheet' => 'boolean',
            'yellow_cards' => 'integer|min:0',
            'red_cards' => 'integer|min:0',
            'own_goals' => 'integer|min:0',
        ]);

        $mergedData = array_merge($playerStat->toArray(), $validated);
        $validated['rating'] = $this->calculateRating($mergedData);

        $playerStat->update($validated);
        $this->updatePlayerOverallRating($playerStat->player_id);

        return response()->json($playerStat->load(['player', 'team']));
    }

    public function destroy(PlayerStat $playerStat): JsonResponse
    {
        $playerId = $playerStat->player_id;
        $playerStat->delete();
        $this->updatePlayerOverallRating($playerId);

        return response()->json(null, 204);
    }

    private function calculateRating(array $data): float
    {
        $rating = 6.00;
        $rating += ($data['goals'] ?? 0) * 1.00;
        $rating += ($data['assists'] ?? 0) * 0.50;
        $rating += !empty($data['clean_sheet']) ? 0.50 : 0.00;
        $rating += ($data['saves'] ?? 0) * 0.10;
        $rating -= ($data['yellow_cards'] ?? 0) * 0.30;
        $rating -= ($data['red_cards'] ?? 0) * 1.00;
        $rating -= ($data['own_goals'] ?? 0) * 1.00;

        if ($rating > 10.00) {
            $rating = 10.00;
        } elseif ($rating < 0.00) {
            $rating = 0.00;
        }

        return round($rating, 2);
    }

    private function updatePlayerOverallRating($playerId): void
    {
        $player = Player::find($playerId);
        $averageRating = PlayerStat::where('player_id', $playerId)->avg('rating');

        if ($player && $averageRating !== null) {
            $player->update(['overall_rating' => round($averageRating, 2)]);
        } elseif ($player) {
            $player->update(['overall_rating' => 6.00]);
        }
    }
}