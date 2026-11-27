<?php
declare(strict_types=1);

$activityId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$activityId || $activityId < 1) {
    http_response_code(400);
    exit('ID aktivitas tidak valid.');
}

$query = db()->prepare(
    'SELECT l.id, l.aksi, l.keterangan, l.created_at, l.target_type, l.target_id, p.nama
     FROM log_aktivitas l
     LEFT JOIN pengguna p ON p.id = l.pengguna_id
     WHERE l.id = :id LIMIT 1'
);
$query->execute(['id' => $activityId]);
$activity = $query->fetch();
if (!$activity) {
    http_response_code(404);
    exit('Aktivitas tidak ditemukan.');
}

$targetType = $activity['target_type'];
if ($user['role'] === 'Guru' && !in_array($targetType, ['kegiatan', 'pengumuman'], true)) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk aktivitas ini.');
}
if ($user['role'] !== 'Super Admin' && ($targetType === 'pengguna' || $targetType === null)) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk aktivitas ini.');
}

$targetHref = activity_target_href($activity);
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Detail Aktivitas</h2>
            <p class="subtitle"><?= e(date('d-m-Y H:i:s', strtotime($activity['created_at']))) ?></p>
        </div>
        <a class="btn" href="index.php">Kembali ke Dashboard</a>
    </div>
    <dl class="detail-grid">
        <div><dt>Aktivitas</dt><dd><?= e($activity['aksi']) ?></dd></div>
        <div><dt>Pengguna</dt><dd><?= e($activity['nama'] ?? 'Akun dihapus') ?></dd></div>
        <div class="field-wide"><dt>Keterangan</dt><dd><?= nl2br(e($activity['keterangan'] ?? '—')) ?></dd></div>
        <div><dt>Referensi Data</dt><dd>
            <?php if ($targetHref): ?>
                <a href="<?= e($targetHref) ?>">Buka data terkait</a>
            <?php elseif ($targetType !== null): ?>
                Data terkait telah dihapus atau tidak lagi tersedia.
            <?php else: ?>
                Log lama ini tidak memiliki referensi data.
            <?php endif; ?>
        </dd></div>
    </dl>
</section>
