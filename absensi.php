<?php
declare(strict_types=1);

$user = require_login();
$canWrite = in_array($user['role'], ['Super Admin', 'Operator'], true);
$dateInput = $_POST['tanggal'] ?? $_GET['tanggal'] ?? date('Y-m-d');
$date = is_string($dateInput) ? trim($dateInput) : '';
$validDate = DateTime::createFromFormat('!Y-m-d', $date);
$dateErrors = DateTime::getLastErrors();
if (!$validDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $validDate->format('Y-m-d') !== $date) {
    http_response_code(400);
    exit('Tanggal absensi tidak valid.');
}

$errors = [];
$pdo = db();
if (isset($_GET['view'])) {
    $viewId = filter_var($_GET['view'], FILTER_VALIDATE_INT);
    if (!$viewId || $viewId < 1) {
        http_response_code(400);
        exit('ID absensi tidak valid.');
    }
    $viewQuery = $pdo->prepare('SELECT tanggal FROM absensi WHERE id = :id LIMIT 1');
    $viewQuery->execute(['id' => $viewId]);
    $viewDate = $viewQuery->fetchColumn();
    if (!is_string($viewDate)) {
        http_response_code(404);
        exit('Catatan absensi tidak ditemukan.');
    }
    $dateRowsQuery = $pdo->prepare(
        'SELECT a.status, a.keterangan, s.id AS santri_id, s.nis, s.nama_lengkap, k.nama AS nama_kelas
         FROM absensi a
         JOIN santri s ON s.id = a.santri_id
         LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE a.tanggal = :date
         ORDER BY k.nama, s.nama_lengkap'
    );
    $dateRowsQuery->execute(['date' => $viewDate]);
    $dateRows = $dateRowsQuery->fetchAll();
    ?>
    <section class="panel">
        <div class="panel-heading"><div><h2>Detail Absensi Harian</h2><p class="subtitle"><?= e(date('d-m-Y', strtotime($viewDate))) ?> · <?= count($dateRows) ?> santri tercatat</p></div><a class="btn" href="index.php?page=absensi&amp;tanggal=<?= e($viewDate) ?>">Kembali ke Absensi</a></div>
        <div class="table-wrap"><table><thead><tr><th>NIS</th><th>Nama Santri</th><th>Kelas</th><th>Status</th><th>Keterangan</th><th>Profil</th></tr></thead><tbody>
            <?php foreach ($dateRows as $row): ?><tr><td><?= e($row['nis']) ?></td><td><?= e($row['nama_lengkap']) ?></td><td><?= e($row['nama_kelas'] ?? '—') ?></td><td><?= e($row['status']) ?></td><td><?= e($row['keterangan'] ?? '—') ?></td><td><a href="index.php?page=santri&amp;detail=<?= (int)$row['santri_id'] ?>">Detail Santri</a></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$canWrite) {
        http_response_code(403);
        exit('Akun Anda hanya dapat melihat absensi.');
    }
    $students = $pdo->query("SELECT id FROM santri WHERE status = 'Aktif'")->fetchAll(PDO::FETCH_COLUMN);
    $studentIds = array_map('intval', $students);
    $entries = $_POST['attendance'] ?? [];
    if (!is_array($entries)) {
        $errors[] = 'Format data absensi tidak valid.';
    } else {
        foreach ($entries as $studentId => $entry) {
            if (!ctype_digit((string)$studentId) || !in_array((int)$studentId, $studentIds, true) || !is_array($entry)) {
                $errors[] = 'Daftar santri pada formulir sudah berubah. Muat ulang halaman.';
                break;
            }
            $statusInput = $entry['status'] ?? '';
            $status = is_string($statusInput) ? $statusInput : '';
            if ($status !== '' && !in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpa'], true)) {
                $errors[] = 'Status kehadiran tidak valid.';
                break;
            }
            $noteInput = $entry['keterangan'] ?? '';
            if (!is_string($noteInput) || strlen(trim($noteInput)) > 10000) {
                $errors[] = 'Keterangan terlalu panjang.';
                break;
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $save = $pdo->prepare(
                'INSERT INTO absensi (santri_id, tanggal, status, keterangan, created_by)
                 VALUES (:student_id, :date, :status, :note, :user_id)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), created_by = VALUES(created_by)'
            );
            $findAttendance = $pdo->prepare(
                'SELECT id FROM absensi WHERE santri_id = :student_id AND tanggal = :date LIMIT 1'
            );
            $saved = 0;
            $targetId = null;
            foreach ($entries as $studentId => $entry) {
                $statusInput = $entry['status'] ?? '';
                $status = is_string($statusInput) ? $statusInput : '';
                if ($status === '') {
                    continue;
                }
                $save->execute([
                    'student_id' => (int)$studentId,
                    'date' => $date,
                    'status' => $status,
                    'note' => trim((string)($entry['keterangan'] ?? '')) ?: null,
                    'user_id' => $user['id'],
                ]);
                $saved++;
                $findAttendance->execute(['student_id' => (int)$studentId, 'date' => $date]);
                $attendanceId = $findAttendance->fetchColumn();
                if ($targetId === null && $attendanceId !== false) {
                    $targetId = (int)$attendanceId;
                }
            }
            if ($saved > 0 && $targetId !== null) {
                log_activity(
                    'Input absensi',
                    'Menyimpan absensi tanggal ' . $date . ' untuk ' . $saved . ' santri.',
                    'absensi',
                    $targetId
                );
            }
            $pdo->commit();
            flash('success', 'Absensi tanggal ' . date('d-m-Y', strtotime($date)) . ' berhasil disimpan.');
            header('Location: index.php?page=absensi&tanggal=' . urlencode($date));
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

$query = $pdo->prepare(
    'SELECT s.id, s.nis, s.nama_lengkap, k.nama AS nama_kelas, a.status, a.keterangan
     FROM santri s
     LEFT JOIN kelas k ON k.id = s.kelas_id
     LEFT JOIN absensi a ON a.santri_id = s.id AND a.tanggal = :date
     WHERE s.status = \'Aktif\'
     ORDER BY k.nama, s.nama_lengkap'
);
$query->execute(['date' => $date]);
$students = $query->fetchAll();
$counts = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0];
foreach ($students as $student) {
    if (isset($counts[$student['status']])) {
        $counts[$student['status']]++;
    }
}
?>
<?php if ($errors): ?><div class="alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="stats attendance-stats"><?php foreach ($counts as $label => $count): ?><article class="stat"><span><?= e($label) ?></span><strong><?= $count ?></strong></article><?php endforeach; ?></section>
<section class="panel">
    <div class="panel-heading"><div><h2>Absensi Harian</h2><p class="subtitle">Status kosong berarti belum dicatat. Data diambil dari daftar santri aktif.</p></div>
        <form method="get" action="index.php"><input type="hidden" name="page" value="absensi"><label for="attendance-date">Tanggal</label> <input id="attendance-date" type="date" name="tanggal" value="<?= e($date) ?>"><button class="btn" type="submit">Tampilkan</button></form>
    </div>
    <?php if (!$students): ?><div class="empty">Belum ada santri aktif untuk dicatat.</div><?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="tanggal" value="<?= e($date) ?>">
        <div class="table-wrap"><table><thead><tr><th>NIS</th><th>Nama Santri</th><th>Kelas</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
        <?php foreach ($students as $student): ?><tr>
            <td><?= e($student['nis']) ?></td><td><?= e($student['nama_lengkap']) ?></td><td><?= e($student['nama_kelas'] ?? '—') ?></td>
            <td><select name="attendance[<?= (int)$student['id'] ?>][status]" <?= $canWrite ? '' : 'disabled' ?>><option value="">Belum dicatat</option><?php foreach (array_keys($counts) as $status): ?><option value="<?= e($status) ?>" <?= $student['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></td>
            <td><input name="attendance[<?= (int)$student['id'] ?>][keterangan]" value="<?= e($student['keterangan'] ?? '') ?>" maxlength="10000" <?= $canWrite ? '' : 'disabled' ?>></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if ($canWrite): ?><div class="form-actions"><button class="btn btn-primary" type="submit">Simpan Absensi</button></div><?php else: ?><p class="notice">Akun Kepala Madrasah memiliki akses baca saja.</p><?php endif; ?>
    </form>
    <?php endif; ?>
</section>
