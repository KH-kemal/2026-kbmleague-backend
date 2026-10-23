<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Player\Player;
use App\Models\Team\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PlayerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function account(bool $admin): User
    {
        return User::forceCreate(['name' => $admin ? 'Admin' : 'Member', 'email' => $admin ? 'admin@example.test' : 'member@example.test',
            'password' => 'a-long-test-password', 'is_admin' => $admin]);
    }

    private function player(): Player
    {
        return Player::create(['team_id' => Team::create(['name' => 'Public Club'])->id, 'name' => 'Public Player',
            'jersey_number' => 7, 'photo' => '/storage/players/test.jpg', 'is_active' => true,
            'school_origin' => 'Private School XYZ', 'birth_place_date' => 'Private City, 1 Mei 2013', 'qr_code' => 'QR-PRINTED-UNCHANGED']);
    }

    private function read(string $url, ?string $token = null)
    {
        $this->app['auth']->forgetGuards();
        return $this->getJson($url, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function assertPrivateFieldsAbsent(array $data): void
    {
        foreach (['qr_code', 'school_origin', 'birth_place_date', 'created_at', 'updated_at'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
    }

    public function test_public_player_and_nested_team_responses_hide_private_fields(): void
    {
        $player = $this->player();
        $list = $this->read('/api/players')->assertOk();
        $this->assertPrivateFieldsAbsent($list->json('0'));
        $this->assertStringContainsString('no-store', $list->headers->get('Cache-Control'));
        $this->assertStringContainsString('Authorization', $list->headers->get('Vary'));
        $profile = $this->read('/api/players/'.$player->id.'?is_admin=1')->assertOk();
        $this->assertPrivateFieldsAbsent($profile->json());
        $this->assertSame('Public Player', $profile->json('name'));
        $this->assertSame(7, $profile->json('jersey_number'));
        $this->assertSame('Public Club', $profile->json('team.name'));
        $this->assertPrivateFieldsAbsent($this->read('/api/teams/'.$player->team_id)->assertOk()->json('players.0'));
        $this->read('/api/players?search=Private%20School%20XYZ')->assertOk()->assertJsonCount(0);
        $this->assertSame('QR-PRINTED-UNCHANGED', $player->fresh()->qr_code);
    }

    public function test_non_admin_and_invalid_bearer_tokens_do_not_reveal_private_fields(): void
    {
        $player = $this->player(); $token = $this->account(false)->createToken('member')->plainTextToken;
        foreach ([$token, 'invalid-token'] as $bearer) {
            $this->assertPrivateFieldsAbsent($this->read('/api/players/'.$player->id, $bearer)->assertOk()->json());
            $this->assertPrivateFieldsAbsent($this->read('/api/teams/'.$player->team_id, $bearer)->assertOk()->json('players.0'));
        }
        $this->read('/api/players/by-qr/'.$player->qr_code, $token)->assertForbidden();
        $this->read('/api/players/by-qr/'.$player->qr_code)->assertUnauthorized();
    }

    public function test_admin_retains_biodata_qr_export_and_screening_access(): void
    {
        $player = $this->player(); $admin = $this->account(true); $token = $admin->createToken('admin')->plainTextToken;
        $this->read('/api/players', $token)->assertOk()->assertJsonPath('0.qr_code', $player->qr_code)->assertJsonPath('0.school_origin', $player->school_origin);
        $this->read('/api/players/'.$player->id, $token)->assertOk()->assertJsonPath('birth_place_date', $player->birth_place_date);
        $this->read('/api/teams/'.$player->team_id, $token)->assertOk()->assertJsonPath('players.0.qr_code', $player->qr_code);
        $this->read('/api/players/by-qr/'.$player->qr_code, $token)->assertOk()->assertJsonPath('school_origin', $player->school_origin);
        $this->read('/api/players?search=Private%20School%20XYZ', $token)->assertOk()->assertJsonCount(1);
        // A following guest request must never inherit the preceding admin representation.
        $this->assertPrivateFieldsAbsent($this->read('/api/players/'.$player->id)->assertOk()->json());
    }

    public function test_login_token_expires_and_can_no_longer_read_private_data(): void
    {
        $this->freezeTime(); $player = $this->player(); $this->account(true);
        $login = $this->postJson('/api/login', ['email' => 'admin@example.test', 'password' => 'a-long-test-password'])->assertOk();
        $token = $login->json('token');
        $this->assertSame(480, config('sanctum.expiration'));
        $stored = PersonalAccessToken::findToken($token);
        $this->assertTrue($stored->expires_at->equalTo(now()->addHours(8)->startOfSecond()));
        $this->assertNotNull($login->json('expires_at'));
        $this->read('/api/me', $token)->assertOk();
        $this->travel(8)->hours(); $this->travel(1)->seconds();
        $this->read('/api/me', $token)->assertUnauthorized();
        $this->read('/api/players/by-qr/'.$player->qr_code, $token)->assertUnauthorized();
        $this->assertPrivateFieldsAbsent($this->read('/api/players/'.$player->id, $token)->assertOk()->json());
    }

    public function test_old_tokens_without_explicit_expiry_are_also_time_limited(): void
    {
        $this->freezeTime(); $admin = $this->account(true); $player = $this->player();
        $token = $admin->createToken('legacy')->plainTextToken;
        $this->assertNull(PersonalAccessToken::findToken($token)->expires_at);
        $this->read('/api/me', $token)->assertOk();
        $this->travel(9)->hours();
        $this->read('/api/me', $token)->assertUnauthorized();
        $this->assertPrivateFieldsAbsent($this->read('/api/teams/'.$player->team_id, $token)->assertOk()->json('players.0'));
    }

    public function test_registration_token_has_expiry_and_cannot_claim_admin_role(): void
    {
        config(['security.public_registration' => true]);
        $response = $this->postJson('/api/register', ['name' => 'Member', 'email' => 'new@example.test', 'password' => 'a-long-test-password',
            'password_confirmation' => 'a-long-test-password'])->assertCreated()->assertJsonPath('user.is_admin', false);
        $this->assertNotNull(PersonalAccessToken::findToken($response->json('token'))->expires_at);
    }

    public function test_logout_revokes_token(): void
    {
        $admin = $this->account(true); $token = $admin->createToken('admin')->plainTextToken;
        $this->postJson('/api/logout', [], ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->read('/api/me', $token)->assertUnauthorized();
    }
}
