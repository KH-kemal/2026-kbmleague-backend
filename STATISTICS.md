# Statistik pemain ? pengembangan lokal 22 Oktober 2026

Halaman publik: /statistics, menu Statistik.
API publik: GET /api/statistics, filter opsional team_id.
Tidak dibuat ZIP, tidak dipush atau diterapkan ke hosting.

## Alur admin
Buka Kelola Jadwal > hasil pertandingan.
- Pencetak Gol: tim penerima gol, jenis biasa/penalti/bunuh diri, pemain, menit.
- Gol bunuh diri memilih pemain dari tim lawan; skor tim penerima tetap diisi manual.
- Penyelamatan Kiper: pilih tim, pemain yang bertugas sebagai kiper, dan jumlah saves. Bisa dua atau lebih kiper.
- Kiper yang sama tidak boleh berulang pada satu pertandingan.
- Jumlah saves per kiper tidak boleh melampaui saves tim jika statistik saves tim diisi.
- Kosongkan statistik tim bila total belum tersedia; tidak dihitung otomatis.
- Pilih Selesai dan simpan untuk memasukkan hasil ke peringkat.

## Aturan peringkat
Hanya fixture completed. Live dan scheduled tidak masuk.
Gol biasa dan penalti selama pertandingan dihitung; own_goal dikecualikan.
Adu penalti tidak dimasukkan ke form gol/saves pertandingan.
Saves dihitung dari rincian goalkeeper_saves, tidak ditebak dari saves tim lama.
Nilai sama mendapat rank sama (1, 1, 3), urutan nama sebagai urutan tampilan.
Agregasi per pemain dan klub yang diwakili saat pertandingan; pindah klub tidak memindahkan catatan lama.
matches_recorded adalah pertandingan dengan gol/saves yang dicatat, BUKAN seluruh penampilan; tidak ditampilkan sebagai statistik Main.
Peringkat dihitung dari catatan saat request sehingga edit, status ulang dan hapus pertandingan tidak menggandakan total.
Pemain dengan nol gol/saves tidak dimasukkan peringkat.
Nama historis pemain dipertahankan jika pindah klub atau dihapus.
API memakai daftar field eksplisit: nama, foto, klub, jumlah, rank, ketersediaan profil. Sekolah/tanggal lahir/QR tidak dikirim.
Halaman melakukan refresh setiap 30 detik dan punya tombol refresh.
Ringkasan total gol memakai skor termasuk gol tanpa rincian pemain; peringkat hanya memakai gol yang sudah dirinci.

## Database lokal
Migrasi tambahan:
database/migrations/2026_10_22_000001_add_goalkeeper_saves_to_fixtures.php
Menambah kolom JSON nullable goalkeeper_saves pada fixtures. Tidak menghapus data.
Migrasi telah diterapkan hanya pada koneksi MySQL localhost yang diverifikasi environment local.
Tidak ada migrasi atau perubahan di hosting.

Jika menyiapkan salinan lokal lain:
php artisan migrate --path=database/migrations/2026_10_22_000001_add_goalkeeper_saves_to_fixtures.php

Frontend lokal harus menunjuk backend lokal yang telah dimigrasi.
Backend hosting lama belum memiliki endpoint statistik atau kolom baru.

## Validasi
51 tes backend / 495 assertions lulus pada SQLite in-memory.
Lint file frontend yang berubah dan build produksi berhasil (peringatan bundle besar Vite masih ada).
UI diuji melalui browser dengan API tiruan: tab, filter, lebar 320/375/768/1024/1280, input saves dan gol bunuh diri.
API backend lokal juga telah dibaca; hasil saat diperiksa 2 pertandingan selesai, 3 gol, dan belum ada saves kiper yang dirinci.
Data contoh pada pemeriksaan browser tidak dimasukkan ke database asli.
