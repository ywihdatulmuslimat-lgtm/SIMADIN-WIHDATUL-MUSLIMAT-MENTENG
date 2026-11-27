WEBSITE MADRASAH DINIYAH WIHDATUL MUSLIMAT

Isi folder:
- index.html : website administrasi madrasah (HTML/CSS/JavaScript)
- assets/logo-wihdatul-muslimat.jpeg : file logo asli

Cara menjalankan:
1. Ekstrak ZIP.
2. Buka folder hasil ekstrak di Visual Studio Code.
3. Buka index.html menggunakan browser, atau gunakan ekstensi Live Server.

Catatan:
Versi dalam ZIP ini adalah versi HTML yang sudah dibuat sebelumnya. Ini belum merupakan aplikasi PHP + MySQL dan belum memiliki database server online. Integrasi MySQL memerlukan file PHP, konfigurasi database, serta skema tabel tersendiri.

Pembaruan antarmuka HTML:
- Data Santri menyediakan tab kelas 1-6, Semua Kelas, filter tahun/status, pencarian gabungan, jumlah otomatis, dan tabel identitas.
- Navigasi SIMADIN hanya menampilkan modul administrasi madrasah yang digunakan; modul non-SIMADIN tidak ditampilkan dan data lokal lama tidak dihapus.
- Legalitas dan Perizinan menyediakan daftar IZOP, CRUD, pratinjau/unduh, serta unggah PDF/JPG/JPEG/PNG.
- Kegiatan Madrasah menyediakan CRUD, filter kategori/tahun, pratinjau foto, galeri, dan lampiran PDF.
- Profil guru tidak lagi menampilkan tab Riwayat Mengajar; data guru dan arsip SK tetap tersedia.
- Menu Data Pengguna dan tombol Tambah Kegiatan/Data Santri yang bersebelahan pada Dashboard prototipe disembunyikan. Kartu agenda lokal dapat dibuka untuk melihat detail.
- Arsip SK, dokumen, lampiran, dan data formulir pada prototipe disimpan di localStorage browser dengan batas ukuran kecil. Gunakan data DEMO saja; file HTML tidak menyediakan autentikasi, proteksi akses, server upload aman, atau koneksi MySQL.
