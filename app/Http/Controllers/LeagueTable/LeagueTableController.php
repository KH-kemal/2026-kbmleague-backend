<?php

namespace App\Http\Controllers\LeagueTable;

use App\Http\Controllers\Controller;
use App\Models\Fixture\Fixture;
use App\Models\Team\Team;
use Illuminate\Http\JsonResponse;

class LeagueTableController extends Controller
{
    public function index(): JsonResponse
    {
        $teams = Team::all();
        $fixtures = Fixture::where('status', 'completed')->get();

        $table = [];

        foreach ($teams as $team) {
            $table[$team->id] = [
                'team' => $team,
                'played' => 0,
                'win' => 0,
                'draw' => 0,
                'lose' => 0,
                'gf' => 0,
                'ga' => 0,
                'gd' => 0,
                'points' => 0,
            ];
        }

        foreach ($fixtures as $fixture) {
            $homeId = $fixture->home_team_id;
            $awayId = $fixture->away_team_id;
            $homeScore = (int) $fixture->home_score;
            $awayScore = (int) $fixture->away_score;

            if (isset($table[$homeId])) {
                $table[$homeId]['played']++;
                $table[$homeId]['gf'] += $homeScore;
                $table[$homeId]['ga'] += $awayScore;
                $table[$homeId]['gd'] = $table[$homeId]['gf'] - $table[$homeId]['ga'];
            }

            if (isset($table[$awayId])) {
                $table[$awayId]['played']++;
                $table[$awayId]['gf'] += $awayScore;
                $table[$awayId]['ga'] += $homeScore;
                $table[$awayId]['gd'] = $table[$awayId]['gf'] - $table[$awayId]['ga'];
            }

            if ($homeScore > $awayScore) {
                if (isset($table[$homeId])) {
                    $table[$homeId]['win']++;
                    $table[$homeId]['points'] += 3;
                }
                if (isset($table[$awayId])) {
                    $table[$awayId]['lose']++;
                }
            } elseif ($homeScore < $awayScore) {
                if (isset($table[$awayId])) {
                    $table[$awayId]['win']++;
                    $table[$awayId]['points'] += 3;
                }
                if (isset($table[$homeId])) {
                    $table[$homeId]['lose']++;
                }
            } else {
                if (isset($table[$homeId])) {
                    $table[$homeId]['draw']++;
                    $table[$homeId]['points'] += 1;
                }
                if (isset($table[$awayId])) {
                    $table[$awayId]['draw']++;
                    $table[$awayId]['points'] += 1;
                }
            }
        }

        $result = array_values($table);

        usort($result, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['gd'] !== $b['gd']) {
                return $b['gd'] <=> $a['gd'];
            }
            return $b['gf'] <=> $a['gf'];
        });

        return response()->json($result);
    }
}