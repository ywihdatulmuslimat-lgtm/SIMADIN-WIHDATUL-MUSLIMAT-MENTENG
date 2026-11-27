<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/uploads.php';
$user = require_login();
$id = filter_var($_GET['detail'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID santri tidak valid.');
}

$pdo = db();
$studentQuery = $pdo->prepare(
    'SELECT s.*, k.nama AS kelas_nama, ta.nama AS tahun_ajaran_nama
     FROM santri s
     LEFT JOIN kelas k ON k.id = s.kelas_id
     LEFT JOIN tahun_ajaran ta ON ta.id = s.tahun_ajaran_id
     WHERE s.id = :id LIMIT 1'
);
$studentQuery->execute(['id' => $id]);
$student = $studentQuery->fetch();
if (!$student) {
    http_response_code(404);
    exit('Data santri tidak ditemukan.');
}

$canWrite = in_array($user['role'], ['Super Admin', 'Operator'], true);
$documentTypes = ['Akta Kelahiran', 'Kartu Keluarga', 'Ijazah / Sertifikat', 'Dokumen Identitas', 'Lainnya'];
$errors = [];
$uploadPath = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$canWrite) {
        http_response_code(403);
        exit('Akun Anda hanya dapat melihat data santri.');
    }

    $actionInput = $_POST['action'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';
    if ($action === 'add_document') {
        $typeInput = $_POST['jenis'] ?? '';
        $nameInput = $_POST['nama_dokumen'] ?? '';
        $noteInput = $_POST['keterangan'] ?? '';
        $type = is_string($typeInput) ? trim($typeInput) : '';
        $name = is_string($nameInput) ? trim($nameInput) : '';
        $note = is_string($noteInput) ? trim($noteInput) : '';
        if (!in_array($type, $documentTypes, true)) {
            $errors[] = 'Jenis dokumen tidak valid.';
        }
        if ($name === '' || mb_strlen($name) > 180) {
            $errors[] = 'Nama dokumen wajib diisi dan maksimal 180 karakter.';
        }
        if (!$errors) {
            try {
                if (!isset($_FILES['document'])) {
                    throw new RuntimeException('Silakan pilih dokumen yang akan diunggah.');
                }
                $uploadPath = store_upload($_FILES['document'], 'document');
                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    'INSERT INTO santri_dokumen (santri_id, jenis, nama_dokumen, keterangan, file_path, created_by)
                     VALUES (:santri_id, :jenis, :nama_dokumen, :keterangan, :file_path, :created_by)'
                );
                $insert->execute([
                    'santri_id' => $id,
                    'jenis' => $type,
                    'nama_dokumen' => $name,
                    'keterangan' => $note !== '' ? $note : null,
                    'file_path' => $uploadPath,
                    'created_by' => $user['id'],
                ]);
                log_activity('Unggah dokumen santri', 'Menambahkan dokumen pada santri #' . $id . '.', 'santri', $id);
                $pdo->commit();
                flash('success', 'Dokumen santri berhasil disimpan.');
                header('Location: index.php?page=santri&detail=' . $id . '#documents');
                exit;
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($uploadPath !== null) {
                    remove_upload($uploadPath);
                    $uploadPath = null;
                }
                if ($exception->getCode() === '23000') {
                    $errors[] = 'Dokumen tidak dapat disimpan karena data terkait tidak valid.';
                } else {
                    throw $exception;
                }
            }
        }
        if ($errors && $uploadPath !== null) {
            remove_upload($uploadPath);
            $uploadPath = null;
        }
    } elseif ($action === 'delete_document') {
        $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$documentId || $documentId < 1) {
            http_response_code(400);
            exit('ID dokumen tidak valid.');
        }
        $documentQuery = $pdo->prepare('SELECT file_path FROM santri_dokumen WHERE id = :id AND santri_id = :santri_id');
        $documentQuery->execute(['id' => $documentId, 'santri_id' => $id]);
        $file = $documentQuery->fetchColumn();
        if (!is_string($file) || $file === '') {
            flash('error', 'Dokumen tidak ditemukan atau sudah dihapus.');
            header('Location: index.php?page=santri&detail=' . $id . '#documents');
            exit;
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM santri_dokumen WHERE id = :id AND santri_id = :santri_id')
            ->execute(['id' => $documentId, 'santri_id' => $id]);
        log_activity('Hapus dokumen santri', 'Menghapus dokumen dari santri #' . $id . '.', 'santri', $id);
        $pdo->commit();
        try {
            remove_upload($file);
        } catch (RuntimeException $exception) {
            flash('error', 'Dokumen dihapus dari data, tetapi file lama tidak berhasil dibersihkan.');
            header('Location: index.php?page=santri&detail=' . $id . '#documents');
            exit;
        }
        flash('success', 'Dokumen santri berhasil dihapus.');
        header('Location: index.php?page=santri&detail=' . $id . '#documents');
        exit;
    } else {
        http_response_code(400);
        exit('Aksi dokumen tidak valid.');
    }
}

