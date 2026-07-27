<?php

namespace App\Http\Controllers\Fixture;

use App\Http\Controllers\Controller;
use App\Models\Fixture\Fixture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixtureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Fixture::with(['homeTeam', 'awayTeam']);

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $fixtures = $query->orderBy('match_date', 'asc')->get();

        return response()->json($fixtures);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'home_team_id' => 'required|exists:teams,id|different:away_team_id',
            'away_team_id' => 'required|exists:teams,id',
            'match_date' => 'required|date',
            'venue' => 'nullable|string|max:255',
            'status' => 'required|in:scheduled,completed',
            'home_score' => 'nullable|integer|min:0',
            'away_score' => 'nullable|integer|min:0',
        ]);

        $fixture = Fixture::create($validated);

        return response()->json($fixture->load(['homeTeam', 'awayTeam']), 201);
    }

    public function show(Fixture $fixture): JsonResponse
    {
        return response()->json($fixture->load(['homeTeam', 'awayTeam']));
    }

    public function update(Request $request, Fixture $fixture): JsonResponse
    {
        $validated = $request->validate([
            'home_team_id' => 'sometimes|required|exists:teams,id|different:away_team_id',
            'away_team_id' => 'sometimes|required|exists:teams,id',
            'match_date' => 'sometimes|required|date',
            'venue' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:scheduled,completed',
            'home_score' => 'nullable|integer|min:0',
            'away_score' => 'nullable|integer|min:0',
        ]);

        $fixture->update($validated);

        return response()->json($fixture->load(['homeTeam', 'awayTeam']));
    }

    public function destroy(Fixture $fixture): JsonResponse
    {
        $fixture->delete();

        return response()->json(null, 204);
    }
}