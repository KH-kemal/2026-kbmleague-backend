# Berita KBM League ? 23 Oktober 2026

Fitur dibuat dan diuji di lokal; tidak ada ZIP, push GitHub, atau deployment hosting.

## Penggunaan
Admin > Berita > Tambah Berita. Isi judul, kategori, gambar sampul, ringkasan dan isi lengkap.
Simpan Berita langsung menampilkan berita ke publik; tidak ada field status, draft, jadwal terbit, atau langkah penerbitan terpisah.
Kategori: Pertandingan, Kegiatan, Pengumuman.
Upload JPG/PNG/WebP maksimal 3 MB melalui /api/upload type=news; tersimpan di disk public folder news.
Judul maksimal 180 karakter, ringkasan 500, isi 50.000. Isi ditampilkan sebagai teks biasa dengan paragraf, bukan HTML tepercaya.
Edit langsung memperbarui publik. Hapus meminta konfirmasi dan menghapus berita dari feed/detail.
File gambar lama tidak otomatis dihapus karena bisa digunakan berita lain.

## Tampilan publik
Beranda bagian bawah: tiga berita terbaru (created_at lalu ID descending).
Desktop: gambar kiri, judul/ringkasan kanan. Mobile: gambar di atas, teks di bawah.
/news: daftar berita dengan filter kategori dan paginasi sembilan per halaman.
/news/:id: halaman lengkap dengan gambar, tanggal, ringkasan, dan paragraf.
Tidak ada konten palsu yang diisikan pada database lokal; kondisi awal kosong.
Tautan Lihat semua berita tersedia di Beranda, serta Berita di footer.

## Backend
GET /api/news: publik, paginasi per_page 1?30, filter category/search, tanpa content lengkap dalam daftar.
GET /api/news/:id: publik, isi lengkap.
POST/PUT/DELETE /api/news: hanya admin (Sanctum + middleware admin).
Model News hanya fillable title/excerpt/cover_image/category/content; tidak ada field status.
URL sampul harus HTTP/HTTPS; data:/javascript: ditolak. Backend tidak mengunduh URL sembarang.

Migrasi database/migrations/2026_10_23_000001_create_news_table.php menambah tabel news.
Migrasi sudah diterapkan pada MySQL localhost setelah environment local dan host diverifikasi. Tidak menyentuh database hosting atau data pemain/QR.
Untuk salinan lokal lain, jalankan migrasi path tersebut pada koneksi lokal yang benar.
Paket hosting akan dibuat hanya setelah aba-aba pengguna; backend hosting lama belum memiliki endpoint/tabel ini.

## Verifikasi
57 tes / 550 assertions lulus di SQLite in-memory; mencakup role admin/nonadmin, validasi, upload, urutan terbaru, filter, paginasi, edit/hapus dan regresi fitur lama.
ESLint seluruh file frontend yang berubah untuk fitur ini lulus; build berhasil dengan peringatan bundle besar Vite.
Browser lokal dengan API tiruan: tiga kartu Beranda, layout horizontal desktop, 320/375/1280 terang/gelap, isi teks tidak menjalankan script, upload gambar, tambah/edit/hapus dan tidak adanya input status.
Endpoint lokal nyata /api/news?per_page=3 sudah terbaca; total awal 0. Tidak ada berita uji disimpan pada database aplikasi asli.
