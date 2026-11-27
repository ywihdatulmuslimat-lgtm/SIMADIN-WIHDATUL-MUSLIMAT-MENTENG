# Progres Administrasi Yayasan

## Sudah dibuat
- Aplikasi PHP modular terpisah, UI responsif dan logo asli.
- Skema MariaDB non-destruktif, akun role-aware, koneksi PDO.
- Login, ganti password wajib, session cookie flags, CSRF, audit log, role checks.
- Dashboard statistik database dan kalender program.
- Profil kelembagaan dengan kelengkapan otomatis dan aksi cepat.
- CRUD generik untuk anak asuh, lansia, orang tua/kontak, bantuan, program, aset, lokasi, foto, dokumen legalitas/akreditasi, madrasah, guru, santri, kelas, jadwal, absensi, nilai, rapor, keuangan, pengguna.
- Validasi NIK/KK, usia dari tanggal lahir, upload privat tervalidasi, pencarian, filter status, pagination, CSV, cetak/PDF browser.
- Migrasi salin legacy anak asuh/lansia tanpa menghapus sumber.

## Perlu verifikasi operasional lanjutan
- Uji browser untuk setiap role dan setiap form CRUD.
- Uji unggah/unduh tiap jenis file, backup/restore di database cadangan.
- Evaluasi aturan role dengan pengurus yayasan sebelum produksi.
- Penggunaan production memerlukan kredensial database non-root dan HTTPS.
