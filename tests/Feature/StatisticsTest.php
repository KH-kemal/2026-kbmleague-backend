<?php

namespace Tests\Feature;

use App\Models\Fixture\Fixture;
use App\Models\Player\Player;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function setupMatch(): array
    {
        $home = Team::create(['name' => 'Home club']);
        $away = Team::create(['name' => 'Away club']);
        $a = Player::create(['name' => 'Alpha', 'team_id' => $home->id, 'jersey_number' => 9,
            'photo' => 'https://example.test/a.jpg', 'school_origin' => 'Private school', 'birth_place_date' => 'Private birthday', 'qr_code' => 'PRIVATE-QR-A']);
        $b = Player::create(['name' => 'Bravo', 'team_id' => $away->id, 'jersey_number' => 1, 'qr_code' => 'PRIVATE-QR-B']);
        return [$home, $away, $a, $b];
    }

    private function data($home, $away): array
    {
        return ['home_team_id' => $home->id, 'away_team_id' => $away->id, 'match_date' => '2026-10-22 15:00:00',
            'status' => 'completed', 'home_score' => 3, 'away_score' => 1];
    }

    private function goal($team, $player, string $type = 'regular'): array
    {
        return ['team_id' => $team->id, 'player_id' => $player->id, 'minute' => 10, 'type' => $type];
    }

    private function admin(): void
    {
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'email' => 'stats-admin@example.test', 'password' => 'test-password', 'is_admin' => true]), 'sanctum');
    }

    public function test_completed_matches_only_count_with_ties_and_no_private_fields(): void
    {
        [$home, $away, $a, $b] = $this->setupMatch();
        $goals = [
            [...$this->goal($home, $a), 'player_name' => 'Alpha'],
            [...$this->goal($away, $b, 'penalty'), 'player_name' => 'Bravo'],
            [...$this->goal($home, $b, 'own_goal'), 'player_name' => 'Bravo'],
        ];
        $saves = [
            ['team_id' => $home->id, 'player_id' => $a->id, 'player_name' => 'Alpha', 'saves' => 5],
            ['team_id' => $away->id, 'player_id' => $b->id, 'player_name' => 'Bravo', 'saves' => 5],
        ];
        $data = [...$this->data($home, $away), 'goals' => $goals, 'goalkeeper_saves' => $saves];
        Fixture::create($data);
        Fixture::create([...$data, 'status' => 'live']);
        Fixture::create([...$data, 'status' => 'scheduled']);
        $result = $this->getJson('/api/statistics')->assertOk()
            ->assertJsonCount(2, 'top_scorers')->assertJsonPath('top_scorers.0.total', 1)
            ->assertJsonPath('top_scorers.0.rank', 1)->assertJsonPath('top_scorers.1.rank', 1)
            ->assertJsonPath('top_saves.0.total', 5)->assertJsonPath('top_saves.1.rank', 1)
            ->assertJsonPath('summary.completed_matches', 1)->assertJsonPath('summary.total_goals', 4)
            ->assertJsonPath('summary.recorded_saves', 10);
        foreach (['school_origin', 'birth_place_date', 'qr_code', 'PRIVATE-QR', 'Private school'] as $secret) {
            $this->assertStringNotContainsString($secret, $result->getContent());
        }
        $this->assertNotNull($result->json('updated_at'));
        $this->assertSame('PRIVATE-QR-A', $a->fresh()->qr_code);
        $this->getJson('/api/statistics?team_id='.$away->id)->assertOk()
            ->assertJsonCount(1, 'top_scorers')->assertJsonPath('top_scorers.0.player_name', 'Bravo')
            ->assertJsonPath('top_saves.0.total', 5)->assertJsonPath('summary.total_goals', 1);
    }

    public function test_edits_status_changes_and_deletions_recalculate_without_duplicates(): void
    {
        [$home, $away, $a, $b] = $this->setupMatch(); $this->admin();
        $result = $this->postJson('/api/fixtures', [...$this->data($home, $away),
            'goals' => [$this->goal($home, $a)],
            'goalkeeper_saves' => [['team_id' => $away->id, 'player_id' => $b->id, 'saves' => 4]],
        ])->assertCreated()->assertJsonPath('goalkeeper_saves.0.player_name', 'Bravo');
        $id = $result->json('id');
        $this->putJson('/api/fixtures/'.$id, ['goalkeeper_saves' => [['team_id' => $away->id, 'player_id' => $b->id, 'saves' => 2]]])->assertOk();
        $this->getJson('/api/statistics')->assertJsonPath('top_scorers.0.total', 1)->assertJsonPath('top_saves.0.total', 2);
        $this->putJson('/api/fixtures/'.$id, ['venue' => 'Updated venue'])->assertOk()->assertJsonPath('goalkeeper_saves.0.saves', 2);
        $this->putJson('/api/fixtures/'.$id, ['status' => 'live'])->assertOk();
        $this->getJson('/api/statistics')->assertJsonCount(0, 'top_scorers')->assertJsonCount(0, 'top_saves');
        $this->putJson('/api/fixtures/'.$id, ['status' => 'completed'])->assertOk();
        $this->getJson('/api/statistics')->assertJsonPath('top_saves.0.total', 2);
        $this->putJson('/api/fixtures/'.$id, ['goalkeeper_saves' => null])->assertOk()->assertJsonPath('goalkeeper_saves', []);
        $this->getJson('/api/statistics')->assertJsonCount(0, 'top_saves');
        $this->deleteJson('/api/fixtures/'.$id)->assertNoContent();
        $this->getJson('/api/statistics')->assertJsonCount(0, 'top_scorers')->assertJsonPath('summary.completed_matches', 0);
    }

    public function test_keeper_roster_duplicate_and_total_validation(): void
    {
        [$home, $away, $a, $b] = $this->setupMatch(); $this->admin();
        $base = $this->data($home, $away);
        $entry = ['team_id' => $home->id, 'player_id' => $a->id, 'saves' => 3];
        $this->postJson('/api/fixtures', [...$base, 'goalkeeper_saves' => [$entry, $entry]])
            ->assertUnprocessable()->assertJsonValidationErrors('goalkeeper_saves.0.player_id');
        $this->postJson('/api/fixtures', [...$base, 'goalkeeper_saves' => [[...$entry, 'player_id' => $b->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('goalkeeper_saves.0.player_id');
        $this->postJson('/api/fixtures', [...$base, 'goalkeeper_saves' => [[...$entry, 'saves' => -1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('goalkeeper_saves.0.saves');
        $this->postJson('/api/fixtures', [...$base, 'goalkeeper_saves' => [$entry], 'match_statistics' => ['home' => ['saves' => 2]]])
            ->assertUnprocessable()->assertJsonValidationErrors('goalkeeper_saves');
        $second = Player::create(['name' => 'Second keeper', 'team_id' => $home->id, 'jersey_number' => 12, 'qr_code' => 'KEEPER-2']);
        $id = $this->postJson('/api/fixtures', [...$base, 'goalkeeper_saves' => [$entry, [...$entry, 'player_id' => $second->id, 'saves' => 2]],
            'match_statistics' => ['home' => ['saves' => 5]]])->assertCreated()->json('id');
        $this->putJson('/api/fixtures/'.$id, ['match_statistics' => ['home' => ['saves' => 4]]])->assertUnprocessable();
        $this->getJson('/api/statistics')->assertJsonCount(2, 'top_saves')->assertJsonPath('summary.recorded_saves', 5);
    }

    public function test_keeper_history_survives_transfer_deletion_and_club_change_is_rejected(): void
    {
        [$home, $away, $a, $b] = $this->setupMatch(); $this->admin();
        $record = ['team_id' => $away->id, 'player_id' => $b->id, 'saves' => 3];
        $id = $this->postJson('/api/fixtures', [...$this->data($home, $away), 'goalkeeper_saves' => [$record]])->assertCreated()->json('id');
        $b->update(['name' => 'Changed name', 'team_id' => $home->id]);
        $this->putJson('/api/fixtures/'.$id, ['goalkeeper_saves' => [[...$record, 'saves' => 4]]])->assertOk()->assertJsonPath('goalkeeper_saves.0.player_name', 'Bravo');
        $other = Team::create(['name' => 'Other club']);
        $this->putJson('/api/fixtures/'.$id, ['away_team_id' => $other->id])->assertUnprocessable();
        $b->delete();
        $this->putJson('/api/fixtures/'.$id, ['venue' => 'Still editable'])->assertOk();
        $this->getJson('/api/statistics')->assertJsonPath('top_saves.0.player_name', 'Bravo')
            ->assertJsonPath('top_saves.0.profile_available', false)->assertJsonPath('top_saves.0.team.id', $away->id);
    }

    public function test_own_goal_uses_opponent_roster_and_does_not_enter_scorer_ranking(): void
    {
        [$home, $away, $a, $b] = $this->setupMatch(); $this->admin();
        $this->postJson('/api/fixtures', [...$this->data($home, $away), 'goals' => [$this->goal($home, $a, 'own_goal')]])
            ->assertUnprocessable()->assertJsonValidationErrors('goals.0.player_id');
        $id = $this->postJson('/api/fixtures', [...$this->data($home, $away), 'goals' => [$this->goal($home, $b, 'own_goal')]])
            ->assertCreated()->assertJsonPath('goals.0.type', 'own_goal')->assertJsonPath('goals.0.player_name', 'Bravo')->json('id');
        $this->getJson('/api/statistics')->assertJsonCount(0, 'top_scorers');
        $this->putJson('/api/fixtures/'.$id, ['goals' => [$this->goal($home, $b, 'regular')]])->assertUnprocessable();
    }

    public function test_old_team_saves_are_not_assigned_to_a_keeper_and_zero_is_not_a_leader(): void
    {
        [$home, $away, $a, $b] = $this->setupMatch();
        Fixture::create([...$this->data($home, $away), 'match_statistics' => ['away' => ['saves' => 20]]]);
        Fixture::create([...$this->data($home, $away),
            'goalkeeper_saves' => [['team_id' => $away->id, 'player_id' => $b->id, 'player_name' => 'Bravo', 'saves' => 0]]]);
        $this->getJson('/api/statistics')->assertOk()->assertJsonCount(0, 'top_saves')
            ->assertJsonCount(0, 'top_scorers')->assertJsonPath('summary.recorded_saves', 0);
        $this->getJson('/api/statistics?team_id=999999')->assertUnprocessable()->assertJsonValidationErrors('team_id');
        $this->getJson('/api/statistics?team_id[]=1')->assertUnprocessable();
        $this->postJson('/api/statistics', [])->assertMethodNotAllowed();
    }
}
