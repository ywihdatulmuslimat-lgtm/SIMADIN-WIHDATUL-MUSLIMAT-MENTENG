<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/uploads.php';

$pdo = db();
$canWrite = in_array($user['role'], ['Super Admin', 'Operator'], true);
$categories = [
    'Izin Operasional (IZOP)',
    'Akta Pendirian',
    'SK Pendirian Lembaga',
    'Nomor Statistik Lembaga',
    'NPWP Lembaga',
    'Sertifikat / Dokumen Legalitas Lainnya',
    'Akta Yayasan',
    'SK Kemenkumham',
    'Izin Operasional',
    'Nomor Statistik',
    'NPWP',
    'Akreditasi',
];
$storedCategories = $pdo->query("SELECT DISTINCT jenis FROM dokumen_legalitas WHERE jenis IS NOT NULL AND jenis <> ''")->fetchAll(PDO::FETCH_COLUMN);
$categories = array_values(array_unique(array_merge($categories, array_map('strval', $storedCategories))));
$errors = [];
$values = [];
$edit = null;
$view = null;
$newFilePath = null;

$redirectToList = static function (): never {
    header('Location: index.php?page=legalitas');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$canWrite) {
        http_response_code(403);
        exit('Akun Anda hanya dapat melihat dokumen legalitas.');
    }

    $actionInput = $_POST['action'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            http_response_code(400);
            exit('ID dokumen tidak valid.');
        }
        $query = $pdo->prepare('SELECT file_path FROM dokumen_legalitas WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $file = $query->fetchColumn();
        if ($file === false) {
            flash('error', 'Dokumen tidak ditemukan atau file-nya tidak tercatat.');
            $redirectToList();
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM dokumen_legalitas WHERE id = :id')->execute(['id' => $id]);
        log_activity('Hapus dokumen legalitas', 'Menghapus dokumen legalitas #' . $id . '.', 'legalitas', $id);
        $pdo->commit();
        if (is_string($file) && $file !== '') {
            try {
                remove_upload($file);
            } catch (RuntimeException $exception) {
                flash('error', 'Data dokumen dihapus, tetapi file lama gagal dibersihkan: ' . $exception->getMessage());
                $redirectToList();
            }
        }
        flash('success', 'Dokumen legalitas berhasil dihapus.');
        $redirectToList();
    }

    if ($action !== 'save') {
        http_response_code(400);
        exit('Aksi dokumen tidak valid.');
    }

    $idInput = $_POST['id'] ?? '';
    $id = $idInput === '' ? null : filter_var($idInput, FILTER_VALIDATE_INT);
    if ($idInput !== '' && (!$id || $id < 1)) {
        http_response_code(400);
        exit('ID dokumen tidak valid.');
    }
    if ($id !== null) {
        $query = $pdo->prepare('SELECT * FROM dokumen_legalitas WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $edit = $query->fetch();
        if (!$edit) {
            http_response_code(404);
            exit('Dokumen yang akan diedit tidak ditemukan.');
        }
    }

    foreach (['jenis', 'nama_dokumen', 'nomor_dokumen', 'instansi_penerbit', 'tanggal_penerbitan', 'masa_berlaku', 'tanggal_kedaluwarsa', 'keterangan'] as $key) {
        $raw = $_POST[$key] ?? '';
        $values[$key] = is_scalar($raw) ? trim((string)$raw) : '';
    }
    if (!in_array($values['jenis'], $categories, true)) {
        $errors[] = 'Jenis dokumen tidak valid.';
    }
    if ($values['nama_dokumen'] === '' || mb_strlen($values['nama_dokumen']) > 150) {
        $errors[] = 'Nama dokumen wajib diisi dan maksimal 150 karakter.';
    }
    if (mb_strlen($values['nomor_dokumen']) > 100) {
        $errors[] = 'Nomor dokumen maksimal 100 karakter.';
    }
    if (mb_strlen($values['instansi_penerbit']) > 180) {
        $errors[] = 'Instansi penerbit maksimal 180 karakter.';
    }
    foreach (['tanggal_penerbitan', 'masa_berlaku', 'tanggal_kedaluwarsa'] as $dateField) {
        if ($values[$dateField] === '') {
            continue;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $values[$dateField]);
        $dateErrors = DateTime::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $values[$dateField]) {
            $errors[] = 'Tanggal dokumen tidak valid.';
            break;
        }
    }
    if ($values['masa_berlaku'] !== '' && $values['tanggal_kedaluwarsa'] !== '' && $values['tanggal_kedaluwarsa'] < $values['masa_berlaku']) {
        $errors[] = 'Tanggal kedaluwarsa tidak boleh sebelum tanggal berlaku.';
    }
    if (!$edit && (!isset($_FILES['document']) || ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
        $errors[] = 'File dokumen wajib diunggah.';
    }

    $oldFilePath = $edit ? (string)($edit['file_path'] ?? '') : '';
    if (!$errors && isset($_FILES['document']) && ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $newFilePath = store_upload($_FILES['document'], 'document');
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if ($errors) {
        if ($newFilePath !== null) {
            remove_upload($newFilePath);
            $newFilePath = null;
        }
    } else {
        $filePath = $newFilePath ?? $oldFilePath;
        try {
            $pdo->beginTransaction();
            $parameters = [
                'jenis' => $values['jenis'],
                'nama_dokumen' => $values['nama_dokumen'],
                'nomor_dokumen' => $values['nomor_dokumen'] !== '' ? $values['nomor_dokumen'] : null,
                'instansi_penerbit' => $values['instansi_penerbit'] !== '' ? $values['instansi_penerbit'] : null,
                'tanggal_penerbitan' => $values['tanggal_penerbitan'] !== '' ? $values['tanggal_penerbitan'] : null,
                'masa_berlaku' => $values['masa_berlaku'] !== '' ? $values['masa_berlaku'] : null,
                'tanggal_kedaluwarsa' => $values['tanggal_kedaluwarsa'] !== '' ? $values['tanggal_kedaluwarsa'] : null,
                'keterangan' => $values['keterangan'] !== '' ? $values['keterangan'] : null,
                'file_path' => $filePath !== '' ? $filePath : null,
            ];
            if ($id === null) {
                $pdo->prepare(
                    'INSERT INTO dokumen_legalitas
                        (jenis, nama_dokumen, nomor_dokumen, instansi_penerbit, tanggal_penerbitan,
                         masa_berlaku, tanggal_kedaluwarsa, keterangan, file_path)
                     VALUES
                        (:jenis, :nama_dokumen, :nomor_dokumen, :instansi_penerbit, :tanggal_penerbitan,
                         :masa_berlaku, :tanggal_kedaluwarsa, :keterangan, :file_path)'
                )->execute($parameters);
                $savedId = (int)$pdo->lastInsertId();
                log_activity('Tambah dokumen legalitas', 'Menambahkan dokumen legalitas #' . $savedId . '.', 'legalitas', $savedId);
            } else {
                $parameters['id'] = $id;
                $pdo->prepare(
                    'UPDATE dokumen_legalitas
                     SET jenis = :jenis, nama_dokumen = :nama_dokumen, nomor_dokumen = :nomor_dokumen,
                         instansi_penerbit = :instansi_penerbit, tanggal_penerbitan = :tanggal_penerbitan,
                         masa_berlaku = :masa_berlaku, tanggal_kedaluwarsa = :tanggal_kedaluwarsa,
                         keterangan = :keterangan, file_path = :file_path
                     WHERE id = :id'
                )->execute($parameters);
                log_activity('Ubah dokumen legalitas', 'Memperbarui dokumen legalitas #' . $id . '.', 'legalitas', $id);
            }
            if ($newFilePath !== null) {
                log_activity(
                    'Unggah dokumen legalitas',
                    'Mengunggah atau mengganti file dokumen legalitas #' . $savedId . '.',
                    'legalitas',
                    $savedId
                );
            }
            $pdo->commit();
            if ($newFilePath !== null && $oldFilePath !== '' && $newFilePath !== $oldFilePath) {
                try {
                    remove_upload($oldFilePath);
                } catch (RuntimeException $exception) {
                    flash('error', 'Dokumen tersimpan, tetapi file pengganti lama gagal dibersihkan: ' . $exception->getMessage());
                    $redirectToList();
                }
            }
            flash('success', 'Dokumen legalitas berhasil disimpan.');
            $redirectToList();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newFilePath !== null) {
                remove_upload($newFilePath);
                $newFilePath = null;
            }
            if ($exception->getCode() === '23000') {
                $errors[] = 'Dokumen tidak dapat disimpan karena data terkait tidak valid.';
            } else {
                throw $exception;
            }
        }
    }
}

$searchInput = $_GET['q'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$typeInput = $_GET['jenis'] ?? '';
$selectedType = is_string($typeInput) && in_array($typeInput, $categories, true) ? $typeInput : '';
$where = [];
$parameters = [];
if ($search !== '') {
    $where[] = '(nama_dokumen LIKE :search_name OR nomor_dokumen LIKE :search_number)';
    $parameters['search_name'] = '%' . $search . '%';
    $parameters['search_number'] = '%' . $search . '%';
}
if ($selectedType !== '') {
    $where[] = 'jenis = :jenis';
    $parameters['jenis'] = $selectedType;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM dokumen_legalitas' . $whereSql);
$countQuery->execute($parameters);
$totalRows = (int)$countQuery->fetchColumn();
$listQuery = $pdo->prepare(
    'SELECT id, jenis, nama_dokumen, nomor_dokumen, instansi_penerbit, tanggal_penerbitan,
            masa_berlaku, tanggal_kedaluwarsa, keterangan, file_path
     FROM dokumen_legalitas' . $whereSql . ' ORDER BY tanggal_penerbitan DESC, id DESC'
);
$listQuery->execute($parameters);
$rows = $listQuery->fetchAll();
$izopQuery = $pdo->prepare(
    'SELECT id, jenis, nama_dokumen, nomor_dokumen, tanggal_penerbitan, tanggal_kedaluwarsa
     FROM dokumen_legalitas
     WHERE jenis IN (:izop, :legacy)
     ORDER BY tanggal_penerbitan DESC, id DESC LIMIT 8'
);
$izopQuery->execute(['izop' => 'Izin Operasional (IZOP)', 'legacy' => 'Izin Operasional']);
$izopRows = $izopQuery->fetchAll();

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId) {
    $query = $pdo->prepare('SELECT * FROM dokumen_legalitas WHERE id = :id LIMIT 1');
    $query->execute(['id' => $editId]);
    $edit = $query->fetch();
    if (!$edit) {
        http_response_code(404);
        exit('Dokumen yang akan diedit tidak ditemukan.');
    }
}
$viewId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT);
if ($viewId) {
    $query = $pdo->prepare('SELECT * FROM dokumen_legalitas WHERE id = :id LIMIT 1');
    $query->execute(['id' => $viewId]);
    $view = $query->fetch();
    if (!$view) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
}

$dateInputValue = static function (string $key) use ($values, $edit): string {
    return (string)($values[$key] ?? $edit[$key] ?? '');
};
?>
<?php if ($errors): ?><div class="alert-error" role="alert"><strong>Dokumen belum dapat disimpan:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($view): ?>
    <section class="panel">
        <div class="panel-heading"><div><h2>Detail Dokumen Legalitas</h2><p class="subtitle"><?= e($view['jenis']) ?></p></div><a class="btn" href="index.php?page=legalitas">Kembali</a></div>
        <dl class="detail-grid">
            <div><dt>Nama Dokumen</dt><dd><?= e($view['nama_dokumen']) ?></dd></div>
            <div><dt>Nomor Dokumen</dt><dd><?= e($view['nomor_dokumen'] ?? '—') ?></dd></div>
            <div><dt>Instansi Penerbit</dt><dd><?= e($view['instansi_penerbit'] ?? '—') ?></dd></div>
            <div><dt>Tanggal Penerbitan</dt><dd><?= e($view['tanggal_penerbitan'] ? date('d-m-Y', strtotime($view['tanggal_penerbitan'])) : '—') ?></dd></div>
            <div><dt>Tanggal Berlaku</dt><dd><?= e($view['masa_berlaku'] ? date('d-m-Y', strtotime($view['masa_berlaku'])) : '—') ?></dd></div>
            <div><dt>Tanggal Kedaluwarsa</dt><dd><?= e($view['tanggal_kedaluwarsa'] ? date('d-m-Y', strtotime($view['tanggal_kedaluwarsa'])) : '—') ?></dd></div>
            <div class="field-wide"><dt>Keterangan</dt><dd><?= nl2br(e($view['keterangan'] ?? '—')) ?></dd></div>
        </dl>
        <?php if (!empty($view['file_path'])): ?><div class="file-actions"><a class="btn" href="file.php?entity=legalitas&id=<?= (int)$view['id'] ?>" target="_blank" rel="noopener">Lihat / Pratinjau</a><a class="btn" href="file.php?entity=legalitas&id=<?= (int)$view['id'] ?>&download=1">Download</a></div><?php endif; ?>
        <?php if ($canWrite): ?><a class="btn btn-primary" href="index.php?page=legalitas&edit=<?= (int)$view['id'] ?>">Edit Dokumen</a><?php endif; ?>
    </section>
<?php else: ?>
    <section class="panel">
        <div class="panel-heading">
            <div><h2>Dokumen IZOP</h2><p class="subtitle">Izin Operasional Madrasah yang terdaftar.</p></div>
            <?php if ($canWrite): ?><a class="btn btn-primary" href="index.php?page=legalitas&action=form&jenis=<?= urlencode('Izin Operasional (IZOP)') ?>">+ Tambah IZOP</a><?php endif; ?>
        </div>
        <?php if (!$izopRows): ?><div class="empty">Dokumen IZOP belum tersedia.</div><?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Nama Dokumen</th><th>Nomor</th><th>Tanggal Terbit</th><th>Kedaluwarsa</th><th>Dokumen</th></tr></thead><tbody>
                <?php foreach ($izopRows as $row): ?><tr>
                    <td><a href="index.php?page=legalitas&view=<?= (int)$row['id'] ?>"><?= e($row['nama_dokumen']) ?></a></td>
                    <td><?= e($row['nomor_dokumen'] ?? '—') ?></td>
                    <td><?= e($row['tanggal_penerbitan'] ? date('d-m-Y', strtotime($row['tanggal_penerbitan'])) : '—') ?></td>
                    <td><?= e($row['tanggal_kedaluwarsa'] ? date('d-m-Y', strtotime($row['tanggal_kedaluwarsa'])) : '—') ?></td>
                    <td><div class="file-actions"><a class="btn btn-small" href="file.php?entity=legalitas&id=<?= (int)$row['id'] ?>" target="_blank" rel="noopener">Lihat</a><a class="btn btn-small" href="file.php?entity=legalitas&id=<?= (int)$row['id'] ?>&download=1">Unduh</a></div></td>
                </tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Legalitas dan Perizinan</h2><p class="subtitle"><?= number_format($totalRows) ?> dokumen terdaftar</p></div><div class="data-actions"><button class="btn" type="button" data-print-page>Ekspor PDF / Cetak</button><?php if ($canWrite): ?><a class="btn btn-primary" href="index.php?page=legalitas&action=form">+ Tambah Dokumen</a><?php endif; ?></div></div>
        <form class="toolbar" method="get" action="index.php">
            <input type="hidden" name="page" value="legalitas">
            <label class="sr-only" for="legal-search">Cari nama atau nomor dokumen</label><input id="legal-search" class="search" type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau nomor dokumen...">
            <label class="sr-only" for="legal-type">Jenis dokumen</label><select id="legal-type" class="search filter-select" name="jenis"><option value="">Semua jenis dokumen</option><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>" <?= $selectedType === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select>
            <button class="btn" type="submit">Cari / Filter</button>
        </form>
        <div class="table-wrap"><table><thead><tr><th>Jenis</th><th>Nama Dokumen</th><th>Nomor</th><th>Instansi Penerbit</th><th>Tanggal Terbit</th><th>Berlaku</th><th>Kedaluwarsa</th><th>File</th><th>Aksi</th></tr></thead><tbody>
            <?php if (!$rows): ?><tr><td class="empty" colspan="9">Belum ada dokumen legalitas yang sesuai.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?><tr>
                <td><?= e($row['jenis']) ?></td><td><?= e($row['nama_dokumen']) ?></td><td><?= e($row['nomor_dokumen'] ?? '—') ?></td>
                <td><?= e($row['instansi_penerbit'] ?? '—') ?></td>
                <td><?= e($row['tanggal_penerbitan'] ? date('d-m-Y', strtotime($row['tanggal_penerbitan'])) : '—') ?></td>
                <td><?= e($row['masa_berlaku'] ? date('d-m-Y', strtotime($row['masa_berlaku'])) : '—') ?></td>
                <td><?= e($row['tanggal_kedaluwarsa'] ? date('d-m-Y', strtotime($row['tanggal_kedaluwarsa'])) : '—') ?></td>
                <td><?php if (!empty($row['file_path'])): ?><div class="file-actions"><a class="btn btn-small" href="file.php?entity=legalitas&id=<?= (int)$row['id'] ?>" target="_blank" rel="noopener">Lihat</a><a class="btn btn-small" href="file.php?entity=legalitas&id=<?= (int)$row['id'] ?>&download=1">Unduh</a></div><?php else: ?>—<?php endif; ?></td>
                <td class="row-actions"><a class="btn btn-small" href="index.php?page=legalitas&view=<?= (int)$row['id'] ?>">Detail</a><?php if ($canWrite): ?><a class="btn btn-small" href="index.php?page=legalitas&edit=<?= (int)$row['id'] ?>">Edit</a><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button type="button" class="btn btn-danger btn-small confirm-delete">Hapus</button></form><?php endif; ?></td>
            </tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php if ($canWrite && (!empty($_GET['action']) || $edit || $errors)): ?>
        <section class="panel form-panel">
            <h2><?= $edit ? 'Edit' : 'Tambah' ?> Dokumen Legalitas</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">
                <div class="form-grid">
                    <?php $currentType = (string)($values['jenis'] ?? $edit['jenis'] ?? $_GET['jenis'] ?? ''); ?>
                    <div class="field"><label for="legal-category">Jenis Dokumen *</label><select id="legal-category" name="jenis" required><?php foreach (array_unique(array_merge($categories, $currentType !== '' && !in_array($currentType, $categories, true) ? [$currentType] : [])) as $category): ?><option value="<?= e($category) ?>" <?= $currentType === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="legal-name">Nama Dokumen *</label><input id="legal-name" name="nama_dokumen" maxlength="150" value="<?= e($values['nama_dokumen'] ?? $edit['nama_dokumen'] ?? '') ?>" required></div>
                    <div class="field"><label for="legal-number">Nomor Dokumen</label><input id="legal-number" name="nomor_dokumen" maxlength="100" value="<?= e($values['nomor_dokumen'] ?? $edit['nomor_dokumen'] ?? '') ?>"></div>
                    <div class="field"><label for="legal-agency">Instansi Penerbit</label><input id="legal-agency" name="instansi_penerbit" maxlength="180" value="<?= e($values['instansi_penerbit'] ?? $edit['instansi_penerbit'] ?? '') ?>"></div>
                    <div class="field"><label for="legal-issued">Tanggal Penerbitan</label><input id="legal-issued" type="date" name="tanggal_penerbitan" value="<?= e($dateInputValue('tanggal_penerbitan')) ?>"></div>
                    <div class="field"><label for="legal-start">Tanggal Berlaku</label><input id="legal-start" type="date" name="masa_berlaku" value="<?= e($dateInputValue('masa_berlaku')) ?>"></div>
                    <div class="field"><label for="legal-expiry">Tanggal Kedaluwarsa</label><input id="legal-expiry" type="date" name="tanggal_kedaluwarsa" value="<?= e($dateInputValue('tanggal_kedaluwarsa')) ?>"></div>
                    <div class="field field-wide"><label for="legal-note">Keterangan</label><textarea id="legal-note" name="keterangan" rows="3"><?= e($values['keterangan'] ?? $edit['keterangan'] ?? '') ?></textarea></div>
                    <div class="field field-wide"><label for="legal-file">File PDF / JPG / JPEG / PNG <?= $edit ? '(opsional untuk mengganti file)' : '*' ?></label>
                        <?php if ($edit && !empty($edit['file_path'])): ?><p class="subtitle">File saat ini: <a href="file.php?entity=legalitas&id=<?= (int)$edit['id'] ?>" target="_blank" rel="noopener">Lihat / pratinjau</a> · <a href="file.php?entity=legalitas&id=<?= (int)$edit['id'] ?>&download=1">Unduh</a></p><?php endif; ?>
                        <input id="legal-file" type="file" name="document" accept="application/pdf,image/jpeg,image/png" <?= $edit ? '' : 'required' ?>><small>Maksimal 5 MB.</small>
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Simpan Dokumen</button><a class="btn" href="index.php?page=legalitas">Batal</a></div>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
