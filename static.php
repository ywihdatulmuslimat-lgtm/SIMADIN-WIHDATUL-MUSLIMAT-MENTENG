<?php
declare(strict_types=1);
?>
<?php if ($page === 'hak_akses'): ?>
<section class="panel">
    <h2>Matriks Hak Akses</h2>
    <div class="table-wrap"><table><thead><tr><th>Peran</th><th>Hak pada Aplikasi</th></tr></thead><tbody>
        <tr><td>Super Admin</td><td>Kelola seluruh data, pengguna, pengaturan, backup, dan log aktivitas.</td></tr>
        <tr><td>Kepala Madrasah</td><td>Melihat data dan laporan; tidak dapat mengubah data atau mengelola akun.</td></tr>
        <tr><td>Operator</td><td>Mengelola data operasional dan kelembagaan; tidak dapat mengubah peran atau mengunduh backup.</td></tr>
        <tr><td>Guru</td><td>Melihat dashboard, kegiatan, kalender, pengumuman, dan informasi profil yang tersedia.</td></tr>
    </tbody></table></div>
    <p class="notice">Akses data akademik untuk akun Guru dibatasi sampai akun dapat ditautkan secara khusus ke data guru dan kelas ajarnya.</p>
</section>
<?php elseif ($page === 'profil_aplikasi'): ?>
<?php
$identity = db()->query('SELECT official_name, nsm, npsn, institution_status, level, phone, email FROM madrasah_identity WHERE id = 1')->fetch() ?: [];
$location = db()->query('SELECT city, province FROM madrasah_location WHERE id = 1')->fetch() ?: [];
?>
<section class="panel">
    <h2>Profil Aplikasi</h2>
    <p>Identitas yang ditampilkan aplikasi menggunakan data kelembagaan yang diisi oleh operator madrasah.</p>
    <dl class="detail-grid">
        <div><dt>Nama Resmi</dt><dd><?= e($identity['official_name'] ?? 'Belum diisi') ?></dd></div>
        <div><dt>NSM</dt><dd><?= e($identity['nsm'] ?? 'Belum diisi') ?></dd></div>
        <div><dt>NPSN</dt><dd><?= e($identity['npsn'] ?? 'Belum diisi') ?></dd></div>
        <div><dt>Status / Jenjang</dt><dd><?= e(trim(($identity['institution_status'] ?? '') . ' / ' . ($identity['level'] ?? ''), ' /') ?: 'Belum diisi') ?></dd></div>
        <div><dt>Telepon / Email</dt><dd><?= e(trim(($identity['phone'] ?? '') . ' / ' . ($identity['email'] ?? ''), ' /') ?: 'Belum diisi') ?></dd></div>
        <div><dt>Wilayah</dt><dd><?= e(trim(($location['city'] ?? '') . ', ' . ($location['province'] ?? ''), ', ') ?: 'Belum diisi') ?></dd></div>
    </dl>
    <div class="form-actions"><a class="btn btn-primary" href="index.php?page=identitas">Edit Identitas</a><a class="btn" href="index.php?page=lokasi">Edit Lokasi</a></div>
</section>
<?php elseif ($page === 'kenaikan_kelas'): ?>
<section class="panel">
    <h2>Kenaikan Kelas</h2>
    <p>Proses kenaikan massal belum diaktifkan. Data kelas santri tidak akan diubah di halaman ini.</p>
    <p class="notice">Riwayat kenaikan kelas pada basis data yang tersedia terhubung ke tabel siswa legacy yang berbeda dari tabel santri SIMADIN. Menjalankan pemindahan sebelum pemetaan data disepakati berisiko mengaitkan riwayat ke santri yang salah.</p>
    <a class="btn btn-primary" href="index.php?page=santri">Buka Data Santri</a>
</section>
<?php else: ?>
<section class="panel">
    <h2><?= e($title) ?></h2>
    <p>Modul ini belum tersedia pada versi SIMADIN saat ini. Data yang ada tidak diubah.</p>
</section>
<?php endif; ?>
