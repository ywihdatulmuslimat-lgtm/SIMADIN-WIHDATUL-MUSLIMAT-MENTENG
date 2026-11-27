<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('YAYASAN_DB_HOST') ?: '127.0.0.1';
    $port = getenv('YAYASAN_DB_PORT') ?: '3306';
    $name = getenv('YAYASAN_DB_NAME') ?: 'db_yayasan_wm';
    $user = getenv('YAYASAN_DB_USER') ?: 'root';
    $pass = getenv('YAYASAN_DB_PASS') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
