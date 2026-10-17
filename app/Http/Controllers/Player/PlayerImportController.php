<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Player\Player;
use App\Models\Team\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlayerImportController extends Controller
{
    private const COLUMNS = ['name', 'birth_place_date', 'school_origin', 'team_name', 'jersey_number', 'photo_url'];

    private function normal(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function team(string $name): ?Team
    {
        $key = preg_replace('/^ssb\s+/u', '', $this->normal($name));
        $matches = Team::all()->filter(fn ($t) => preg_replace('/^ssb\s+/u', '', $this->normal($t->name)) === $key);
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function existing(string $name): ?Player
    {
        return Player::all()->first(fn ($p) => $this->normal($p->name) === $this->normal($name));
    }

    private function driveId(string $url): ?string
    {
        if ($url === '') return null;
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'drive.google.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return null;
        parse_str($parts['query'] ?? '', $query);
        $id = $query['id'] ?? null;
        if (preg_match('~^/file/d/([a-zA-Z0-9_-]+)(?:/|$)~', $parts['path'] ?? '', $m)) $id = $m[1];
        return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{10,150}$/', $id) ? $id : null;
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|max:2048']);
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        try {
            $first = fgets($handle);
            if ($first === false) throw ValidationException::withMessages(['file' => 'CSV kosong.']);
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $separator = str_contains($first, ';') ? ';' : ',';
            $header = str_getcsv(trim($first), $separator, '"', '');
            if ($header !== self::COLUMNS) throw ValidationException::withMessages(['file' => 'Kolom tidak sesuai. Gunakan template CSV UTF-8.']);
            $rows = []; $seen = []; $line = 1;
            while (($cells = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
                $line++;
                if (count($cells) === 1 && trim($cells[0] ?? '') === '') continue;
                if (count($rows) >= 200) throw ValidationException::withMessages(['file' => 'Maksimal 200 pemain per file.']);
                if (count($cells) !== 6) {
                    $rows[] = ['line' => $line, 'name' => '', 'status' => 'error', 'message' => 'Jumlah kolom harus 6.'];
                    continue;
                }
                $data = array_combine(self::COLUMNS, array_map(fn ($s) => trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $cells));
                $v = Validator::make($data, [
                    'name' => 'required|string|max:255', 'birth_place_date' => 'nullable|string|max:255',
                    'school_origin' => 'nullable|string|max:255', 'team_name' => 'required|string|max:255',
                    'jersey_number' => ['required', 'regex:/^[0-9]{1,3}$/'], 'photo_url' => 'nullable|string|max:2048',
                ]);
                $errors = $v->errors()->all();
                $team = $this->team($data['team_name']);
                if (!$team) $errors[] = 'Klub belum tersedia atau ambigu. Tambahkan klub melalui Manajemen Tim terlebih dahulu.';
                if ($data['photo_url'] !== '' && !$this->driveId($data['photo_url'])) $errors[] = 'Foto harus berupa tautan Google Drive HTTPS publik.';
                $key = $this->normal($data['name']);
                $duplicate = isset($seen[$key]) || $this->existing($data['name']);
                $seen[$key] = true;
                $status = $errors ? 'error' : ($duplicate ? 'duplicate' : 'ready');
                $message = $errors ? implode(' ', $errors) : ($duplicate ? 'Nama sama ditemukan. Dilewati; periksa identitas sebelum menambah manual.' : 'Siap diimpor');
                if ($status === 'ready' && $team->players()->where('jersey_number', (int) $data['jersey_number'])->exists()) $message .= ' ? nomor punggung sudah dipakai.';
                if ($status === 'ready' && $data['photo_url'] === '') $message .= ' ? tanpa foto.';
                $row = array_merge($data, ['line' => $line, 'status' => $status, 'message' => $message]);
                if ($status === 'ready') {
                    $row['ticket'] = Crypt::encryptString(json_encode([
                        'user' => $request->user()->id, 'expires' => time() + 7200,
                        'data' => $data, 'team_id' => $team->id,
                    ], JSON_THROW_ON_ERROR));
                }
                $rows[] = $row;
            }
            if (!$rows) throw ValidationException::withMessages(['file' => 'CSV tidak memiliki data pemain.']);
            return response()->json(['rows' => $rows]);
        } finally {
            fclose($handle);
        }
    }

    private function photo(string $id): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'kbm-photo-');
        $url = 'https://drive.google.com/uc?export=download&id='.rawurlencode($id);
        $deadline = microtime(true) + 20;
        try {
            for ($i = 0; $i < 4; $i++) {
                $parts = parse_url($url);
                if (($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', ['drive.google.com', 'drive.usercontent.google.com'], true)
                    || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) throw new \RuntimeException('Redirect foto tidak diizinkan.');
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) throw new \RuntimeException('Unduhan foto timeout.');
                $response = Http::connectTimeout(5)->timeout($remaining)->withOptions([
                    'allow_redirects' => false, 'sink' => $temp,
                    'progress' => function ($total, $downloaded) {
                        if ($total > 10 * 1024 * 1024 || $downloaded > 10 * 1024 * 1024) throw new \RuntimeException('Foto maksimal 10 MB.');
                    },
                ])->get($url);
                if ($response->redirect()) { $url = $response->header('Location'); continue; }
                if (!$response->successful()) throw new \RuntimeException('Foto tidak dapat diunduh.');
                $info = @getimagesize($temp);
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
                if (!$ext || filesize($temp) > 10 * 1024 * 1024) throw new \RuntimeException('Foto harus JPG, PNG, atau WebP maksimal 10 MB; PDF tidak didukung.');
                $path = 'players/import-'.Str::uuid().'.'.$ext;
                $stream = fopen($temp, 'rb');
                try {
                    if (!Storage::disk('public')->put($path, $stream)) throw new \RuntimeException('Penyimpanan foto gagal.');
                } finally { fclose($stream); }
                return $path;
            }
            throw new \RuntimeException('Terlalu banyak redirect.');
        } finally { @unlink($temp); }
    }

    public function store(Request $request)
    {
        $request->validate(['ticket' => 'required|string|max:15000']);
        try { $ticket = json_decode(Crypt::decryptString($request->ticket), true, 512, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw ValidationException::withMessages(['ticket' => 'Pratinjau tidak valid. Unggah ulang CSV.']); }
        if ($ticket['user'] !== $request->user()->id || $ticket['expires'] < time()) {
            throw ValidationException::withMessages(['ticket' => 'Pratinjau kedaluwarsa atau bukan milik akun ini. Unggah ulang CSV.']);
        }
        $data = $ticket['data'];
        $lock = Cache::lock('player-import-'.hash('sha256', $this->normal($data['name'])), 120);
        if (!$lock->get()) return response()->json(['message' => 'Pemain sedang diproses. Coba ulang.'], 409);
        $path = null;
        try {
            if ($this->existing($data['name'])) return response()->json(['status' => 'duplicate', 'message' => 'Pemain sudah ada; data dan QR lama dipertahankan.']);
            if (!Team::find($ticket['team_id'])) throw ValidationException::withMessages(['team' => 'Klub sudah dihapus. Unggah ulang CSV.']);
            if ($data['photo_url'] !== '') {
                try { $path = $this->photo($this->driveId($data['photo_url'])); }
                catch (\Throwable $e) { return response()->json(['message' => 'Foto gagal diunduh. Gunakan tautan publik JPG/PNG/WebP maksimal 10 MB. Pemain belum disimpan.'], 422); }
            }
            $player = DB::transaction(fn () => Player::create([
                'team_id' => $ticket['team_id'], 'name' => $data['name'],
                'birth_place_date' => $data['birth_place_date'] ?: null,
                'school_origin' => $data['school_origin'] ?: null,
                'jersey_number' => (int) $data['jersey_number'], 'is_active' => true,
                'photo' => $path ? rtrim(config('app.url'), '/').'/storage/'.$path : null,
                'qr_code' => 'KBM-PL-'.strtoupper((string) Str::uuid()),
            ]));
            return response()->json(['status' => 'created', 'player' => $player->load('team')], 201);
        } catch (\Throwable $e) {
            if ($path) Storage::disk('public')->delete($path);
            throw $e;
        } finally { $lock->release(); }
    }
}
