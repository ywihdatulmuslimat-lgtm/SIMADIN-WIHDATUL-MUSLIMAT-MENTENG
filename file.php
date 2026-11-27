<?php
declare(strict_types=1);
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/uploads.php';

$user = require_login();
if ($user['role'] === 'Guru') {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk melihat lampiran.');
}

$entityInput = $_GET['entity'] ?? '';
$entity = is_string($entityInput) ? $entityInput : '';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Permintaan file tidak valid.');
}
$legalTypes = [
    'akta_yayasan' => 'Akta Yayasan',
    'sk_kemenkumham' => 'SK Kemenkumham',
    'npwp' => 'NPWP',
    'izin_operasional' => 'Izin Operasional',
    'nomor_statistik' => 'Nomor Statistik',
    'akreditasi' => 'Akreditasi',
];
$sources = [
    'santri' => ['santri', 'foto', null],
    'guru' => ['guru', 'foto', null],
    'santri_dokumen' => ['santri_dokumen', 'file_path', null],
    'guru_sk' => ['guru_sk', 'file_path', null],
    'guru_dokumen' => ['guru_dokumen', 'file_path', null],
    'kegiatan' => ['kegiatan', 'dokumentasi', null],
    'kegiatan_lampiran' => ['kegiatan_lampiran', 'file_path', null],
    'legalitas' => ['dokumen_legalitas', 'file_path', null],
    'inventaris' => ['inventaris', 'foto', null],
    'galeri' => ['madrasah_gallery', 'photo', null],
    'tanah' => ['facilities', 'photo', 'Tanah'],
    'bangunan' => ['facilities', 'photo', 'Bangunan'],
    'ruang_belajar' => ['facilities', 'photo', 'Ruang Belajar'],
    'ruang_guru' => ['facilities', 'photo', 'Ruang Guru'],
    'perpustakaan' => ['facilities', 'photo', 'Perpustakaan'],
    'kondisi' => ['facilities', 'photo', null],
    'arsip' => ['dokumen_legalitas', 'file_path', null],
];
foreach ($legalTypes as $key => $type) {
    $sources[$key] = ['dokumen_legalitas', 'file_path', $type];
}
if (!isset($sources[$entity])) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}
[$table, $column, $scope] = $sources[$entity];
$scopeColumn = in_array($entity, ['tanah', 'bangunan', 'ruang_belajar', 'ruang_guru', 'perpustakaan'], true) ? 'category' : 'jenis';
$statement = db()->prepare("SELECT `$column` FROM `$table` WHERE id = :id" . ($scope === null ? '' : " AND `$scopeColumn` = :scope") . ' LIMIT 1');
$parameters = ['id' => $id];
if ($scope !== null) {
    $parameters['scope'] = $scope;
}
$statement->execute($parameters);
$filename = $statement->fetchColumn();
if (!is_string($filename) || $filename === '' || basename($filename) !== $filename) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$directory = realpath(upload_directory());
$path = realpath(upload_directory() . DIRECTORY_SEPARATOR . $filename);
if ($directory === false || $path === false || !str_starts_with(strtolower($path), strtolower($directory . DIRECTORY_SEPARATOR)) || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(415);
    exit('Tipe file tidak dapat ditampilkan.');
}

$downloadName = $entity . '-' . $id . '.' . strtolower(pathinfo($filename, PATHINFO_EXTENSION));
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: sandbox; default-src \'none\'');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
