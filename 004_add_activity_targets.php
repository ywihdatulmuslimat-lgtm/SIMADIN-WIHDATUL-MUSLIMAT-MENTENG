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

foreach ([
    'target_type' => 'VARCHAR(40) NULL AFTER keterangan',
    'target_id' => 'INT UNSIGNED NULL AFTER target_type',
] as $column => $definition) {
    $hasColumn->execute(['table' => 'log_aktivitas', 'column' => $column]);
    if ((int)$hasColumn->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE log_aktivitas ADD COLUMN `' . $column . '` ' . $definition);
    }
}

$hasIndex = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name'
);
$hasIndex->execute(['table' => 'log_aktivitas', 'index_name' => 'idx_log_aktivitas_target']);
if ((int)$hasIndex->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE log_aktivitas ADD INDEX idx_log_aktivitas_target (target_type, target_id)');
}

$legacyLogs = $pdo->query(
    'SELECT id, keterangan FROM log_aktivitas
     WHERE target_type IS NULL AND target_id IS NULL AND keterangan IS NOT NULL'
)->fetchAll();
$legacyTargets = [
    'santri' => '/(?:data\s+)?santri\s*#([1-9][0-9]*)/i',
    'guru' => '/(?:data\s+)?guru\s*#([1-9][0-9]*)/i',
    'kegiatan' => '/kegiatan\s*#([1-9][0-9]*)/i',
    'legalitas' => '/(?:dokumen\s+)?legalitas\s*#([1-9][0-9]*)/i',
    'pengguna' => '/pengguna\s*#([1-9][0-9]*)/i',
    'tahun_ajaran' => '/tahun\s+ajaran\s*#([1-9][0-9]*)/i',
    'mata_pelajaran' => '/mata\s+pelajaran\s*#([1-9][0-9]*)/i',
    'pengumuman' => '/pengumuman\s*#([1-9][0-9]*)/i',
    'inventaris' => '/inventaris\s*#([1-9][0-9]*)/i',
];
$backfill = $pdo->prepare(
    'UPDATE log_aktivitas
     SET target_type = :target_type, target_id = :target_id
     WHERE id = :id AND target_type IS NULL AND target_id IS NULL'
);
foreach ($legacyLogs as $legacyLog) {
    foreach ($legacyTargets as $targetType => $pattern) {
        if (preg_match($pattern, (string)$legacyLog['keterangan'], $matches) === 1) {
            $backfill->execute([
                'target_type' => $targetType,
                'target_id' => (int)$matches[1],
                'id' => (int)$legacyLog['id'],
            ]);
            break;
        }
    }
}

echo "Migrasi selesai: referensi data aktivitas tersedia; log lama dipertahankan dan referensi yang dikenali dilengkapi.\n";
