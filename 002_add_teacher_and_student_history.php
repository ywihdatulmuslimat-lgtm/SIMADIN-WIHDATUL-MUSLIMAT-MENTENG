<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Jalankan migrasi melalui PHP CLI.');
}

require __DIR__ . '/../../config/database.php';
$pdo = db();

$statements = [
    "CREATE TABLE IF NOT EXISTS santri_riwayat_kelas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        santri_id INT UNSIGNED NOT NULL,
        kelas_id INT UNSIGNED NOT NULL,
        tahun_ajaran_id INT UNSIGNED NOT NULL,
        tanggal_mulai DATE NOT NULL,
        tanggal_selesai DATE NULL,
        catatan TEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_santri_riwayat (santri_id, tahun_ajaran_id, tanggal_mulai),
        INDEX idx_santri_riwayat_kelas (kelas_id, tahun_ajaran_id),
        CONSTRAINT fk_santri_riwayat_santri FOREIGN KEY (santri_id) REFERENCES santri(id) ON DELETE RESTRICT,
        CONSTRAINT fk_santri_riwayat_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT,
        CONSTRAINT fk_santri_riwayat_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
        CONSTRAINT fk_santri_riwayat_user FOREIGN KEY (created_by) REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS santri_dokumen (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        santri_id INT UNSIGNED NOT NULL,
        jenis VARCHAR(60) NOT NULL,
        nama_dokumen VARCHAR(180) NOT NULL,
        keterangan TEXT NULL,
        file_path VARCHAR(255) NOT NULL UNIQUE,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_santri_dokumen (santri_id, jenis),
        CONSTRAINT fk_santri_dokumen_santri FOREIGN KEY (santri_id) REFERENCES santri(id) ON DELETE RESTRICT,
        CONSTRAINT fk_santri_dokumen_user FOREIGN KEY (created_by) REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS guru_sk (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        guru_id INT UNSIGNED NOT NULL,
        nomor_sk VARCHAR(100) NOT NULL,
        jenis_sk VARCHAR(100) NOT NULL,
        tanggal_penetapan DATE NOT NULL,
        tanggal_mulai DATE NOT NULL,
        tanggal_berakhir DATE NULL,
        jabatan VARCHAR(150) NULL,
        pejabat_penetap VARCHAR(150) NULL,
        keterangan TEXT NULL,
        file_path VARCHAR(255) NOT NULL UNIQUE,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_guru_sk_nomor (guru_id, nomor_sk),
        INDEX idx_guru_sk_tanggal (guru_id, tanggal_penetapan),
        CONSTRAINT fk_guru_sk_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE RESTRICT,
        CONSTRAINT fk_guru_sk_user FOREIGN KEY (created_by) REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS guru_dokumen (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        guru_id INT UNSIGNED NOT NULL,
        jenis VARCHAR(60) NOT NULL,
        nama_dokumen VARCHAR(180) NOT NULL,
        keterangan TEXT NULL,
        file_path VARCHAR(255) NOT NULL UNIQUE,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_guru_dokumen (guru_id, jenis),
        CONSTRAINT fk_guru_dokumen_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE RESTRICT,
        CONSTRAINT fk_guru_dokumen_user FOREIGN KEY (created_by) REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    "CREATE TABLE IF NOT EXISTS guru_riwayat_mengajar (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        guru_id INT UNSIGNED NOT NULL,
        mata_pelajaran_id INT UNSIGNED NOT NULL,
        kelas_id INT UNSIGNED NOT NULL,
        tahun_ajaran_id INT UNSIGNED NOT NULL,
        tanggal_mulai DATE NOT NULL,
        tanggal_selesai DATE NULL,
        keterangan TEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_guru_mengajar (guru_id, tahun_ajaran_id, tanggal_mulai),
        CONSTRAINT fk_guru_mengajar_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE RESTRICT,
        CONSTRAINT fk_guru_mengajar_mapel FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE RESTRICT,
        CONSTRAINT fk_guru_mengajar_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT,
        CONSTRAINT fk_guru_mengajar_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
        CONSTRAINT fk_guru_mengajar_user FOREIGN KEY (created_by) REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
];

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

$pdo->exec(
    'INSERT INTO santri_riwayat_kelas
        (santri_id, kelas_id, tahun_ajaran_id, tanggal_mulai)
     SELECT s.id, s.kelas_id, s.tahun_ajaran_id, DATE(s.created_at)
     FROM santri s
     WHERE s.kelas_id IS NOT NULL
       AND s.tahun_ajaran_id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
           FROM santri_riwayat_kelas history
           WHERE history.santri_id = s.id
             AND history.kelas_id = s.kelas_id
             AND history.tahun_ajaran_id = s.tahun_ajaran_id
       )'
);

echo "Migrasi selesai: arsip guru dan riwayat santri tersedia; data lama dipertahankan.\n";
