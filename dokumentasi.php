<?php
declare(strict_types=1);

$pdo = db();
$rows = $pdo->query(
    "SELECT k.id AS event_id, NULL AS attachment_id, k.nama_kegiatan, k.tanggal, k.tempat,
            'foto' AS jenis, 'image' AS mime_type, 'Foto kegiatan' AS nama_file
     FROM kegiatan k
     WHERE k.dokumentasi IS NOT NULL AND k.dokumentasi <> ''
     UNION ALL
     SELECT k.id AS event_id, a.id AS attachment_id, k.nama_kegiatan, k.tanggal, k.tempat,
            a.jenis, a.mime_type, a.nama_file
     FROM kegiatan_lampiran a
     JOIN kegiatan k ON k.id = a.kegiatan_id
     ORDER BY tanggal DESC, event_id DESC"
)->fetchAll();
?>
<section class="panel">
    <div class="panel-heading"><div><h2>Dokumentasi Kegiatan</h2><p class="subtitle">Foto kegiatan diambil dari lampiran pada data kegiatan.</p></div><a class="btn btn-primary" href="index.php?page=kegiatan">Kelola Kegiatan</a></div>
    <?php if (!$rows): ?><div class="empty">Belum ada dokumentasi kegiatan yang diunggah.</div><?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($rows as $row):
            $isLegacy = $row['attachment_id'] === null;
            $fileUrl = $isLegacy
                ? 'file.php?entity=kegiatan&id=' . (int)$row['event_id']
                : 'file.php?entity=kegiatan_lampiran&id=' . (int)$row['attachment_id'];
        ?>
        <article class="gallery-card">
            <?php if ($row['jenis'] === 'foto' && str_starts_with((string)$row['mime_type'], 'image/')): ?>
                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($fileUrl) ?>" alt="<?= e($row['nama_kegiatan']) ?>"></a>
            <?php else: ?>
                <div class="gallery-document"><strong>Dokumen pendukung</strong><a class="btn btn-small" href="<?= e($fileUrl . '&download=1') ?>">Unduh <?= e($row['nama_file']) ?></a></div>
            <?php endif; ?>
            <div><strong><?= e($row['nama_kegiatan']) ?></strong><p class="subtitle"><?= e(date('d-m-Y', strtotime($row['tanggal']))) ?><?= $row['tempat'] ? ' · ' . e($row['tempat']) : '' ?></p></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
