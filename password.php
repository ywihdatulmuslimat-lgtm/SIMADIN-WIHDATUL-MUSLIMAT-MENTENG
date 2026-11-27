<?php
declare(strict_types=1);

$user = require_login();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $currentInput = $_POST['current_password'] ?? '';
    $newInput = $_POST['new_password'] ?? '';
    $confirmInput = $_POST['confirm_password'] ?? '';
    $current = is_string($currentInput) ? $currentInput : '';
    $new = is_string($newInput) ? $newInput : '';
    $confirm = is_string($confirmInput) ? $confirmInput : '';

    $statement = db()->prepare('SELECT password_hash FROM pengguna WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $user['id']]);
    $currentHash = $statement->fetchColumn();
    if (!is_string($currentHash) || !password_verify($current, $currentHash)) {
        $error = 'Password saat ini tidak sesuai.';
    } elseif (strlen($new) < 12) {
        $error = 'Password baru minimal 12 karakter.';
    } elseif ($new !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif ($new === $current) {
        $error = 'Gunakan password yang berbeda dari password saat ini.';
    } else {
        $update = db()->prepare('UPDATE pengguna SET password_hash = :password WHERE id = :id');
        $update->execute([
            'password' => password_hash($new, PASSWORD_DEFAULT),
            'id' => $user['id'],
        ]);
        log_activity('Ubah password', 'Pengguna mengubah password akunnya.');
        session_regenerate_id(true);
        flash('success', 'Password berhasil diperbarui.');
        header('Location: index.php?page=password');
        exit;
    }
}
?>
<section class="panel form-panel">
    <h2>Ubah Password</h2>
    <p class="subtitle">Gunakan password minimal 12 karakter. Jangan gunakan password yang sama dengan akun lain.</p>
    <?php if ($error !== ''): ?><div class="alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-grid">
            <div class="field"><label for="current-password">Password Saat Ini</label><input id="current-password" type="password" name="current_password" autocomplete="current-password" required></div>
            <div class="field"><label for="new-password">Password Baru</label><input id="new-password" type="password" name="new_password" autocomplete="new-password" minlength="12" required></div>
            <div class="field"><label for="confirm-password">Ulangi Password Baru</label><input id="confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="12" required></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Simpan Password</button></div>
    </form>
</section>
