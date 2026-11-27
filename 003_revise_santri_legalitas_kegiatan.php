<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Jalankan migrasi melalui PHP CLI.');
}

require __DIR__ . '/../../config/database.php';
$pdo = db();

$hasColumn = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
);
$ensureColumn = static function (string $name, string $definition) use ($pdo, $hasColumn): void {
    $hasColumn->execute(['table' => 'dokumen_legalitas', 'column' => $name]);
    if ((int)$hasColumn->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE dokumen_legalitas ADD COLUMN `' . $name . '` ' . $definition);
    }
};
$ensureColumn('instansi_penerbit', 'VARCHAR(180) NULL AFTER nomor_dokumen');
$ensureColumn('tanggal_kedaluwarsa', 'DATE NULL AFTER masa_berlaku');

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS kegiatan_lampiran (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kegiatan_id INT UNSIGNED NOT NULL,
        jenis ENUM('foto','dokumen') NOT NULL,
        file_path VARCHAR(255) NOT NULL UNIQUE,
        nama_file VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        uploaded_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_kegiatan_lampiran (kegiatan_id, jenis),
        CONSTRAINT fk_kegiatan_lampiran_event
            FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
        CONSTRAINT fk_kegiatan_lampiran_user
            FOREIGN KEY (uploaded_by) REFERENCES pengguna(id) ON DELETE SET NULL
    ) ENGINE=InnoDB"
);

$existingClass = $pdo->prepare(
    "SELECT COUNT(*) FROM kelas
     WHERE LOWER(REPLACE(REPLACE(nama, ' ', ''), 'kelas', '')) = :grade_name
        OR LOWER(REPLACE(REPLACE(tingkat, ' ', ''), 'kelas', '')) = :grade_level"
);
$addClass = $pdo->prepare(
    'INSERT INTO kelas (nama, tingkat) VALUES (:name, :grade)'
);
for ($grade = 1; $grade <= 6; $grade++) {
    $existingClass->execute([
        'grade_name' => (string)$grade,
        'grade_level' => (string)$grade,
    ]);
    if ((int)$existingClass->fetchColumn() === 0) {
        $addClass->execute(['name' => 'Kelas ' . $grade, 'grade' => (string)$grade]);
    }
}

echo "Migrasi selesai: kolom legalitas, lampiran kegiatan, dan referensi kelas 1-6 siap; data lama dipertahankan.\n";
