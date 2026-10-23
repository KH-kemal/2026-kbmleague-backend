# Perbaikan laporan keamanan ? 20 Oktober 2026

Perubahan backend lokal; tidak menghapus akun, pemain, klub, token hosting, atau QR.
Tidak ada migrasi database, paket Composer, atau perubahan frontend yang diperlukan.

## Temuan laporan dan hasil pemeriksaan kode

- Contoh mass assignment tidak membuktikan eskalasi admin: responsnya is_admin=false dan controller lama menetapkan false secara eksplisit. Namun model User sebelumnya mengizinkan is_admin di fillable. Sekarang fillable hanya name/email/password, default is_admin=false, dan payload privilege ditolak (422 saat registrasi dibuka).
- Tidak adanya throttle autentikasi terkonfirmasi pada route lama. Rate limit dipasang sebelum controller sehingga hashing/pembuatan akun ikut dibatasi.
- Registrasi publik ditutup default, respons 403 tanpa membuat akun/token. Ini menghentikan spam akun melalui endpoint tersebut setelah deployment.
- CORS wildcard diganti allowlist origin tepat. Ini membatasi pembacaan cross-origin browser, bukan mencegah curl/bot atau menggantikan autentikasi.
- Header API mencakup nosniff, DENY/frame-ancestors, no-referrer, Permissions-Policy, dan CSP ketat untuk JSON API. Header tidak dipasang ke frontend sehingga kamera screening frontend tidak dimatikan.
- X-XSS-Protection=0 sengaja menonaktifkan auditor browser lama yang deprecated; tidak memakai rekomendasi lama 1; mode=block.
- 404 API menjadi pesan generik, termasuk model tidak ditemukan.
- Status is_admin untuk akun sendiri tetap tersedia pada login/me. Menyembunyikannya secara global akan memutus navigasi admin frontend; mengetahui nama atribut bukan bukti akses admin.
- Verifikasi email belum ditambahkan: SMTP/verification flow belum tersedia. Registrasi tetap ditutup. Jika sengaja dibuka lagi, perlindungan email/CAPTCHA perlu dirancang, bukan dianggap sudah selesai.
- Header Server: LiteSpeed dan file statis berada di lapisan server; penghapusan identitas server perlu konfigurasi Rumahweb, bukan Eloquent/controller.

## File produksi yang berubah

- app/Http/Controllers/Auth/AuthController.php
- app/Http/Middleware/ApiSecurityHeaders.php (baru)
- app/Models/User.php
- app/Providers/AppServiceProvider.php
- bootstrap/app.php
- config/cache.php
- config/cors.php (baru)
- config/security.php (baru)
- routes/api.php

## Pemasangan hosting

1. Cadangkan file PHP/config di atas. ZIP patch hanya untuk backend KBMLeague yang sudah berjalan dengan update sebelumnya.
2. Extract ZIP backend langsung di /home/kbmf2918/laravel-app, overwrite file sama. Jangan extract di public_html/api.
3. Pada .env hosting, pastikan APP_ENV=production, APP_DEBUG=false, PUBLIC_REGISTRATION_ENABLED=false.
4. Pastikan storage/framework/cache/data bisa ditulis oleh proses PHP. CACHE_LIMITER_STORE default file, tidak perlu menambahnya jika belum ada.
5. Di Terminal cPanel:

~~~sh
cd /home/kbmf2918/laravel-app
/opt/cpanel/ea-php84/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php84/root/usr/bin/php artisan route:clear
~~~

Tidak perlu migrate, migrate:fresh, seed, reset database, atau upload .env lokal.
Frontend hosting yang ada bisa tetap digunakan; form daftar akan menampilkan pesan registrasi ditutup.
Existing admin login, upload foto, CSV import, screening, dan API publik tetap tersedia.

Jika memakai CDN/proxy cache untuk /api, pastikan request POST tidak dicache. Header CORS tambahan dari server/CDN harus diselaraskan jika masih menimpa allowlist aplikasi.

## Pengaturan dan batas

PUBLIC_REGISTRATION_ENABLED=false adalah default. Jangan membuka kembali hanya untuk menguji serangan pada hosting.
Login: 5/menit per pasangan IP-email; 20/menit per IP; 15/15 menit per email (semua percobaan).
Registrasi: 2/menit dan 5/jam per IP, termasuk saat ditutup.
Bila 429 muncul, tunggu Retry-After; jangan menghapus cache limiter berulang kali karena membuka ulang batas spam.
Pembatasan account/email juga dapat mengunci login sementara bila ada serangan ke email tersebut; batas tidak mengganti MFA.

Default origin produksi:
https://kbmleague.my.id
https://www.kbmleague.my.id

Testing frontend localhost terhadap API hosting perlu tambahan persis di .env hosting, misalnya:
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
Kemudian config:clear. Jangan memakai * atau origin null.
Mode backend local otomatis mengizinkan localhost/127.0.0.1 port 5173 dan 5174.
Tidak ada perubahan trust proxy: header X-Forwarded-For dari klien tidak dipercaya sembarangan. Jika hosting memakai reverse proxy, konfigurasikan hanya proxy yang diverifikasi tepercaya, bukan trust semua alamat.

## Akun spam yang sudah terbuat

Patch mencegah registrasi baru; tidak otomatis menghapus akun/token yang sudah tersimpan.
Periksa dan bersihkan akun spam teridentifikasi melalui phpMyAdmin sesuai rentang yang telah diverifikasi, termasuk token milik akun tersebut.
Jangan menghapus semua user, semua token, atau akun admin untuk memasang patch.
Token/password dalam laporan pengujian tidak dimasukkan ke source code/paket ini.

## Batas klaim pengujian

Tes berjalan di SQLite in-memory dan cache limiter array terisolasi pada environment testing.
Tidak ada fuzzing/flood, percobaan login, registrasi, atau mutasi ke hosting dilakukan untuk tes ini.
Sesudah dipasang, validasi satu login admin normal dan cek halaman publik. Koordinasikan pengujian serangan ulang agar tidak memenuhi database produksi.
Paket aplikasi tidak menjamin perlindungan DDoS volumetrik; serangan jaringan perlu mitigasi hosting/WAF.

## Referensi

- Laravel rate limit: https://laravel.com/docs/12.x/routing#rate-limiting
- X-XSS-Protection deprecated: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-XSS-Protection
- CSP frame-ancestors: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/frame-ancestors

Verifikasi akhir: 45 tes, 413 assertions lulus. Patch hosting 9 file tersedia di exports/04_UPDATE_TERAKHIR/Update_Keamanan_20261020 (di luar repository backend).