$maskSensitive = !in_array($user['role'], ['Super Admin', 'Operator'], true);
$mask = static function (mixed $value) use ($maskSensitive): string {
    $value = (string)($value ?? '');
    if (!$maskSensitive || $value === '') {
        return $value !== '' ? $value : '—';
    }
    return str_repeat('•', max(0, strlen($value) - 4)) . substr($value, -4);
};
$age = !empty($student['tanggal_lahir'])
    ? (new DateTime($student['tanggal_lahir']))->diff(new DateTime('today'))->y . ' tahun'
    : 'Belum diisi';
$attendanceQuery = $pdo->prepare(
    'SELECT tanggal, status, keterangan FROM absensi WHERE santri_id = :id ORDER BY tanggal DESC, id DESC LIMIT 20'
);
$attendanceQuery->execute(['id' => $id]);
$attendance = $attendanceQuery->fetchAll();
$gradeQuery = $pdo->prepare(
    'SELECT p.nilai_akhir, p.predikat, p.catatan_guru, mp.nama AS mata_pelajaran, k.nama AS kelas, ta.nama AS tahun_ajaran
     FROM penilaian p
     JOIN mata_pelajaran mp ON mp.id = p.mata_pelajaran_id
     JOIN kelas k ON k.id = p.kelas_id
     JOIN tahun_ajaran ta ON ta.id = p.tahun_ajaran_id
     WHERE p.santri_id = :id
     ORDER BY ta.tanggal_mulai DESC, mp.nama'
);
$gradeQuery->execute(['id' => $id]);
$grades = $gradeQuery->fetchAll();
$documentQuery = $pdo->prepare('SELECT id, jenis, nama_dokumen, keterangan, created_at FROM santri_dokumen WHERE santri_id = :id ORDER BY created_at DESC, id DESC');
$documentQuery->execute(['id' => $id]);
$documents = $documentQuery->fetchAll();
?>
<section class="panel">
    <div class="panel-heading">
        <div><h2>Profil Santri</h2><p class="subtitle">Detail identitas, absensi, nilai, dan dokumen.</p></div>
        <a class="btn" href="index.php?page=santri">Kembali ke Data Santri</a>
    </div>
    <div class="profile-hero">
        <?php if (!empty($student['foto'])): ?><a href="file.php?entity=santri&id=<?= $id ?>" target="_blank" rel="noopener"><img src="file.php?entity=santri&id=<?= $id ?>" alt="Foto <?= e($student['nama_lengkap']) ?>"></a><?php else: ?><img src="assets/img/avatar-placeholder.svg" alt="Foto belum tersedia"><?php endif; ?>
        <div><h2><?= e($student['nama_lengkap']) ?></h2><p class="subtitle">NIS <?= e($student['nis']) ?> · <?= e($student['status']) ?> · <?= e($student['kelas_nama'] ?? 'Kelas belum ditentukan') ?></p><?php if ($canWrite): ?><a class="btn btn-small" href="index.php?page=santri&edit=<?= $id ?>">Edit Profil / Foto</a><?php endif; ?></div>
    </div>
    <h3>Identitas Lengkap</h3>
    <dl class="detail-grid">
        <div><dt>NIS</dt><dd><?= e($student['nis']) ?></dd></div><div><dt>NIK</dt><dd><?= e($mask($student['nik'])) ?></dd></div>
        <div><dt>Nama Lengkap</dt><dd><?= e($student['nama_lengkap']) ?></dd></div><div><dt>Jenis Kelamin</dt><dd><?= e($student['jenis_kelamin']) ?></dd></div>
        <div><dt>Tempat, Tanggal Lahir</dt><dd><?= e(trim(($student['tempat_lahir'] ?? '') . (!empty($student['tanggal_lahir']) ? ', ' . date('d-m-Y', strtotime($student['tanggal_lahir'])) : '')) ?: '—') ?></dd></div><div><dt>Umur</dt><dd><?= e($age) ?></dd></div>
        <div><dt>Alamat</dt><dd><?= nl2br(e($mask($student['alamat']))) ?></dd></div><div><dt>Kelurahan / Kecamatan</dt><dd><?= e(trim(($student['kelurahan'] ?? '') . ' / ' . ($student['kecamatan'] ?? ''), ' /') ?: '—') ?></dd></div>
        <div><dt>Kota / Provinsi</dt><dd><?= e(trim(($student['kota'] ?? '') . ' / ' . ($student['provinsi'] ?? ''), ' /') ?: '—') ?></dd></div>
        <div><dt>Ayah</dt><dd><?= e($student['nama_ayah'] ?? '—') ?> · NIK <?= e($mask($student['nik_ayah'])) ?> · <?= e($student['pekerjaan_ayah'] ?? '—') ?></dd></div>
        <div><dt>Ibu</dt><dd><?= e($student['nama_ibu'] ?? '—') ?> · NIK <?= e($mask($student['nik_ibu'])) ?> · <?= e($student['pekerjaan_ibu'] ?? '—') ?></dd></div>
        <div><dt>Wali</dt><dd><?= e($student['nama_wali'] ?? '—') ?> · <?= e($mask($student['nomor_hp_wali'])) ?></dd></div>
        <div><dt>Kelas / Tahun Ajaran</dt><dd><?= e($student['kelas_nama'] ?? '—') ?> / <?= e($student['tahun_ajaran_nama'] ?? '—') ?></dd></div>
        <div><dt>Tahun Masuk</dt><dd><?= e($student['tahun_masuk'] ?? '—') ?></dd></div>
    </dl>
