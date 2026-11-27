<?php
declare(strict_types=1);
allow_role(['Super Admin']);

$rows = db()->query(
    'SELECT l.created_at, l.aksi, l.keterangan, p.nama AS nama_pengguna, p.username
     FROM log_aktivitas l
     LEFT JOIN pengguna p ON p.id = l.pengguna_id
     ORDER BY l.id DESC LIMIT 200'
)->fetchAll();
?>
<section class="panel">
    <div class="panel-heading"><div><h2>Log Aktivitas</h2><p class="subtitle">200 aktivitas terbaru.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Keterangan</th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td class="empty" colspan="4">Belum ada aktivitas tercatat.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr><td><?= e(date('d-m-Y H:i', strtotime($row['created_at']))) ?></td><td><?= e($row['nama_pengguna'] ? $row['nama_pengguna'] . ' (' . $row['username'] . ')' : 'Akun dihapus') ?></td><td><?= e($row['aksi']) ?></td><td><?= e($row['keterangan']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
