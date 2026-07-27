# KickRank Backend

Backend API untuk project KickRank, dibangun dengan Laravel 12.

## Fungsi Utama

- Menyediakan API untuk tim, pemain, fixture, statistik pemain, klasemen, dan leaderboard.
- Mengelola relasi Eloquent antara tim, pemain, pertandingan, dan statistik.
- Menyediakan endpoint untuk dipakai frontend React.

## Teknologi

- Laravel 12
- PHP 8.3+
- Eloquent ORM
- Laravel Sanctum
- MySQL / MariaDB

## Menjalankan Project

```bash
composer install
php artisan migrate
php artisan serve
```

## Endpoint Utama

- `GET /api/teams`
- `GET /api/players`
- `GET /api/fixtures`
- `GET /api/player-stats`
- `GET /api/league-table`
- `GET /api/leaderboard`

## Struktur Singkat

- `app/Http/Controllers/Team` untuk controller tim.
- `app/Http/Controllers/Player` untuk controller pemain.
- `app/Http/Controllers/Fixture` untuk controller pertandingan.
- `app/Http/Controllers/PlayerStat` untuk statistik pemain.
- `app/Http/Controllers/Leaderboard` untuk klasemen papan peringkat.

## Catatan

File seperti `vendor`, `.env`, dan cache Laravel tidak perlu di-push ke GitHub karena sudah diabaikan melalui `.gitignore`.
