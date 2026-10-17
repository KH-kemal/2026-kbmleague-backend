<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Player\Player;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Player::with('team');
        $isAdmin = (bool) $request->user('sanctum')?->is_admin;

        if ($request->has('search') && $request->search != '') {
            $query->where(function ($q) use ($request, $isAdmin) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhereHas('team', function ($teamQuery) use ($request) {
                      $teamQuery->where('name', 'like', '%' . $request->search . '%');
                  });
                if ($isAdmin) $q->orWhere('school_origin', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('team_id') && $request->team_id != '') {
            $query->where('team_id', $request->team_id);
        }

        $players = $query->orderBy('name')->get();

        return response()->json(PlayerResource::collection($players)->resolve($request))
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'name' => 'required|string|max:255',
            'photo' => 'nullable|string',
            'school_origin' => 'nullable|string|max:255',
            'birth_place_date' => 'nullable|string|max:255',
            'jersey_number' => 'required|integer',
            'is_active' => 'boolean',
        ]);

        // Auto generate unique qr_code token
        $validated['qr_code'] = 'KBM-PL-' . strtoupper(\Illuminate\Support\Str::random(10)) . '-' . time();

        $player = Player::create($validated);

        return response()->json($player->load('team'), 201);
    }

    public function show(Request $request, Player $player): JsonResponse
    {
        return response()->json((new PlayerResource($player->load('team')))->resolve($request))
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }

    public function update(Request $request, Player $player): JsonResponse
    {
        $validated = $request->validate([
            'team_id' => 'sometimes|required|exists:teams,id',
            'name' => 'sometimes|required|string|max:255',
            'photo' => 'nullable|string',
            'school_origin' => 'nullable|string|max:255',
            'birth_place_date' => 'nullable|string|max:255',
            'jersey_number' => 'sometimes|required|integer',
            'is_active' => 'boolean',
            'qr_code' => 'nullable|string|unique:players,qr_code,' . $player->id,
        ]);

        $player->update($validated);

        return response()->json($player->load('team'));
    }

    public function destroy(Player $player): JsonResponse
    {
        $player->delete();

        return response()->json(null, 204);
    }

    public function generateQr(Player $player): JsonResponse
    {
        $player->update([
            'qr_code' => 'KBM-PL-' . strtoupper(\Illuminate\Support\Str::random(10)) . '-' . time()
        ]);

        return response()->json($player->load('team'));
    }

    public function showByQr(string $qrCode): JsonResponse
    {
        $player = Player::where('qr_code', $qrCode)->with('team')->first();

        if (!$player) {
            return response()->json(['message' => 'Pemain tidak ditemukan.'], 404);
        }

        return response()->json($player);
    }
}
