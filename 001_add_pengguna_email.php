<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Jalankan migrasi melalui PHP CLI.');
}

require __DIR__ . '/../../config/database.php';
$pdo = db();
$column = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
);
$column->execute(['table' => 'pengguna', 'column' => 'email']);
if ((int)$column->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE pengguna ADD COLUMN email VARCHAR(190) NULL AFTER username');
}

$index = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name'
);
$index->execute(['table' => 'pengguna', 'index_name' => 'uq_pengguna_email']);
if ((int)$index->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE pengguna ADD UNIQUE KEY uq_pengguna_email (email)');
}

echo "Migration selesai: kolom email opsional tersedia; data pengguna lama tetap utuh.\n";
