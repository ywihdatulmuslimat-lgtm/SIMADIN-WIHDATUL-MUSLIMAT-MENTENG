<?php
declare(strict_types=1);

function upload_directory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
}

function store_upload(array $file, string $kind): string
{
    $allowed = [
        'image' => [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ],
        'portrait' => [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ],
        'document' => [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ],
    ];
    if (!isset($allowed[$kind])) {
        throw new RuntimeException('Jenis unggahan tidak didukung.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian. Silakan coba kembali.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara unggahan tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'File gagal disimpan oleh server.',
            UPLOAD_ERR_EXTENSION => 'Unggahan dihentikan oleh konfigurasi server.',
        ];
        throw new RuntimeException($uploadErrors[$file['error']] ?? 'Unggahan file gagal.');
    }
    if (!isset($file['tmp_name'], $file['name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('File unggahan tidak valid.');
    }
    if ((int)$file['size'] < 1 || (int)$file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file harus antara 1 byte dan 5 MB.');
    }
    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$kind][$extension])) {
        throw new RuntimeException('Ekstensi file tidak diizinkan.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== $allowed[$kind][$extension]) {
        throw new RuntimeException('Tipe file tidak sesuai dengan ekstensi yang dipilih.');
    }

    $directory = upload_directory();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan unggahan tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(24)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('File gagal disimpan. Periksa izin folder penyimpanan.');
    }

    return $filename;
}

function remove_upload(string $filename): void
{
    if ($filename === '' || basename($filename) !== $filename) {
        return;
    }
    $path = upload_directory() . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('File lama tidak dapat dihapus dari penyimpanan.');
    }
}
