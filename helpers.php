<?php
declare(strict_types=1);

function store_upload(array $file, bool $photoOnly = false): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        throw new RuntimeException('Upload gagal. Pilih file yang valid.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file maksimal 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string)$file['tmp_name']);
    $extensions = [
        'image/jpeg'=>'jpg',
        'image/png'=>'png',
        'application/pdf'=>'pdf',
    ];
    if (!isset($extensions[$mime]) || ($photoOnly && $mime==='application/pdf')) {
        throw new RuntimeException('Jenis file harus JPG, PNG, atau PDF; foto hanya menerima JPG/PNG.');
    }

    $directory=__DIR__.'/../storage/uploads/'.date('Y/m');
    if(!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat.');
    }
    $filename=bin2hex(random_bytes(20)).'.'.$extensions[$mime];
    if(!move_uploaded_file((string)$file['tmp_name'],$directory.'/'.$filename)) {
        throw new RuntimeException('File tidak dapat disimpan.');
    }
    return 'storage/uploads/'.date('Y/m').'/'.$filename;
}

function flash_message(): ?array
{
    $flash=$_SESSION['flash']??null;
    unset($_SESSION['flash']);
    return $flash;
}
