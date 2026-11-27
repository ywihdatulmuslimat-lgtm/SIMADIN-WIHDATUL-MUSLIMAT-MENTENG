<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/uploads.php';
$user = require_login();
$id = filter_var($_GET['detail'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID guru tidak valid.');
}

$pdo = db();
$teacherQuery = $pdo->prepare(
    'SELECT g.*, mp.nama AS mata_pelajaran_nama
     FROM guru g LEFT JOIN mata_pelajaran mp ON mp.id = g.mata_pelajaran_id
     WHERE g.id = :id LIMIT 1'
);
$teacherQuery->execute(['id' => $id]);
$teacher = $teacherQuery->fetch();
if (!$teacher) {
    http_response_code(404);
    exit('Data guru tidak ditemukan.');
}

$canWrite = in_array($user['role'], ['Super Admin', 'Operator'], true);
$skTypes = [
    'SK Pengangkatan Guru',
    'SK Pengangkatan Kepala Madrasah',
    'SK Pembagian Tugas Mengajar',
    'SK Wali Kelas',
    'SK Perpanjangan Masa Tugas',
    'SK Pemberhentian',
    'SK Lainnya',
];
$documentTypes = ['Ijazah', 'Sertifikat', 'Dokumen Identitas', 'Dokumen Pendukung Lainnya'];
$tabInput = $_GET['tab'] ?? 'identitas';
$tab = is_string($tabInput) && in_array($tabInput, ['identitas', 'sk', 'dokumen'], true) ? $tabInput : 'identitas';
$errors = [];
$uploadPath = null;
$editSk = null;
$editSkInput = $_GET['edit_sk'] ?? null;
if ($editSkInput !== null) {
    $editSkId = filter_var($editSkInput, FILTER_VALIDATE_INT);
    if (!$editSkId || $editSkId < 1) {
        http_response_code(400);
        exit('ID SK tidak valid.');
    }
    $editSkQuery = $pdo->prepare('SELECT * FROM guru_sk WHERE id = :id AND guru_id = :guru_id LIMIT 1');
    $editSkQuery->execute(['id' => $editSkId, 'guru_id' => $id]);
    $editSk = $editSkQuery->fetch();
    if (!$editSk) {
        http_response_code(404);
        exit('Arsip SK tidak ditemukan.');
    }
    $tab = 'sk';
}

$redirectToTab = static function (int $teacherId, string $targetTab): never {
    header('Location: index.php?page=guru&detail=' . $teacherId . '&tab=' . urlencode($targetTab));
    exit;
};
$validDate = static function (string $value): bool {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTime::getLastErrors();
    return $date !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$canWrite) {
        http_response_code(403);
        exit('Akun Anda hanya dapat melihat data guru.');
    }
    $actionInput = $_POST['action'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';

    if (in_array($action, ['add_sk', 'update_sk'], true)) {
        $skId = $action === 'update_sk' ? filter_var($_POST['sk_id'] ?? null, FILTER_VALIDATE_INT) : null;
        if ($action === 'update_sk' && (!$skId || $skId < 1)) {
            http_response_code(400);
            exit('ID SK tidak valid.');
        }
        $existing = null;
        if ($skId !== null) {
            $existingQuery = $pdo->prepare('SELECT * FROM guru_sk WHERE id = :id AND guru_id = :guru_id LIMIT 1');
            $existingQuery->execute(['id' => $skId, 'guru_id' => $id]);
            $existing = $existingQuery->fetch();
            if (!$existing) {
                http_response_code(404);
                exit('Arsip SK tidak ditemukan.');
            }
        }

        $numberInput = $_POST['nomor_sk'] ?? '';
        $typeInput = $_POST['jenis_sk'] ?? '';
        $issuedInput = $_POST['tanggal_penetapan'] ?? '';
        $startInput = $_POST['tanggal_mulai'] ?? '';
        $endInput = $_POST['tanggal_berakhir'] ?? '';
        $positionInput = $_POST['jabatan'] ?? '';
        $authorityInput = $_POST['pejabat_penetap'] ?? '';
        $noteInput = $_POST['keterangan'] ?? '';
        $number = is_string($numberInput) ? trim($numberInput) : '';
        $type = is_string($typeInput) ? trim($typeInput) : '';
        $issued = is_string($issuedInput) ? trim($issuedInput) : '';
        $start = is_string($startInput) ? trim($startInput) : '';
        $end = is_string($endInput) ? trim($endInput) : '';
        $position = is_string($positionInput) ? trim($positionInput) : '';
        $authority = is_string($authorityInput) ? trim($authorityInput) : '';
        $note = is_string($noteInput) ? trim($noteInput) : '';
        if ($number === '' || mb_strlen($number) > 100) {
            $errors[] = 'Nomor SK wajib diisi dan maksimal 100 karakter.';
        }
        if (!in_array($type, $skTypes, true)) {
            $errors[] = 'Jenis SK tidak valid.';
        }
        foreach (['Tanggal penetapan' => $issued, 'Tanggal mulai berlaku' => $start] as $label => $dateValue) {
            if (!$validDate($dateValue)) {
                $errors[] = $label . ' wajib diisi dengan tanggal yang valid.';
            }
        }
        if ($end !== '' && (!$validDate($end) || ($validDate($start) && $end < $start))) {
            $errors[] = 'Tanggal berakhir tidak valid atau sebelum tanggal mulai berlaku.';
        }
        if (mb_strlen($position) > 150 || mb_strlen($authority) > 150) {
            $errors[] = 'Jabatan dan pejabat penetap maksimal 150 karakter.';
        }

        if (isset($_FILES['sk_file']) && $_FILES['sk_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $uploadPath = store_upload($_FILES['sk_file'], 'document');
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        } elseif ($existing === null) {
            $errors[] = 'File dokumen SK wajib diunggah.';
        }

        if (!$errors) {
            $oldPath = $existing['file_path'] ?? null;
            $filePath = $uploadPath ?? $oldPath;
            try {
                $pdo->beginTransaction();
                if ($existing) {
                    $update = $pdo->prepare(
                        'UPDATE guru_sk SET nomor_sk = :nomor_sk, jenis_sk = :jenis_sk,
                         tanggal_penetapan = :tanggal_penetapan, tanggal_mulai = :tanggal_mulai,
                         tanggal_berakhir = :tanggal_berakhir, jabatan = :jabatan,
                         pejabat_penetap = :pejabat_penetap, keterangan = :keterangan,
                         file_path = :file_path WHERE id = :id AND guru_id = :guru_id'
                    );
                    $update->execute([
                        'nomor_sk' => $number, 'jenis_sk' => $type, 'tanggal_penetapan' => $issued,
                        'tanggal_mulai' => $start, 'tanggal_berakhir' => $end !== '' ? $end : null,
                        'jabatan' => $position !== '' ? $position : null,
                        'pejabat_penetap' => $authority !== '' ? $authority : null,
                        'keterangan' => $note !== '' ? $note : null, 'file_path' => $filePath,
                        'id' => $skId, 'guru_id' => $id,
                    ]);
                    $logAction = 'Ubah arsip SK guru';
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO guru_sk
                            (guru_id, nomor_sk, jenis_sk, tanggal_penetapan, tanggal_mulai,
                             tanggal_berakhir, jabatan, pejabat_penetap, keterangan, file_path, created_by)
                         VALUES
                            (:guru_id, :nomor_sk, :jenis_sk, :tanggal_penetapan, :tanggal_mulai,
                             :tanggal_berakhir, :jabatan, :pejabat_penetap, :keterangan, :file_path, :created_by)'
                    );
                    $insert->execute([
                        'guru_id' => $id, 'nomor_sk' => $number, 'jenis_sk' => $type,
                        'tanggal_penetapan' => $issued, 'tanggal_mulai' => $start,
                        'tanggal_berakhir' => $end !== '' ? $end : null,
                        'jabatan' => $position !== '' ? $position : null,
                        'pejabat_penetap' => $authority !== '' ? $authority : null,
                        'keterangan' => $note !== '' ? $note : null, 'file_path' => $filePath,
                        'created_by' => $user['id'],
                    ]);
                    $logAction = 'Unggah arsip SK guru';
                }
                log_activity($logAction, 'Memperbarui arsip SK untuk guru #' . $id . '.', 'guru', $id);
                $pdo->commit();
                if ($uploadPath !== null && is_string($oldPath) && $oldPath !== '') {
                    remove_upload($oldPath);
                }
                flash('success', $existing ? 'Arsip SK berhasil diperbarui.' : 'Arsip SK berhasil ditambahkan.');
                $redirectToTab($id, 'sk');
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($uploadPath !== null) {
                    remove_upload($uploadPath);
                    $uploadPath = null;
                }
                if ($exception->getCode() === '23000') {
                    $errors[] = 'Nomor SK tersebut sudah tercatat untuk guru ini.';
                } else {
                    throw $exception;
                }
            }
        }
        if ($errors && $uploadPath !== null) {
            remove_upload($uploadPath);
            $uploadPath = null;
        }
    } elseif ($action === 'delete_sk') {
        $skId = filter_var($_POST['sk_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$skId || $skId < 1) {
            http_response_code(400);
            exit('ID SK tidak valid.');
        }
        $fileQuery = $pdo->prepare('SELECT file_path FROM guru_sk WHERE id = :id AND guru_id = :guru_id');
        $fileQuery->execute(['id' => $skId, 'guru_id' => $id]);
        $file = $fileQuery->fetchColumn();
        if (!is_string($file) || $file === '') {
            flash('error', 'SK tidak ditemukan atau sudah dihapus.');
            $redirectToTab($id, 'sk');
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM guru_sk WHERE id = :id AND guru_id = :guru_id')
            ->execute(['id' => $skId, 'guru_id' => $id]);
        log_activity('Hapus arsip SK guru', 'Menghapus arsip SK dari guru #' . $id . '.', 'guru', $id);
        $pdo->commit();
        try {
            remove_upload($file);
        } catch (RuntimeException $exception) {
            flash('error', 'SK dihapus dari daftar, tetapi file lama tidak berhasil dibersihkan.');
            $redirectToTab($id, 'sk');
        }
        flash('success', 'Arsip SK berhasil dihapus.');
        $redirectToTab($id, 'sk');
    } elseif ($action === 'add_document') {
        $typeInput = $_POST['jenis'] ?? '';
        $nameInput = $_POST['nama_dokumen'] ?? '';
        $noteInput = $_POST['keterangan'] ?? '';
        $type = is_string($typeInput) ? trim($typeInput) : '';
        $name = is_string($nameInput) ? trim($nameInput) : '';
        $note = is_string($noteInput) ? trim($noteInput) : '';
        if (!in_array($type, $documentTypes, true)) {
            $errors[] = 'Jenis dokumen pendukung tidak valid.';
        }
        if ($name === '' || mb_strlen($name) > 180) {
            $errors[] = 'Nama dokumen wajib diisi dan maksimal 180 karakter.';
        }
        if (!$errors) {
            try {
                if (!isset($_FILES['document'])) {
                    throw new RuntimeException('Silakan pilih dokumen pendukung.');
                }
                $uploadPath = store_upload($_FILES['document'], 'document');
                $insert = $pdo->prepare(
                    'INSERT INTO guru_dokumen (guru_id, jenis, nama_dokumen, keterangan, file_path, created_by)
                     VALUES (:guru_id, :jenis, :nama_dokumen, :keterangan, :file_path, :created_by)'
                );
                $insert->execute([
                    'guru_id' => $id, 'jenis' => $type, 'nama_dokumen' => $name,
                    'keterangan' => $note !== '' ? $note : null, 'file_path' => $uploadPath,
                    'created_by' => $user['id'],
                ]);
                log_activity('Unggah dokumen guru', 'Menambahkan dokumen pendukung untuk guru #' . $id . '.', 'guru', $id);
                flash('success', 'Dokumen pendukung berhasil ditambahkan.');
                $redirectToTab($id, 'dokumen');
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (PDOException $exception) {
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
        $fileQuery = $pdo->prepare('SELECT file_path FROM guru_dokumen WHERE id = :id AND guru_id = :guru_id');
        $fileQuery->execute(['id' => $documentId, 'guru_id' => $id]);
        $file = $fileQuery->fetchColumn();
        if (!is_string($file) || $file === '') {
            flash('error', 'Dokumen tidak ditemukan atau sudah dihapus.');
            $redirectToTab($id, 'dokumen');
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM guru_dokumen WHERE id = :id AND guru_id = :guru_id')
            ->execute(['id' => $documentId, 'guru_id' => $id]);
        log_activity('Hapus dokumen guru', 'Menghapus dokumen pendukung dari guru #' . $id . '.', 'guru', $id);
        $pdo->commit();
        try {
            remove_upload($file);
        } catch (RuntimeException $exception) {
            flash('error', 'Dokumen dihapus dari daftar, tetapi file lama tidak berhasil dibersihkan.');
            $redirectToTab($id, 'dokumen');
        }
        flash('success', 'Dokumen pendukung berhasil dihapus.');
        $redirectToTab($id, 'dokumen');
    } else {
        http_response_code(400);
        exit('Aksi profil guru tidak valid.');
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
$skQuery = $pdo->prepare('SELECT * FROM guru_sk WHERE guru_id = :id ORDER BY tanggal_penetapan DESC, id DESC');
$skQuery->execute(['id' => $id]);
$skRows = $skQuery->fetchAll();
$documentQuery = $pdo->prepare('SELECT * FROM guru_dokumen WHERE guru_id = :id ORDER BY created_at DESC, id DESC');
$documentQuery->execute(['id' => $id]);
$documents = $documentQuery->fetchAll();
$age = !empty($teacher['tanggal_lahir'])
    ? (new DateTime($teacher['tanggal_lahir']))->diff(new DateTime('today'))->y . ' tahun'
    : 'Belum diisi';
$tabs = ['identitas' => 'Identitas Guru', 'sk' => 'Arsip SK', 'dokumen' => 'Dokumen Pendukung'];
?>
<section class="panel">
    <div class="panel-heading"><div><h2>Profil Guru</h2><p class="subtitle">Biodata, arsip SK, dan dokumen.</p></div><a class="btn" href="index.php?page=guru">Kembali ke Data Guru</a></div>
    <div class="profile-hero">
        <?php if (!empty($teacher['foto'])): ?><a href="file.php?entity=guru&id=<?= $id ?>" target="_blank" rel="noopener"><img src="file.php?entity=guru&id=<?= $id ?>" alt="Foto <?= e($teacher['nama_lengkap']) ?>"></a><?php else: ?><img src="assets/img/avatar-placeholder.svg" alt="Foto belum tersedia"><?php endif; ?>
        <div><h2><?= e($teacher['nama_lengkap']) ?></h2><p class="subtitle"><?= e($teacher['kode_guru']) ?> · <?= e($teacher['jabatan'] ?? 'Jabatan belum diisi') ?> · <?= e($teacher['status']) ?></p><?php if ($canWrite): ?><a class="btn btn-small" href="index.php?page=guru&edit=<?= $id ?>">Edit Profil / Foto</a><?php endif; ?></div>
    </div>
    <nav class="profile-tabs" aria-label="Bagian profil guru"><?php foreach ($tabs as $tabKey => $label): ?><a class="<?= $tab === $tabKey ? 'active' : '' ?>" href="index.php?page=guru&detail=<?= $id ?>&tab=<?= e($tabKey) ?>" <?= $tab === $tabKey ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
</section>

<?php if ($errors): ?><div class="alert-error" role="alert"><strong>Perubahan belum dapat disimpan:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($tab === 'identitas'): ?>
<section class="panel">
    <h3>Identitas Guru</h3>
    <dl class="detail-grid">
        <div><dt>Kode Guru</dt><dd><?= e($teacher['kode_guru']) ?></dd></div><div><dt>NIK</dt><dd><?= e($mask($teacher['nik'])) ?></dd></div>
        <div><dt>Nama Lengkap</dt><dd><?= e($teacher['nama_lengkap']) ?></dd></div><div><dt>Jenis Kelamin</dt><dd><?= e($teacher['jenis_kelamin']) ?></dd></div>
        <div><dt>Tempat, Tanggal Lahir</dt><dd><?= e(trim(($teacher['tempat_lahir'] ?? '') . (!empty($teacher['tanggal_lahir']) ? ', ' . date('d-m-Y', strtotime($teacher['tanggal_lahir'])) : '')) ?: '—') ?></dd></div><div><dt>Umur</dt><dd><?= e($age) ?></dd></div>
        <div><dt>Pendidikan Terakhir</dt><dd><?= e($teacher['pendidikan_terakhir'] ?? '—') ?></dd></div><div><dt>Mata Pelajaran</dt><dd><?= e($teacher['mata_pelajaran_nama'] ?? '—') ?></dd></div>
        <div><dt>Jabatan</dt><dd><?= e($teacher['jabatan'] ?? '—') ?></dd></div><div><dt>Nomor HP / WhatsApp</dt><dd><?= e($mask($teacher['nomor_telepon'])) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($teacher['email'] ?? '—') ?></dd></div><div><dt>Mulai Mengajar</dt><dd><?= e($teacher['tanggal_bergabung'] ? date('d-m-Y', strtotime($teacher['tanggal_bergabung'])) : '—') ?></dd></div>
        <div><dt>Status Kepegawaian</dt><dd><?= e($teacher['status']) ?></dd></div><div><dt>Alamat</dt><dd><?= nl2br(e($mask($teacher['alamat']))) ?></dd></div>
    </dl>
</section>
<?php elseif ($tab === 'sk'): ?>
<section class="panel" id="teacher-sk">
    <div class="section-heading"><h3>Arsip Surat Keputusan (SK)</h3><span class="tag"><?= count($skRows) ?> dokumen</span></div>
    <div class="table-wrap"><table><thead><tr><th>Nomor SK</th><th>Jenis SK</th><th>Penetapan</th><th>Masa Berlaku</th><th>Jabatan</th><th>Pejabat Penetap</th><th>File</th><?php if ($canWrite): ?><th>Aksi</th><?php endif; ?></tr></thead><tbody>
        <?php if (!$skRows): ?><tr><td class="empty" colspan="<?= $canWrite ? 8 : 7 ?>">Belum ada arsip SK.</td></tr><?php endif; ?>
        <?php foreach ($skRows as $sk): ?><tr><td><?= e($sk['nomor_sk']) ?></td><td><?= e($sk['jenis_sk']) ?></td><td><?= e(date('d-m-Y', strtotime($sk['tanggal_penetapan']))) ?></td><td><?= e(date('d-m-Y', strtotime($sk['tanggal_mulai']))) ?> – <?= e($sk['tanggal_berakhir'] ? date('d-m-Y', strtotime($sk['tanggal_berakhir'])) : 'Tidak ditentukan') ?></td><td><?= e($sk['jabatan'] ?? '—') ?></td><td><?= e($sk['pejabat_penetap'] ?? '—') ?></td><td><div class="file-actions"><a class="btn btn-small" href="file.php?entity=guru_sk&id=<?= (int)$sk['id'] ?>" target="_blank" rel="noopener">Lihat</a><a class="btn btn-small" href="file.php?entity=guru_sk&id=<?= (int)$sk['id'] ?>&download=1">Unduh</a></div></td><?php if ($canWrite): ?><td class="row-actions"><a class="btn btn-small" href="index.php?page=guru&detail=<?= $id ?>&tab=sk&edit_sk=<?= (int)$sk['id'] ?>">Edit</a><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_sk"><input type="hidden" name="sk_id" value="<?= (int)$sk['id'] ?>"><button type="button" class="btn btn-danger btn-small confirm-delete" data-confirm-message="Hapus arsip SK ini?">Hapus</button></form></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($canWrite): $formSk = $editSk ?: []; ?>
    <form class="doc-form" method="post" enctype="multipart/form-data">
        <h3><?= $editSk ? 'Edit Arsip SK' : 'Tambah Arsip SK' ?></h3>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $editSk ? 'update_sk' : 'add_sk' ?>"><?php if ($editSk): ?><input type="hidden" name="sk_id" value="<?= (int)$editSk['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field"><label for="sk-number">Nomor SK</label><input id="sk-number" name="nomor_sk" maxlength="100" required value="<?= e($formSk['nomor_sk'] ?? '') ?>"></div>
            <div class="field"><label for="sk-type">Jenis SK</label><select id="sk-type" name="jenis_sk" required><?php foreach ($skTypes as $type): ?><option value="<?= e($type) ?>" <?= ($formSk['jenis_sk'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="sk-issued">Tanggal Penetapan</label><input id="sk-issued" type="date" name="tanggal_penetapan" required value="<?= e($formSk['tanggal_penetapan'] ?? '') ?>"></div>
            <div class="field"><label for="sk-start">Tanggal Mulai Berlaku</label><input id="sk-start" type="date" name="tanggal_mulai" required value="<?= e($formSk['tanggal_mulai'] ?? '') ?>"></div>
            <div class="field"><label for="sk-end">Tanggal Berakhir</label><input id="sk-end" type="date" name="tanggal_berakhir" value="<?= e($formSk['tanggal_berakhir'] ?? '') ?>"></div>
            <div class="field"><label for="sk-position">Jabatan dalam SK</label><input id="sk-position" name="jabatan" maxlength="150" value="<?= e($formSk['jabatan'] ?? '') ?>"></div>
            <div class="field"><label for="sk-authority">Pejabat yang Menetapkan</label><input id="sk-authority" name="pejabat_penetap" maxlength="150" value="<?= e($formSk['pejabat_penetap'] ?? '') ?>"></div>
            <div class="field"><label for="sk-note">Keterangan</label><input id="sk-note" name="keterangan" maxlength="2000" value="<?= e($formSk['keterangan'] ?? '') ?>"></div>
            <div class="field"><label for="sk-file">File SK (PDF / JPG / PNG)<?= $editSk ? ' — kosongkan untuk mempertahankan file' : '' ?></label><input id="sk-file" type="file" name="sk_file" accept="application/pdf,image/jpeg,image/png" <?= $editSk ? '' : 'required' ?>><small>Maksimal 5 MB.</small><?php if ($editSk): ?><p><a href="file.php?entity=guru_sk&id=<?= (int)$editSk['id'] ?>" target="_blank" rel="noopener">Lihat file saat ini</a></p><?php endif; ?></div>
        </div><div class="form-actions"><button class="btn btn-primary" type="submit"><?= $editSk ? 'Simpan Perubahan' : 'Tambah SK' ?></button><?php if ($editSk): ?><a class="btn" href="index.php?page=guru&detail=<?= $id ?>&tab=sk">Batal</a><?php endif; ?></div>
    </form>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="panel" id="teacher-documents">
    <h3>Dokumen Pendukung</h3>
    <div class="table-wrap"><table><thead><tr><th>Jenis</th><th>Nama Dokumen</th><th>Keterangan</th><th>File</th><?php if ($canWrite): ?><th>Aksi</th><?php endif; ?></tr></thead><tbody>
        <?php if (!$documents): ?><tr><td class="empty" colspan="<?= $canWrite ? 5 : 4 ?>">Belum ada dokumen pendukung.</td></tr><?php endif; ?>
        <?php foreach ($documents as $document): ?><tr><td><?= e($document['jenis']) ?></td><td><?= e($document['nama_dokumen']) ?></td><td><?= e($document['keterangan'] ?? '—') ?></td><td><div class="file-actions"><a class="btn btn-small" href="file.php?entity=guru_dokumen&id=<?= (int)$document['id'] ?>" target="_blank" rel="noopener">Lihat</a><a class="btn btn-small" href="file.php?entity=guru_dokumen&id=<?= (int)$document['id'] ?>&download=1">Unduh</a></div></td><?php if ($canWrite): ?><td><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>"><button type="button" class="btn btn-danger btn-small confirm-delete">Hapus</button></form></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($canWrite): ?><form class="doc-form" method="post" enctype="multipart/form-data"><h3>Tambah Dokumen Pendukung</h3><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_document"><div class="form-grid">
        <div class="field"><label for="teacher-document-type">Jenis</label><select id="teacher-document-type" name="jenis" required><?php foreach ($documentTypes as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="teacher-document-name">Nama Dokumen</label><input id="teacher-document-name" name="nama_dokumen" maxlength="180" required></div>
        <div class="field"><label for="teacher-document-file">File PDF / JPG / PNG</label><input id="teacher-document-file" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required><small>Maksimal 5 MB.</small></div>
        <div class="field"><label for="teacher-document-note">Keterangan</label><input id="teacher-document-note" name="keterangan" maxlength="1000"></div>
    </div><div class="form-actions"><button class="btn btn-primary" type="submit">Unggah Dokumen</button></div></form><?php endif; ?>
</section>
<?php endif; ?>
