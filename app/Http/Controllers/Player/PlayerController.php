<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Player\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Player::with('team');

        if ($request->has('search') && $request->search != '') {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('position', 'like', '%' . $request->search . '%')
                  ->orWhereHas('team', function ($teamQuery) use ($request) {
                      $teamQuery->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->has('position') && $request->position != '') {
            $query->where('position', $request->position);
        }

        if ($request->has('team_id') && $request->team_id != '') {
            $query->where('team_id', $request->team_id);
        }

        $players = $query->orderBy('overall_rating', 'desc')->get();

        return response()->json($players);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'name' => 'required|string|max:255',
            'photo' => 'nullable|string',
            'position' => 'required|string',
            'jersey_number' => 'required|integer',
            'birth_date' => 'nullable|date',
            'height' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'overall_rating' => 'nullable|numeric|between:0,10',
            'is_active' => 'boolean',
        ]);

        $player = Player::create($validated);

        return response()->json($player->load('team'), 201);
    }

    public function show(Player $player): JsonResponse
    {
        return response()->json($player->load('team'));
    }

    public function update(Request $request, Player $player): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'sometimes|required|exists:teams,id',
            'name' => 'sometimes|required|string|max:255',
            'photo' => 'nullable|string',
            'position' => 'sometimes|required|string',
            'jersey_number' => 'sometimes|required|integer',
            'birth_date' => 'nullable|date',
            'height' => 'nullable|integer',
            'weight' => 'nullable|integer',
            'overall_rating' => 'nullable|numeric|between:0,10',
            'is_active' => 'boolean',
        ]);

        $player->update($validated);

        return response()->json($player->load('team'));
    }

    public function destroy(Player $player): JsonResponse
    {
        $player->delete();

        return response()->json(null, 204);
    }
}