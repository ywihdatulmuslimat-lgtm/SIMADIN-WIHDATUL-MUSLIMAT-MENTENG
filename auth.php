<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Muat ulang halaman dan coba kembali.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    if (!empty($user['must_change_password']) && basename($_SERVER['PHP_SELF'] ?? '') !== 'change-password.php') {
        header('Location: change-password.php');
        exit;
    }
    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki hak akses untuk halaman ini.');
    }
    return $user;
}

function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function log_activity(string $action, string $entity = '', string $details = ''): void
{
    $stmt = db()->prepare('INSERT INTO activity_log (user_id,action,entity,details,ip_address) VALUES (:user,:action,:entity,:details,:ip)');
    $stmt->execute([
        'user' => current_user()['id'] ?? null,
        'action' => $action,
        'entity' => $entity,
        'details' => $details,
        'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);
}

function age_from_date(?string $date): ?int
{
    if (!$date) return null;
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$birth || $birth->format('Y-m-d') !== $date) return null;
    $today = new DateTimeImmutable('today');
    if ($birth > $today) return null;
    return $birth->diff($today)->y;
}
