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

`GET /api/teams`, `/players`, `/fixtures`, `/league-table`, dan `/dashboard-stats` bersifat publik. `POST /api/register` membuat akun pengguna biasa; `POST /api/login` mengembalikan token Sanctum. `/api/me` dan `/api/logout` memerlukan token. Seluruh endpoint perubahan data, unggahan, dan QR screening memerlukan token admin.

Buat admin pertama dengan mendaftarkan akun biasa, lalu jalankan `php artisan tinker`:

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
