<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'photo@example.test', 'password' => 'test-password', 'is_admin' => true]);
        $this->withToken($admin->createToken('test')->plainTextToken);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHf0AAAAASUVORK5CYII='));
    }

    public function test_replacing_photo_keeps_player_identity_and_qr(): void
    {
        Storage::fake('public');
        $this->loginAdmin();
        $team = DB::table('teams')->insertGetId(['name' => 'Test team']);
        $id = DB::table('players')->insertGetId(['name' => 'Test player', 'team_id' => $team, 'jersey_number' => 7, 'qr_code' => 'KBM-PL-ORIGINAL', 'photo' => 'https://example.test/old.png']);
        $upload = $this->postJson('/api/upload', ['file' => $this->photo(), 'type' => 'players'])->assertCreated();
        Storage::disk('public')->assertExists($upload->json('path'));
        $this->assertSame(Storage::disk('public')->url($upload->json('path')), $upload->json('url'));
        $this->assertDatabaseHas('players', ['id' => $id, 'photo' => 'https://example.test/old.png']);
        $this->putJson('/api/players/'.$id, ['photo' => $upload->json('url')])->assertOk()->assertJsonPath('id', $id)->assertJsonPath('qr_code', 'KBM-PL-ORIGINAL');
        $this->assertDatabaseHas('players', ['id' => $id, 'photo' => $upload->json('url'), 'qr_code' => 'KBM-PL-ORIGINAL']);
    }

    public function test_invalid_and_oversized_files_are_rejected(): void
    {
        Storage::fake('public');
        $this->loginAdmin();
        $this->postJson('/api/upload', ['file' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image')->mimeType('text/plain'), 'type' => 'players'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $largePhoto = $this->photo()->size(3073);
        $this->postJson('/api/upload', ['file' => $largePhoto, 'type' => 'players'])->assertUnprocessable()->assertJsonPath('errors.file.0', 'Ukuran foto maksimal 3 MB.');
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_storage_failure_does_not_return_a_broken_url(): void
    {
        $this->loginAdmin();
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('putFileAs')->once()->andReturn(false);
        $response = $this->postJson('/api/upload', ['file' => $this->photo(), 'type' => 'players'])->assertStatus(503);
        $this->assertArrayNotHasKey('url', $response->json());
    }

    public function test_player_photo_at_three_megabytes_is_accepted(): void
    {
        Storage::fake('public');
        $this->loginAdmin();
        $photo = $this->photo()->size(3072);
        $upload = $this->postJson('/api/upload', ['file' => $photo, 'type' => 'players'])->assertCreated();
        Storage::disk('public')->assertExists($upload->json('path'));
    }

    public function test_guest_cannot_upload_photos(): void
    {
        $this->postJson('/api/upload', ['file' => $this->photo(), 'type' => 'players'])->assertUnauthorized();
    }
}
