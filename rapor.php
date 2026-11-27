<?php
declare(strict_types=1);

$pdo = db();
$santriId = filter_var($_GET['santri_id'] ?? null, FILTER_VALIDATE_INT);
$yearId = filter_var($_GET['tahun_ajaran_id'] ?? null, FILTER_VALIDATE_INT);
$students = $pdo->query("SELECT id, nis, nama_lengkap FROM santri ORDER BY nama_lengkap")->fetchAll();
$years = $pdo->query('SELECT id, nama, tanggal_mulai, tanggal_selesai FROM tahun_ajaran ORDER BY tanggal_mulai DESC')->fetchAll();
$student = null;
$year = null;
$grades = [];
$attendance = [];

if ($santriId && $santriId > 0) {
    $query = $pdo->prepare('SELECT s.id, s.nis, s.nama_lengkap, s.kelas_id, k.nama AS nama_kelas FROM santri s LEFT JOIN kelas k ON k.id = s.kelas_id WHERE s.id = :id');
    $query->execute(['id' => $santriId]);
    $student = $query->fetch();
}
if ($yearId && $yearId > 0) {
    $query = $pdo->prepare('SELECT id, nama, tanggal_mulai, tanggal_selesai FROM tahun_ajaran WHERE id = :id');
    $query->execute(['id' => $yearId]);
    $year = $query->fetch();
}
if ($student && $year) {
    $query = $pdo->prepare(
        'SELECT mp.nama AS mata_pelajaran, p.tugas, p.ulangan_harian, p.praktik, p.uts, p.uas, p.nilai_akhir, p.predikat, p.deskripsi, p.catatan_guru
         FROM penilaian p JOIN mata_pelajaran mp ON mp.id = p.mata_pelajaran_id
         WHERE p.santri_id = :student_id AND p.tahun_ajaran_id = :year_id
         ORDER BY mp.nama'
    );
    $query->execute(['student_id' => $student['id'], 'year_id' => $year['id']]);
    $grades = $query->fetchAll();
    $query = $pdo->prepare(
        'SELECT status, COUNT(*) AS total FROM absensi
         WHERE santri_id = :student_id AND tanggal BETWEEN :start_date AND :end_date
         GROUP BY status'
    );
    $query->execute([
        'student_id' => $student['id'],
        'start_date' => $year['tanggal_mulai'],
        'end_date' => $year['tanggal_selesai'],
    ]);
    foreach ($query->fetchAll() as $row) {
        $attendance[$row['status']] = (int)$row['total'];
    }
}
?>
<section class="panel">
    <div class="panel-heading"><div><h2>Rapor Santri</h2><p class="subtitle">Pilih santri dan tahun ajaran untuk melihat data penilaian serta rekap kehadiran.</p></div><button class="btn" type="button" onclick="window.print()">Cetak</button></div>
    <form class="toolbar" method="get" action="index.php">
        <input type="hidden" name="page" value="rapor">
        <label for="report-student">Santri</label><select class="search" id="report-student" name="santri_id" required><option value="">Pilih santri</option><?php foreach ($students as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $santriId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['nis'] . ' · ' . $item['nama_lengkap']) ?></option><?php endforeach; ?></select>
        <label for="report-year">Tahun Ajaran</label><select class="search" id="report-year" name="tahun_ajaran_id" required><option value="">Pilih tahun ajaran</option><?php foreach ($years as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $yearId === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['nama']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary" type="submit">Tampilkan Rapor</button>
    </form>
</section>
<?php if ($student && $year): ?>
<section class="panel">
    <h2><?= e($student['nama_lengkap']) ?></h2>
    <p>NIS: <?= e($student['nis']) ?> · Kelas: <?= e($student['nama_kelas'] ?? 'Belum ditentukan') ?> · Tahun Ajaran: <?= e($year['nama']) ?></p>
    <div class="table-wrap"><table><thead><tr><th>Mata Pelajaran</th><th>Tugas</th><th>Ulangan</th><th>Praktik</th><th>UTS</th><th>UAS</th><th>Nilai Akhir</th><th>Predikat</th></tr></thead><tbody>
        <?php if (!$grades): ?><tr><td class="empty" colspan="8">Belum ada data penilaian untuk tahun ajaran ini.</td></tr><?php endif; ?>
        <?php foreach ($grades as $grade): ?><tr><td><?= e($grade['mata_pelajaran']) ?></td><td><?= e($grade['tugas']) ?></td><td><?= e($grade['ulangan_harian']) ?></td><td><?= e($grade['praktik']) ?></td><td><?= e($grade['uts']) ?></td><td><?= e($grade['uas']) ?></td><td><?= e($grade['nilai_akhir']) ?></td><td><?= e($grade['predikat'] ?? '—') ?></td></tr><?php if ($grade['deskripsi'] || $grade['catatan_guru']): ?><tr><td colspan="8"><small><?= e($grade['deskripsi'] ?? '') ?> <?= e($grade['catatan_guru'] ?? '') ?></small></td></tr><?php endif; ?><?php endforeach; ?>
    </tbody></table></div>
    <h3>Rekap Kehadiran</h3>
    <div class="stats attendance-stats"><?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpa'] as $status): ?><article class="stat"><span><?= e($status) ?></span><strong><?= $attendance[$status] ?? 0 ?></strong></article><?php endforeach; ?></div>
</section>
<?php elseif ($santriId || $yearId): ?>
<div class="alert-error" role="alert">Santri atau tahun ajaran tidak ditemukan.</div>
<?php endif; ?>
