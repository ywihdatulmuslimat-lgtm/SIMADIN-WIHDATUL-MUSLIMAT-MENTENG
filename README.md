# Administrasi Yayasan Wihdatul Muslimat

Aplikasi PHP 8+ dan MariaDB/MySQL untuk pengelolaan yayasan. Folder ini berdiri terpisah dari proyek Madrasah Diniyah.

## Jalankan di XAMPP

1. Pastikan project berada di `C:\xampp\htdocs\madrasah-wm\yayasan-wm`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Impor `database/database.sql` melalui phpMyAdmin atau jalankan:

   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\madrasah-wm\yayasan-wm\database\database.sql
   ```

4. Buka `http://localhost/madrasah-wm/yayasan-wm/login.php`.

Database default `db_yayasan_wm`, pengguna MySQL `root`, tanpa password. Sesuaikan `config/database.php` melalui environment variables `YAYASAN_DB_HOST`, `YAYASAN_DB_PORT`, `YAYASAN_DB_NAME`, `YAYASAN_DB_USER`, dan `YAYASAN_DB_PASS`.

## Akun awal

- Username: `admin`
- Password awal: `Admin123!`
- Aplikasi mewajibkan password baru minimal 12 karakter pada login pertama.

## Perlindungan data lama

Skema menggunakan `CREATE TABLE IF NOT EXISTS`; tidak ada perintah `DROP`, `TRUNCATE`, atau penggantian password/pengguna lama. Tabel lama `users` tetap dibiarkan dan autentikasi aplikasi baru memakai `app_users`.

Database ini telah memiliki data sebelumnya di `dashboard_records`. Jalankan migrasi salin berikut hanya sekali setelah backup:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\madrasah-wm\yayasan-wm\database\migrate_legacy_data.php
```

Migrasi menyalin record aktif Anak Asuh dan Lansia ke tabel terstruktur tanpa menghapus atau mengubah payload sumber. Identitas hasil migrasi diberi awalan `LEGACY-`; record sumber tetap ada di `dashboard_records`.

## Cakupan

- Autentikasi, ganti password awal, role server-side, CSRF, audit log.
- Dashboard statistik MySQL, grafik dan agenda.
- Profil/kelengkapan lembaga, lokasi, anak asuh, lansia, bantuan, program, fasilitas, galeri, legalitas/akreditasi, pendidikan, pengguna, laporan.
- CRUD, detail, pencarian, filter status, pagination, upload JPG/PNG/PDF maksimal 5 MB, unduh file privat.
- Ekspor CSV untuk Excel dan cetak browser / Simpan sebagai PDF.
- Backup SQL tanpa `DROP` dan insert `IGNORE`, sehingga restore tidak menimpa tabel/baris yang ada.

Laporan PDF dibuat melalui dialog cetak browser. Akun Ketua Yayasan memiliki akses baca saja; akses Petugas dibatasi menurut modul dan data sensitif tidak ditampilkan dalam daftar/ekspor.
