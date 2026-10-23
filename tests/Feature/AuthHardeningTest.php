<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function registration(string $email = 'new@example.test'): array
    {
        return ['name' => 'New member', 'email' => $email,
            'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password'];
    }

    public function test_registration_is_closed_by_default_and_flood_creates_no_accounts_or_tokens(): void
    {
        $this->assertFalse(config('security.public_registration'));
        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/register', $this->registration('flood'.$i.'@example.test'));
            $this->assertSame($i < 2 ? 403 : 429, $response->status());
            $this->assertArrayNotHasKey('token', $response->json());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_enabled_registration_rejects_the_reported_privilege_payload(): void
    {
        config(['security.public_registration' => true]);
        $this->postJson('/api/register', [...$this->registration(),
            'is_admin' => true, 'role' => 'admin', 'admin' => true,
            'email_verified_at' => '2026-10-20 00:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['is_admin', 'role', 'admin', 'email_verified_at']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_mass_assignment_cannot_set_admin_but_existing_admin_can_login(): void
    {
        $user = User::create([...$this->registration(), 'is_admin' => true]);
        $this->assertFalse($user->fresh()->is_admin);
        $admin = User::forceCreate(['name' => 'Existing admin', 'email' => 'admin@example.test',
            'password' => 'a-long-test-password', 'is_admin' => true]);
        $response = $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'a-long-test-password'])
            ->assertOk()->assertJsonPath('user.is_admin', true);
        $this->assertNotNull(PersonalAccessToken::findToken($response->json('token'))->expires_at);
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$response->json('token')])
            ->assertOk()->assertJsonPath('is_admin', true)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_repeated_login_is_throttled_with_retry_headers_and_resumes_after_window(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])
                ->assertUnauthorized();
        }
        $blocked = $this->postJson('/api/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '5');
        $this->assertGreaterThan(0, (int) $blocked->headers->get('Retry-After'));
        $this->assertStringContainsString('no-store', $blocked->headers->get('Cache-Control'));
        $this->travel(61)->seconds();
        $this->postJson('/api/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])->assertUnauthorized();
    }

    public function test_rotating_emails_and_forged_forwarded_ip_cannot_bypass_ip_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/login', ['email' => 'spray'.$i.'@example.test', 'password' => 'incorrect'],
                ['X-Forwarded-For' => '198.51.100.'.($i + 1)])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'spray-last@example.test', 'password' => 'incorrect'],
            ['X-Forwarded-For' => '203.0.113.10'])->assertStatus(429)->assertHeader('X-RateLimit-Limit', '20');
    }

    public function test_rotating_ips_cannot_bypass_account_window(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($i + 1)])
                ->postJson('/api/login', ['email' => $i % 2 ? 'TARGET@example.test' : 'target@example.test', 'password' => 'incorrect'])
                ->assertUnauthorized();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->postJson('/api/login', ['email' => 'target@example.test', 'password' => 'incorrect'])
            ->assertStatus(429)->assertHeader('X-RateLimit-Limit', '15');
    }

    public function test_reopened_registration_still_has_minute_and_hour_limits(): void
    {
        $this->freezeTime();
        config(['security.public_registration' => true]);
        for ($i = 0; $i < 5; $i++) {
            if ($i > 0 && $i % 2 === 0) {
                $this->travel(61)->seconds();
            }
            $this->postJson('/api/register', $this->registration('member'.$i.'@example.test'))
                ->assertCreated()->assertJsonPath('user.is_admin', false);
            if ($i === 1) {
                $this->postJson('/api/register', $this->registration('minute-blocked@example.test'))->assertStatus(429);
            }
        }
        $this->travel(61)->seconds();
        $this->postJson('/api/register', $this->registration('hour-blocked@example.test'))->assertStatus(429);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('personal_access_tokens', 5);
    }

    public function test_invalid_login_types_get_validation_error_instead_of_server_error(): void
    {
        $this->postJson('/api/login', ['email' => ['malformed'], 'password' => ['malformed']])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_cors_allows_the_public_site_and_bearer_preflight(): void
    {
        $this->getJson('/api/teams', ['Origin' => 'https://kbmleague.my.id'])
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', 'https://kbmleague.my.id')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $response = $this->options('/api/upload', [], [
            'Origin' => 'https://kbmleague.my.id',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://kbmleague.my.id');
        $this->assertStringContainsString('authorization', strtolower($response->headers->get('Access-Control-Allow-Headers')));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_unapproved_and_null_origins_do_not_get_cors_permission(): void
    {
        foreach (['https://evil.example.test', 'null', 'https://kbmleague.my.id.evil.example', 'http://localhost:5173'] as $origin) {
            $this->getJson('/api/teams', ['Origin' => $origin])->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
            $this->options('/api/login', [], ['Origin' => $origin, 'Access-Control-Request-Method' => 'POST'])
                ->assertHeaderMissing('Access-Control-Allow-Origin');
        }
    }

    public function test_security_headers_on_https_and_generic_api_errors(): void
    {
        $response = $this->getJson('https://localhost/api/does-not-exist')
            ->assertNotFound()->assertExactJson(['message' => 'Resource tidak ditemukan.'])
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('X-XSS-Protection', '0');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->getJson('/api/teams/999999')->assertNotFound()->assertExactJson(['message' => 'Resource tidak ditemukan.']);
        $this->getJson('/api/me')->assertUnauthorized()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('http://localhost/api/teams')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    }
}
