<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class PlayerProfileTest extends TestCase {
    use RefreshDatabase;
    public function test_admin_can_create_and_update_school_profile_without_removed_fields(): void {
        $team = DB::table('teams')->insertGetId(['name' => 'Test club']);
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'a-long-test-password', 'is_admin' => true]);
        $token = $admin->createToken('test')->plainTextToken;
        $created = $this->withToken($token)->postJson('/api/players', [
            'team_id' => $team, 'name' => 'Test player', 'jersey_number' => 7,
            'school_origin' => 'SMA Example', 'birth_place_date' => 'Surabaya, 15 Mei 2008', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('school_origin', 'SMA Example')->assertJsonPath('birth_place_date', 'Surabaya, 15 Mei 2008');
        $id = $created->json('id');
        $this->withToken($token)->putJson('/api/players/'.$id, ['school_origin' => 'SMA Updated'])->assertOk()->assertJsonPath('school_origin', 'SMA Updated');
        $profile = $this->getJson('/api/players/'.$id)->assertOk()->json();
        foreach (['height', 'weight', 'position', 'overall_rating', 'birth_date'] as $field) {
            $this->assertArrayNotHasKey($field, $profile);
            $this->assertFalse(Schema::hasColumn('players', $field));
        }
        $this->getJson('/api/players?search=Updated')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/players?search=missing-school')->assertOk()->assertJsonCount(0);
    }
    public function test_statistics_are_removed_and_dashboard_still_works(): void {
        $this->assertFalse(Schema::hasTable('player_stats'));
        $this->getJson('/api/player-stats')->assertNotFound();
        $this->getJson('/api/leaderboard')->assertNotFound();
        $data = $this->getJson('/api/dashboard-stats')->assertOk()->json();
        $this->assertArrayHasKey('total_players', $data);
        foreach (['total_goals', 'total_assists', 'average_rating'] as $field) $this->assertArrayNotHasKey($field, $data);
    }
    public function test_non_admin_cannot_write_and_profile_text_is_validated(): void {
        $user = User::forceCreate(['name' => 'Member', 'email' => 'member@example.test', 'password' => 'a-long-test-password', 'is_admin' => false]);
        $this->postJson('/api/players', [])->assertUnauthorized();
        $this->withToken($user->createToken('test')->plainTextToken)->postJson('/api/players', [])->assertForbidden();
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'a-long-test-password', 'is_admin' => true]);
        $team = DB::table('teams')->insertGetId(['name' => 'Test club']);
        $this->app['auth']->forgetGuards();
        $this->withToken($admin->createToken('admin')->plainTextToken)->postJson('/api/players', [
            'team_id' => $team, 'name' => 'Player', 'jersey_number' => 3, 'school_origin' => str_repeat('x', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors('school_origin');
    }
    public function test_migration_preserves_existing_player_date_and_qr(): void {
        $migration = require database_path('migrations/2026_10_11_000000_simplify_player_profiles.php');
        $migration->down();
        $team = DB::table('teams')->insertGetId(['name' => 'Existing club']);
        $id = DB::table('players')->insertGetId(['team_id' => $team, 'name' => 'Existing player', 'jersey_number' => 9, 'birth_date' => '2008-05-15', 'qr_code' => 'KBM-PL-EXISTING']);
        $migration->up();
        $row = DB::table('players')->where('id', $id)->first();
        $this->assertSame('2008-05-15', $row->birth_place_date);
        $this->assertSame('KBM-PL-EXISTING', $row->qr_code);
        $this->assertNull($row->school_origin);
    }
}
