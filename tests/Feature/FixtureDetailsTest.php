<?php

namespace Tests\Feature;

use App\Models\Fixture\Fixture;
use App\Models\Player\Player;
use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function matchData(): array
    {
        $home = Team::create(['name' => 'Home']);
        $away = Team::create(['name' => 'Away']);
        return ['home_team_id' => $home->id, 'away_team_id' => $away->id,
            'match_date' => '2026-10-15 15:00:00', 'status' => 'completed', 'home_score' => 1, 'away_score' => 0];
    }

    private function admin(): void
    {
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test',
            'password' => bcrypt('password'), 'is_admin' => true]), 'sanctum');
    }

    private function scorer(int $teamId): Player
    {
        return Player::create(['name' => 'Test Striker', 'team_id' => $teamId, 'jersey_number' => 9, 'qr_code' => 'FIXED-QR']);
    }

    public function test_details_round_trip_and_unrelated_update_preserve_goals(): void
    {
        $this->admin();
        $data = $this->matchData();
        $player = $this->scorer($data['home_team_id']);
        $data['goals'] = [['team_id' => $data['home_team_id'], 'player_id' => $player->id, 'minute' => 45, 'added_time' => 2]];
        // Both percentages are independent manual inputs, with no total calculation.
        $data['match_statistics'] = ['home' => ['possession' => 60.5, 'shots' => 5, 'saves' => 0], 'away' => ['possession' => 60, 'shots' => null]];
        $created = $this->postJson('/api/fixtures', $data)->assertCreated()
            ->assertJsonPath('goals.0.player_name', 'Test Striker')->assertJsonPath('goals.0.added_time', 2)
            ->assertJsonPath('match_statistics.home.saves', 0)->assertJsonPath('match_statistics.away.shots', null);
        $id = $created->json('id');
        $this->getJson('/api/fixtures/'.$id)->assertOk()->assertJsonPath('goals.0.minute', 45);
        $this->putJson('/api/fixtures/'.$id, ['venue' => 'New Venue'])->assertOk()->assertJsonPath('goals.0.player_name', 'Test Striker');
        $this->getJson('/api/fixtures')->assertOk()->assertJsonPath('0.match_statistics.home.possession', 60.5);
        $this->assertSame('FIXED-QR', $player->fresh()->qr_code);
    }

    public function test_invalid_stats_goal_minutes_and_wrong_roster_are_rejected(): void
    {
        $this->admin(); $data = $this->matchData();
        $player = $this->scorer($data['away_team_id']);
        $this->postJson('/api/fixtures', $data + ['match_statistics' => ['home' => ['possession' => 101, 'shots' => -1], 'away' => ['saves' => 1.5]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['match_statistics.home.possession', 'match_statistics.home.shots', 'match_statistics.away.saves']);
        $this->postJson('/api/fixtures', $data + ['goals' => [['team_id' => $data['home_team_id'], 'player_id' => $player->id, 'minute' => 151]]])
            ->assertUnprocessable()->assertJsonValidationErrors('goals.0.minute');
        $this->postJson('/api/fixtures', $data + ['goals' => [['team_id' => $data['home_team_id'], 'player_id' => $player->id, 'minute' => 10]]])
            ->assertUnprocessable()->assertJsonValidationErrors('goals.0.player_id');
        $this->assertDatabaseCount('fixtures', 0);
    }

    public function test_excess_goals_cannot_be_saved_and_details_can_be_cleared(): void
    {
        $this->admin(); $data = $this->matchData(); $player = $this->scorer($data['home_team_id']);
        $goal = ['team_id' => $data['home_team_id'], 'player_id' => $player->id, 'minute' => 10];
        $this->postJson('/api/fixtures', $data + ['goals' => [$goal, $goal]])->assertUnprocessable()->assertJsonValidationErrors('goals');
        $fixture = $this->postJson('/api/fixtures', $data + ['goals' => [$goal]])->assertCreated()->json('id');
        $this->putJson('/api/fixtures/'.$fixture, ['home_score' => 0])->assertUnprocessable()->assertJsonValidationErrors('goals');
        $this->putJson('/api/fixtures/'.$fixture, ['home_score' => 0, 'goals' => [], 'match_statistics' => null])->assertOk()->assertJsonPath('goals', [])->assertJsonPath('match_statistics', null);
    }

    public function test_legacy_results_and_scheduled_fixtures_still_work(): void
    {
        $this->admin(); $data = $this->matchData();
        $id = $this->postJson('/api/fixtures', $data)->assertCreated()->json('id');
        $this->putJson('/api/fixtures/'.$id, ['home_score' => 2])->assertOk()->assertJsonPath('goals', null);
        $data['status'] = 'scheduled'; $data['home_score'] = null; $data['away_score'] = null;
        $this->postJson('/api/fixtures', $data)->assertCreated();
    }

    public function test_saved_scorer_name_survives_transfer_and_edit(): void
    {
        $this->admin(); $data = $this->matchData(); $player = $this->scorer($data['home_team_id']);
        $goal = ['team_id' => $data['home_team_id'], 'player_id' => $player->id, 'minute' => 5];
        $id = $this->postJson('/api/fixtures', $data + ['goals' => [$goal]])->assertCreated()->json('id');
        $player->update(['name' => 'Renamed', 'team_id' => $data['away_team_id']]);
        $goal['minute'] = 6;
        $this->putJson('/api/fixtures/'.$id, ['goals' => [$goal]])->assertOk()
            ->assertJsonPath('goals.0.player_name', 'Test Striker')->assertJsonPath('goals.0.minute', 6);
        $other = Team::create(['name' => 'Other']);
        $this->putJson('/api/fixtures/'.$id, ['home_team_id' => $other->id])->assertUnprocessable();
        $player->delete();
        $this->putJson('/api/fixtures/'.$id, ['goals' => [$goal]])->assertOk()->assertJsonPath('goals.0.player_name', 'Test Striker');
    }

    public function test_live_lifecycle_filters_dashboard_and_standings(): void
    {
        $this->admin(); $data = $this->matchData();
        $data['status'] = 'scheduled'; $data['home_score'] = null; $data['away_score'] = null;
        $id = $this->postJson('/api/fixtures', $data)->assertCreated()->json('id');
        $this->putJson('/api/fixtures/'.$id, ['status' => 'live'])->assertUnprocessable();
        $this->putJson('/api/fixtures/'.$id, ['status' => 'live', 'home_score' => 0, 'away_score' => 0])->assertOk()->assertJsonPath('status', 'live');
        $player = $this->scorer($data['home_team_id']);
        $this->putJson('/api/fixtures/'.$id, ['home_score' => 1, 'goals' => [['player_id' => $player->id, 'team_id' => $data['home_team_id'], 'minute' => 12]],
            'match_statistics' => ['home' => ['shots' => 3, 'possession' => 55], 'away' => ['saves' => 2]]])->assertOk();
        $this->getJson('/api/fixtures?status=live')->assertOk()->assertJsonCount(1)->assertJsonPath('0.home_score', 1)->assertJsonPath('0.goals.0.minute', 12);
        $this->getJson('/api/fixtures?status=scheduled')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/dashboard-stats')->assertOk()->assertJsonPath('live_fixtures_count', 1)->assertJsonCount(1, 'live_fixtures')->assertJsonPath('live_fixtures.0.match_statistics.home.shots', 3);
        $this->getJson('/api/league-table')->assertOk()->assertJsonPath('0.played', 0)->assertJsonPath('0.points', 0);
        $this->putJson('/api/fixtures/'.$id, ['status' => 'completed'])->assertOk()->assertJsonPath('goals.0.player_name', 'Test Striker');
        $this->getJson('/api/fixtures?status=live')->assertJsonCount(0);
        $this->getJson('/api/fixtures?status=completed')->assertJsonCount(1);
        $this->getJson('/api/league-table')->assertJsonPath('0.played', 1)->assertJsonPath('0.points', 3);
        $this->putJson('/api/fixtures/'.$id, ['status' => 'unknown'])->assertUnprocessable();
    }

    public function test_all_live_matches_are_available_on_dashboard(): void
    {
        $data = $this->matchData(); $data['status'] = 'live';
        for ($i = 0; $i < 6; $i++) Fixture::create($data);
        $this->getJson('/api/dashboard-stats')->assertOk()->assertJsonPath('live_fixtures_count', 6)->assertJsonCount(6, 'live_fixtures')->assertJsonCount(0, 'recent_fixtures');
    }

    public function test_only_admin_can_write_and_public_can_read(): void
    {
        $fixture = Fixture::create($this->matchData());
        $this->getJson('/api/fixtures/'.$fixture->id)->assertOk();
        $this->putJson('/api/fixtures/'.$fixture->id, ['goals' => []])->assertUnauthorized();
        $this->actingAs(User::forceCreate(['name' => 'User', 'email' => 'user@example.test', 'password' => bcrypt('password'), 'is_admin' => false]), 'sanctum');
        $this->putJson('/api/fixtures/'.$fixture->id, ['goals' => []])->assertForbidden();
    }
}
