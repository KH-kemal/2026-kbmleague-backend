<?php

namespace App\Http\Controllers\Statistics;

use App\Http\Controllers\Controller;
use App\Models\Fixture\Fixture;
use App\Models\Player\Player;
use App\Models\Team\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['team_id' => 'nullable|integer|exists:teams,id']);
        $teamId = isset($validated['team_id']) ? (int) $validated['team_id'] : null;
        $query = Fixture::where('status', 'completed');
        if ($teamId !== null) {
            $query->where(fn ($q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId));
        }
        $fixtures = $query->orderBy('match_date')->orderBy('id')
            ->get(['id', 'home_team_id', 'away_team_id', 'home_score', 'away_score', 'goals', 'goalkeeper_saves']);
        $scorers = []; $keepers = []; $totalGoals = 0; $goalCoverage = 0; $saveCoverage = 0;
        foreach ($fixtures as $fixture) {
            $totalGoals += $teamId === null ? $fixture->home_score + $fixture->away_score
                : ($teamId === (int) $fixture->home_team_id ? $fixture->home_score : $fixture->away_score);
            if ($fixture->goals !== null) $goalCoverage++;
            $saveRecords = array_filter($fixture->goalkeeper_saves ?? [], fn ($entry) => $teamId === null || (int) $entry['team_id'] === $teamId);
            if ($saveRecords) $saveCoverage++;
            foreach ($fixture->goals ?? [] as $entry) {
                if (($entry['type'] ?? 'regular') === 'own_goal') continue;
                $this->add($scorers, $entry, 1, $fixture->id, $teamId);
            }
            foreach ($saveRecords as $entry) {
                $this->add($keepers, $entry, (int) $entry['saves'], $fixture->id, $teamId);
            }
        }

        $all = array_merge(array_values($scorers), array_values($keepers));
        $players = Player::whereIn('id', array_column($all, 'player_id'))->get(['id', 'name', 'photo'])->keyBy('id');
        $teams = Team::whereIn('id', array_column($all, 'team_id'))->get(['id', 'name', 'logo'])->keyBy('id');
        $updated = Fixture::max('updated_at');

        return response()->json([
            'top_scorers' => $this->rank($scorers, $players, $teams),
            'top_saves' => $this->rank($keepers, $players, $teams),
            'summary' => ['completed_matches' => $fixtures->count(), 'total_goals' => $totalGoals,
                'recorded_saves' => array_sum(array_column($keepers, 'total'))],
            'coverage' => ['matches_with_goal_details' => $goalCoverage, 'matches_with_save_details' => $saveCoverage],
            'updated_at' => $updated ? Carbon::parse($updated)->toISOString() : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function add(array &$rows, array $entry, int $count, int $fixtureId, ?int $teamId): void
    {
        if ($count <= 0 || ($teamId !== null && (int) $entry['team_id'] !== $teamId)) return;
        // Keep a transferred player's results under the club represented in that match.
        $key = $entry['player_id'].':'.$entry['team_id'];
        if (!isset($rows[$key])) {
            $rows[$key] = ['player_id' => (int) $entry['player_id'], 'team_id' => (int) $entry['team_id'],
                'player_name' => $entry['player_name'] ?? '', 'total' => 0, 'matches' => []];
        }
        $rows[$key]['total'] += $count;
        $rows[$key]['matches'][$fixtureId] = true;
    }

    private function rank(array $rows, $players, $teams): array
    {
        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']
            ?: strcasecmp($a['player_name'], $b['player_name'])
            ?: $a['player_id'] <=> $b['player_id'] ?: $a['team_id'] <=> $b['team_id']);
        $previous = null; $rank = 0;
        return array_map(function ($entry, $index) use ($players, $teams, &$previous, &$rank) {
            if ($entry['total'] !== $previous) $rank = $index + 1;
            $previous = $entry['total'];
            $player = $players->get($entry['player_id']);
            $team = $teams->get($entry['team_id']);
            return ['rank' => $rank, 'player_id' => $entry['player_id'],
                'player_name' => $entry['player_name'] ?: ($player?->name ?? 'Pemain tersimpan'),
                'photo' => $player?->photo, 'profile_available' => $player !== null,
                'team' => $team ? ['id' => $team->id, 'name' => $team->name, 'logo' => $team->logo] : null,
                'total' => $entry['total'], 'matches_recorded' => count($entry['matches'])];
        }, $rows, array_keys($rows));
    }
}
