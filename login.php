<?php
declare(strict_types=1);
require __DIR__.'/config/database.php';
require __DIR__.'/includes/auth.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id,name,username,email,password_hash,role,must_change_password FROM app_users WHERE active=1 AND (username=:username OR email=:email) LIMIT 1');
    $stmt->execute(['username'=>$identity,'email'=>$identity]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'=>(int)$user['id'],'name'=>$user['name'],'username'=>$user['username'],
            'role'=>$user['role'],'must_change_password'=>(int)$user['must_change_password'],
        ];
        log_activity('login','auth','Login berhasil');
        header('Location: '.($user['must_change_password']?'change-password.php':'index.php'));
        exit;
    }
    $error = 'Username/email atau password tidak sesuai.';
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk | Yayasan Wihdatul Muslimat</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body><main class="login-page"><section class="login-card"><img src="assets/img/logo-wihdatul-muslimat.jpeg" alt="Logo Yayasan Wihdatul Muslimat"><h1>Administrasi Yayasan</h1><p>Yayasan Wihdatul Muslimat · Jakarta Pusat</p><?php if($error): ?><div class="alert alert-error"><?=h($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="field"><label for="identity">Username atau email</label><input class="form-control" id="identity" name="identity" autocomplete="username" required></div><div class="field"><label for="password">Password</label><div class="password-wrap"><input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required><button class="password-toggle" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'">Tampilkan</button></div></div><button class="btn btn-primary" type="submit">Masuk</button></form></section></main></body></html>
