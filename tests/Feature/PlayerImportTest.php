<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Player\Player;
use App\Models\Team\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerImportTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): void {
        $this->actingAs(User::forceCreate(['name'=>'Admin','email'=>'admin@example.test','password'=>bcrypt('test-password'),'is_admin'=>true]), 'sanctum');
    }
    private function preview(string $csv) {
        return $this->postJson('/api/player-import/preview', ['file'=>UploadedFile::fake()->createWithContent('players.csv', $csv)]);
    }
    private function header(): string {
        return "name,birth_place_date,school_origin,team_name,jersey_number,photo_url\n";
    }
    public function test_admin_access_is_required(): void {
        $this->postJson('/api/player-import/preview')->assertUnauthorized();
        $this->postJson('/api/player-import/commit')->assertUnauthorized();
        $this->actingAs(User::forceCreate(['name'=>'User','email'=>'user@example.test','password'=>bcrypt('test-password'),'is_admin'=>false]), 'sanctum');
        $this->postJson('/api/player-import/preview')->assertForbidden();
        $this->postJson('/api/player-import/commit')->assertForbidden();
    }
    public function test_preview_and_replay_preserve_player_and_qr(): void {
        $this->admin();
        Team::create(['name'=>'Putra Ratu']);
        $response=$this->preview("\xEF\xBB\xBF".$this->header()."AHMAD RIFA'I,\"Sidoarjo, 1 Mei 2013\",SD TEST,ssb putra ratu,03,\n");
        $response->assertOk()->assertJsonPath('rows.0.status','ready');
        $this->assertDatabaseCount('players',0);
        $ticket=$response->json('rows.0.ticket');
        $created=$this->postJson('/api/player-import/commit',['ticket'=>$ticket])->assertCreated();
        $this->assertSame(3,$created->json('player.jersey_number'));
        $qr=$created->json('player.qr_code');
        $this->assertNotEmpty($qr);
        $this->postJson('/api/player-import/commit',['ticket'=>$ticket])->assertOk()->assertJsonPath('status','duplicate');
        $this->assertDatabaseCount('players',1);
        $this->assertSame($qr,Player::first()->qr_code);
        $this->getJson('/api/players/by-qr/'.$qr)->assertOk()->assertJsonPath('school_origin','SD TEST');
    }
    public function test_duplicates_unknown_club_and_invalid_photo_are_reported(): void {
        $this->admin(); $team=Team::create(['name'=>'Test']);
        Player::create(['name'=>'Existing Player','team_id'=>$team->id,'jersey_number'=>1,'qr_code'=>'FIXED']);
        $response=$this->preview($this->header()." existing   player,,,Test,2,\nNew,,,Test,3,\nNEW,,,Test,4,\nUnknown,,,Absent,5,\nBad,,,Test,6,http://127.0.0.1/a.jpg\n");
        $response->assertOk()->assertJsonPath('rows.0.status','duplicate')->assertJsonPath('rows.1.status','ready')
            ->assertJsonPath('rows.2.status','duplicate')->assertJsonPath('rows.3.status','error')->assertJsonPath('rows.4.status','error');
        $this->assertDatabaseCount('players',1);
        $this->assertSame('FIXED',Player::first()->qr_code);
    }
    public function test_bad_headers_and_tampered_tickets_are_rejected(): void {
        $this->admin();
        $this->preview("name,team\nA,B\n")->assertUnprocessable();
        $this->postJson('/api/player-import/commit',['ticket'=>'tampered'])->assertUnprocessable();
    }
    public function test_failed_photo_does_not_create_player(): void {
        $this->admin(); Team::create(['name'=>'Test']);
        Storage::fake('public'); Http::fake(['*'=>Http::response('Forbidden',403)]);
        $ticket=$this->preview($this->header()."Photo,,,Test,2,https://drive.google.com/open?id=123456789012345\n")->assertOk()->json('rows.0.ticket');
        $this->postJson('/api/player-import/commit',['ticket'=>$ticket])->assertUnprocessable();
        $this->assertDatabaseCount('players',0);
        $this->assertSame([],Storage::disk('public')->allFiles());
    }
    public function test_semicolon_file_and_deleted_team(): void {
        $this->admin(); $team=Team::create(['name'=>'Test']);
        $ticket=$this->preview("name;birth_place_date;school_origin;team_name;jersey_number;photo_url\nPlayer;City, date;School;Test;9;\n")->assertOk()->json('rows.0.ticket');
        $team->delete();
        $this->postJson('/api/player-import/commit',['ticket'=>$ticket])->assertUnprocessable();
        $this->assertDatabaseCount('players',0);
    }
    public function test_photo_is_saved_and_private_redirect_is_blocked(): void {
        $this->admin(); Team::create(['name'=>'Test']); Storage::fake('public');
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHf0AAAAASUVORK5CYII=');
        Http::fake(['*'=>Http::response($png,200,['Content-Type'=>'image/png'])]);
        $ticket=$this->preview($this->header()."Photo,,,Test,2,https://drive.google.com/open?id=123456789012345\n")->json('rows.0.ticket');
        $result=$this->postJson('/api/player-import/commit',['ticket'=>$ticket])->assertCreated();
        $path=explode('/storage/',$result->json('player.photo'))[1];
        Storage::disk('public')->assertExists($path);
        $this->assertSame($png,Storage::disk('public')->get($path));
    }
    public function test_redirect_to_private_host_is_not_requested(): void {
        $this->admin(); Team::create(['name'=>'Test']); Storage::fake('public');
        Http::fake(['*'=>Http::response('',302,['Location'=>'http://127.0.0.1/private'])]);
        $ticket=$this->preview($this->header()."Photo,,,Test,2,https://drive.google.com/open?id=123456789012345\n")->json('rows.0.ticket');
        $this->postJson('/api/player-import/commit',['ticket'=>$ticket])->assertUnprocessable();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('players',0);
    }
}
