<?php

namespace App\Http\Controllers\Fixture;

use App\Http\Controllers\Controller;
use App\Models\Fixture\Fixture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Player\Player;
use Illuminate\Validation\ValidationException;

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
            'status' => 'required|in:scheduled,live,completed',
            'home_score' => 'nullable|integer|min:0',
            'away_score' => 'nullable|integer|min:0',
        ] + $this->detailRules());

        if ($response = $this->validateScore($validated)) return $response;

        $validated = $this->prepareDetails($validated);
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
            'status' => 'sometimes|required|in:scheduled,live,completed',
            'home_score' => 'nullable|integer|min:0',
            'away_score' => 'nullable|integer|min:0',
        ] + $this->detailRules());

        if ($response = $this->validateScore(array_merge($fixture->only(['status', 'home_score', 'away_score']), $validated))) return $response;

        $validated = $this->prepareDetails($validated, $fixture);
        $fixture->update($validated);

        return response()->json($fixture->load(['homeTeam', 'awayTeam']));
    }

    public function destroy(Fixture $fixture): JsonResponse
    {
        $fixture->delete();

        return response()->json(null, 204);
    }

    private function detailRules(): array
    {
        $rules = [
            'goals' => 'sometimes|nullable|array|max:100',
            'goals.*' => 'required|array:team_id,player_id,minute,added_time,type',
            'goals.*.type' => 'sometimes|in:regular,penalty,own_goal',
            'goals.*.team_id' => 'required|integer',
            'goals.*.player_id' => 'required|integer',
            'goals.*.minute' => 'required|integer|min:0|max:150',
            'goals.*.added_time' => 'nullable|integer|min:0|max:30',
            'goalkeeper_saves' => 'sometimes|nullable|array|max:50',
            'goalkeeper_saves.*' => 'required|array:team_id,player_id,saves',
            'goalkeeper_saves.*.team_id' => 'required|integer',
            'goalkeeper_saves.*.player_id' => 'required|integer|distinct',
            'goalkeeper_saves.*.saves' => 'required|integer|min:0|max:999',
            'match_statistics' => 'sometimes|nullable|array:home,away',
        ];
        $fields = 'possession,shots,shots_on_target,saves,fouls,yellow_cards,red_cards';
        foreach (['home', 'away'] as $side) {
            $rules["match_statistics.$side"] = "sometimes|array:$fields";
            foreach (explode(',', $fields) as $field) {
                $rules["match_statistics.$side.$field"] = $field === 'possession'
                    ? 'nullable|numeric|min:0|max:100'
                    : 'nullable|integer|min:0|max:999';
            }
        }
        return $rules;
    }

    private function prepareDetails(array $data, ?Fixture $fixture = null): array
    {
        $home = (int) ($data['home_team_id'] ?? $fixture?->home_team_id);
        $away = (int) ($data['away_team_id'] ?? $fixture?->away_team_id);
        if ($home === $away) {
            throw ValidationException::withMessages(['away_team_id' => 'Kedua tim harus berbeda.']);
        }
        // Revalidate saved goals too when the participating teams change.
        $goals = $data['goals'] ?? $fixture?->goals ?? [];
        if (array_key_exists('goals', $data) && $data['goals'] === null) $goals = [];
        $players = Player::whereIn('id', array_column($goals, 'player_id'))->get()->keyBy('id');
        foreach ($goals as $index => &$goal) {
            $teamId = (int) $goal['team_id'];
            $player = $players->get($goal['player_id']);
            $type = $goal['type'] ?? 'regular';
            $playerTeam = $type === 'own_goal' ? ($teamId === $home ? $away : $home) : $teamId;
            $unchanged = $fixture && collect($fixture->goals ?? [])->contains(fn ($saved) =>
                (int) $saved['player_id'] === (int) $goal['player_id'] && (int) $saved['team_id'] === $teamId && ($saved['type'] ?? 'regular') === $type);
            if (!in_array($teamId, [$home, $away], true) ||
                (!$unchanged && (!$player || (int) $player->team_id !== $playerTeam))) {
                throw ValidationException::withMessages(["goals.$index.player_id" => 'Pemain harus berasal dari tim pencetak gol; untuk gol bunuh diri pilih pemain dari tim lawan.']);
            }
            $goal = [
                'team_id' => $teamId,
                'type' => $type,
                'player_id' => (int) $goal['player_id'],
                'player_name' => $unchanged
                    ? collect($fixture->goals)->first(fn ($saved) => (int) $saved['player_id'] === (int) $goal['player_id'] && (int) $saved['team_id'] === $teamId)['player_name']
                    : $player->name,
                'minute' => (int) $goal['minute'],
                'added_time' => (int) ($goal['added_time'] ?? 0),
            ];
        }
        unset($goal);
        foreach (['home' => $home, 'away' => $away] as $side => $teamId) {
            $score = $data[$side.'_score'] ?? $fixture?->{$side.'_score'};
            if ($score !== null && count(array_filter($goals, fn ($goal) => $goal['team_id'] === $teamId)) > $score) {
                throw ValidationException::withMessages(['goals' => 'Jumlah detail gol tidak boleh melebihi skor tim.']);
            }
        }
        if (array_key_exists('goals', $data)) $data['goals'] = $goals;
        return $this->prepareGoalkeeperSaves($data, $fixture, $home, $away);
    }

    private function prepareGoalkeeperSaves(array $data, ?Fixture $fixture, int $home, int $away): array
    {
        $records = array_key_exists('goalkeeper_saves', $data) ? ($data['goalkeeper_saves'] ?? []) : ($fixture?->goalkeeper_saves ?? []);
        $players = Player::whereIn('id', array_column($records, 'player_id'))->get()->keyBy('id');
        $totals = [$home => 0, $away => 0];
        foreach ($records as $index => &$record) {
            $teamId = (int) $record['team_id'];
            $player = $players->get($record['player_id']);
            $saved = collect($fixture?->goalkeeper_saves ?? [])->first(fn ($entry) =>
                (int) $entry['player_id'] === (int) $record['player_id'] && (int) $entry['team_id'] === $teamId);
            if (!in_array($teamId, [$home, $away], true) || (!$saved && (!$player || (int) $player->team_id !== $teamId))) {
                throw ValidationException::withMessages(["goalkeeper_saves.$index.player_id" => 'Kiper harus berasal dari salah satu tim pada pertandingan ini.']);
            }
            $record = ['team_id' => $teamId, 'player_id' => (int) $record['player_id'],
                'player_name' => $saved['player_name'] ?? $player->name, 'saves' => (int) $record['saves']];
            $totals[$teamId] += $record['saves'];
        }
        unset($record);
        $statistics = array_key_exists('match_statistics', $data) ? $data['match_statistics'] : $fixture?->match_statistics;
        foreach (['home' => $home, 'away' => $away] as $side => $teamId) {
            $total = $statistics[$side]['saves'] ?? null;
            if ($total !== null && $totals[$teamId] > $total) {
                throw ValidationException::withMessages(['goalkeeper_saves' => 'Total saves per kiper tidak boleh melebihi saves tim. Sesuaikan statistik saves tim atau rincian kipernya.']);
            }
        }
        if (array_key_exists('goalkeeper_saves', $data)) $data['goalkeeper_saves'] = $records;
        return $data;
    }

    private function validateScore(array $data): ?JsonResponse
    {
        if (in_array($data['status'], ['live', 'completed'], true) && (($data['home_score'] ?? null) === null || ($data['away_score'] ?? null) === null)) {
            return response()->json(['message' => 'Pertandingan berlangsung atau selesai harus memiliki kedua skor.'], 422);
        }
        return null;
    }
}
