# KBMLeague Backend

Laravel 12 REST API untuk tim, pemain, fixture, klasemen, dan dashboard. Database dimulai tanpa data contoh.

## Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Atur `DB_*` di `.env`, lalu:

```bash
php artisan migrate
php artisan storage:link
php artisan serve
```

PHP 8.2+ dan MySQL/MariaDB diperlukan. API default berjalan di `http://127.0.0.1:8000/api`.

## Akses

`GET /api/teams`, `/players`, `/fixtures`, `/league-table`, dan `/dashboard-stats` bersifat publik. `POST /api/register` dinonaktifkan secara default (PUBLIC_REGISTRATION_ENABLED=false); `POST /api/login` mengembalikan token Sanctum. `/api/me` dan `/api/logout` memerlukan token. Seluruh endpoint perubahan data, unggahan, dan QR screening memerlukan token admin.

Untuk menaikkan akun yang sudah ada menjadi admin, verifikasi email pemilik lalu jalankan `php artisan tinker`:

```php
App\Models\User::where('email', 'alamat-admin-anda@example.com')->update(['is_admin' => true]);
```

Logout dan login ulang setelah menaikkan hak akses. Ganti kata sandi bawaan pada database lama sebelum dipakai di lingkungan terbuka.

## Pemeriksaan

```bash
php artisan migrate:status
php artisan route:list --path=api
php artisan test
```

Seeder bawaan kosong. Jangan gunakan `migrate:fresh` pada database yang menyimpan data nyata karena perintah itu menghapus seluruh tabel.


## Profil pemain dan penghapusan statistik individu

Pemain memakai `school_origin` (asal sekolah) dan `birth_place_date` (tempat, tanggal lahir), keduanya VARCHAR(255) opsional. Tinggi, berat, posisi, rating, dan kolom tanggal lahir terpisah dihapus. Data tanggal lahir lama dipindahkan sebagai teks.

Fitur/API statistik pemain dan leaderboard individu dihapus; jadwal, skor pertandingan, serta klasemen tim tetap tersedia.

Sebelum menerapkan perubahan database, cadangkan data, lalu jalankan `php artisan migrate` dari folder backend. Migrasi baru menghapus tabel `player_stats` dan kolom lama; rollback tidak memulihkan isi data tersebut. Jangan menjalankan `migrate:fresh` pada data nyata.


## Pengujian profil pemain

Tes memakai SQLite in-memory (diatur pada phpunit.xml), bukan database aplikasi. Jika driver SQLite belum diaktifkan pada PHP lokal, jalankan dari backend:

```bash
php -d extension=pdo_sqlite vendor/bin/phpunit --filter PlayerProfileTest
```

## Keamanan autentikasi (20 Oktober 2026)

Registrasi publik ditutup secara default untuk menghentikan spam. Login akun yang sudah ada tetap tersedia.
Jika registrasi sengaja dibuka kembali, akun baru selalu nonadmin; field is_admin/role/admin/email_verified_at ditolak.
Password pendaftaran baru minimal 12 karakter; password akun lama tidak diubah.

Login dibatasi 5 request/menit per pasangan IP-email, 20/menit per IP, dan 15/15 menit per email.
Registrasi dibatasi 2/menit dan 5/jam per IP meskipun dibuka kembali.
HTTP 429 menyertakan Retry-After. Cache limiter default file agar tetap persisten lintas request.
Jangan memakai CACHE_LIMITER_STORE=array/null pada hosting; file cocok untuk satu server, database/Redis untuk banyak server.

CORS mengizinkan domain publik KBMLeague. Mode local juga mengizinkan localhost/127.0.0.1 port 5173/5174.
Untuk frontend lokal yang memakai API hosting, set CORS_ALLOWED_ORIGINS ke origin localhost yang tepat pada backend hosting, lalu config:clear.
CORS bukan pengganti autentikasi atau rate limit. Script/server lain tetap dapat menghubungi endpoint.

Header keamanan dipasang pada respons API, termasuk error dan preflight. HSTS hanya pada HTTPS dan tidak memaksakan includeSubDomains.
is_admin tetap dikirim sebagai status akun sendiri pada login/me agar navigasi admin frontend tetap bekerja; backend tetap memvalidasi hak akses.
Panduan penanganan laporan dan pemasangan: [SECURITY-HARDENING.md](SECURITY-HARDENING.md).

## Statistik pemain

Top Scorer dan Top Saves tersedia melalui GET /api/statistics. Aturan, migrasi tambahan dan alur admin ada di [STATISTICS.md](STATISTICS.md).

## Berita

Berita langsung tampil setelah disimpan admin, tanpa status draft. Panduan fitur, migrasi dan endpoint ada di [NEWS.md](NEWS.md).