</section>

<section class="panel">
    <h3>Riwayat Absensi</h3>
    <div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
        <?php if (!$attendance): ?><tr><td class="empty" colspan="3">Belum ada riwayat absensi.</td></tr><?php endif; ?>
        <?php foreach ($attendance as $item): ?><tr><td><?= e(date('d-m-Y', strtotime($item['tanggal']))) ?></td><td><?= e($item['status']) ?></td><td><?= e($item['keterangan'] ?? '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="panel">
    <h3>Riwayat Nilai</h3>
    <div class="table-wrap"><table><thead><tr><th>Tahun Ajaran</th><th>Kelas</th><th>Mata Pelajaran</th><th>Nilai Akhir</th><th>Predikat</th><th>Catatan</th></tr></thead><tbody>
        <?php if (!$grades): ?><tr><td class="empty" colspan="6">Belum ada nilai.</td></tr><?php endif; ?>
        <?php foreach ($grades as $item): ?><tr><td><?= e($item['tahun_ajaran']) ?></td><td><?= e($item['kelas']) ?></td><td><?= e($item['mata_pelajaran']) ?></td><td><?= e($item['nilai_akhir'] ?? '—') ?></td><td><?= e($item['predikat'] ?? '—') ?></td><td><?= e($item['catatan_guru'] ?? '—') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="panel" id="documents">
    <div class="section-heading"><h3>Dokumen Pendukung</h3></div>
    <div class="table-wrap"><table><thead><tr><th>Jenis</th><th>Nama Dokumen</th><th>Keterangan</th><th>File</th><th>Aksi</th></tr></thead><tbody>
        <?php if (!$documents): ?><tr><td class="empty" colspan="5">Belum ada dokumen pendukung.</td></tr><?php endif; ?>
        <?php foreach ($documents as $document): ?><tr><td><?= e($document['jenis']) ?></td><td><?= e($document['nama_dokumen']) ?></td><td><?= e($document['keterangan'] ?? '—') ?></td><td><div class="file-actions"><a class="btn btn-small" href="file.php?entity=santri_dokumen&id=<?= (int)$document['id'] ?>" target="_blank" rel="noopener">Lihat</a><a class="btn btn-small" href="file.php?entity=santri_dokumen&id=<?= (int)$document['id'] ?>&download=1">Unduh</a></div></td><td><?php if ($canWrite): ?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>"><button type="button" class="btn btn-danger btn-small confirm-delete">Hapus</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($canWrite): ?><form class="doc-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_document"><div class="form-grid">
        <div class="field"><label for="document-type">Jenis Dokumen</label><select id="document-type" name="jenis" required><?php foreach ($documentTypes as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="document-name">Nama Dokumen</label><input id="document-name" name="nama_dokumen" maxlength="180" required></div>
        <div class="field"><label for="document-file">File PDF / JPG / PNG</label><input id="document-file" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required><small>Maksimal 5 MB.</small></div>
        <div class="field"><label for="document-note">Keterangan</label><input id="document-note" name="keterangan" maxlength="1000"></div>
    </div><div class="form-actions"><button class="btn btn-primary" type="submit">Unggah Dokumen</button></div></form><?php endif; ?>
</section>
