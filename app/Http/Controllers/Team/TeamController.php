<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Team\Team;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $teams = Team::withCount('players')->latest()->get();

        return response()->json($teams);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string',
            'founded_year' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $team = Team::create($validated);

        return response()->json($team, 201);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $team->load('players');
        $data = $team->toArray();
        $data['players'] = PlayerResource::collection($team->players)->resolve($request);
        return response()->json($data)->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'logo' => 'nullable|string',
            'founded_year' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $team->update($validated);

        return response()->json($team);
    }

    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json(null, 204);
    }
}