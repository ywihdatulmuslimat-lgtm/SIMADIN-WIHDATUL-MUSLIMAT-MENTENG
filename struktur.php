<?php
declare(strict_types=1);

$pdo = db();
$canWrite = in_array($user['role'] ?? '', ['Super Admin', 'Operator'], true);
try {
    $stmt = $pdo->query("SELECT id, role, person_name, teacher_id, sort_order, note FROM organization ORDER BY sort_order ASC, id ASC");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    http_response_code(500);
    echo '<section class="panel"><h2>Struktur Organisasi belum dapat dimuat</h2><p class="subtitle">Tabel organization belum tersedia pada database yang sedang digunakan. Pastikan skema database SIMADIN sudah diperbarui menggunakan database/database.sql. Data lama tidak perlu dihapus.</p></section>';
    return;
}
?>
<section class="panel">
  <div class="panel-heading">
    <div>
      <h2>Struktur Organisasi Madrasah</h2>
      <p class="subtitle">Daftar pengurus dan jabatan berdasarkan urutan yang tersimpan di database SIMADIN.</p>
    </div>
    <?php if ($canWrite): ?><a class="btn btn-primary" href="index.php?page=struktur&amp;manage=1">Kelola Struktur</a><?php endif; ?>
  </div>
  <?php if (!$members): ?>
    <div class="empty-state">
      <h3>Data struktur organisasi belum diisi</h3>
      <p class="subtitle">Tambahkan nama pengurus, jabatan, dan urutan tampil agar struktur organisasi muncul di halaman ini.</p>
      <?php if ($canWrite): ?><a class="btn btn-primary" href="index.php?page=struktur&amp;manage=1">Tambah Data Pengurus</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>No.</th><th>Jabatan</th><th>Nama Pengurus</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php foreach ($members as $index => $member): ?>
          <tr>
            <td><?= (int)($index + 1) ?></td>
            <td><strong><?= e($member['role']) ?></strong></td>
            <td><?= e($member['person_name']) ?></td>
            <td><?= e($member['note'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="subtitle" style="margin-top:12px">Total pengurus: <?= count($members) ?> orang.</p>
  <?php endif; ?>
</section>
