<?php
declare(strict_types=1);
require __DIR__.'/config/database.php';
require __DIR__.'/includes/auth.php';
$user = current_user();
if (!$user) { header('Location: login.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $password=(string)($_POST['password']??'');
    $confirm=(string)($_POST['confirm_password']??'');
    if (strlen($password)<12) $error='Password minimal 12 karakter.';
    elseif (!hash_equals($password,$confirm)) $error='Konfirmasi password tidak sama.';
    else {
        $stmt=db()->prepare('UPDATE app_users SET password_hash=:hash,must_change_password=0 WHERE id=:id');
        $stmt->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$user['id']]);
        $_SESSION['user']['must_change_password']=0;
        log_activity('change_password','users','Password diperbarui');
        header('Location: index.php'); exit;
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ubah Password</title><link rel="stylesheet" href="assets/css/app.css"></head><body><main class="login-page"><section class="login-card"><img src="assets/img/logo-wihdatul-muslimat.jpeg" alt="Logo"><h1>Ganti Password</h1><p>Password awal harus diganti sebelum menggunakan aplikasi.</p><?php if($error):?><div class="alert alert-error"><?=h($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="field"><label>Password baru (minimal 12 karakter)</label><input class="form-control" type="password" name="password" required minlength="12" autocomplete="new-password"></div><div class="field"><label>Ulangi password</label><input class="form-control" type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></div><button class="btn btn-primary">Simpan Password Baru</button></form></section></main></body></html>
