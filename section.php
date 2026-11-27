<?php
declare(strict_types=1);
$profile=db()->query('SELECT * FROM institution_profile WHERE id=1')->fetch();
$sectionFields=['history'=>'Sejarah Yayasan','vision'=>'Visi','mission'=>'Misi','organization_structure'=>'Struktur Organisasi','programs'=>'Program Kerja'];
$fieldMap=['history'=>'history','vision'=>'vision','mission'=>'mission','structure'=>'organization_structure','work-programs'=>'programs'];
if(isset($fieldMap[$page])) {
    $field=$fieldMap[$page];$value=$field==='programs'?'':($profile[$field]??'');
    if($_SERVER['REQUEST_METHOD']==='POST' && in_array($user['role'],['super_admin','admin_operator'],true)) {
        verify_csrf();$value=trim((string)($_POST['content']??''));
        if($field!=='programs'){$stmt=db()->prepare("UPDATE institution_profile SET `$field`=:content WHERE id=1");$stmt->execute(['content'=>$value]);}
        log_activity('update',$page,'Data kelembagaan diperbarui');
        $_SESSION['flash']=['type'=>'success','text'=>'Data berhasil disimpan.'];
        header('Location: index.php?page='.rawurlencode($page));exit;
    }
    $flash=flash_message();
    if($flash)echo '<div class="alert alert-'.h($flash['type']).'">'.h($flash['text']).'</div>';
    echo '<section class="panel"><div class="panel-head"><h2>'.h($title).'</h2>'.(in_array($user['role'],['super_admin','admin_operator'],true)?'<a class="btn btn-primary" href="index.php?page=profile&edit=1">Edit Profil</a>':'').'</div>';
    if($field==='programs')echo '<p>Program kerja dicatat dalam modul Program dan Kegiatan.</p><a class="btn btn-soft" href="index.php?page=programs">Buka Program dan Kegiatan</a>';
    else echo '<div>'.($value!==''?nl2br(h($value)):'<div class="empty">Informasi belum diisi.</div>').'</div>';
    echo '</section>';
} elseif($page==='facility-photos' || $page==='program-gallery') {
    echo '<section class="panel"><div class="panel-head"><h2>'.h($title).'</h2><a class="btn btn-primary" href="index.php?page='.($page==='facility-photos'?'facilities':'programs').'">Kelola Foto / Dokumentasi</a></div><div class="empty">Dokumentasi tersimpan bersama data fasilitas atau program. File dapat dilihat melalui halaman detail masing-masing.</div></section>';
} elseif($page==='child-documents' || $page==='elderly-documents') {
    echo '<section class="panel"><h2>'.h($title).'</h2><p>Unggah dokumen dari formulir data dokumen.</p><a class="btn btn-primary" href="index.php?page='.($page==='child-documents'?'child-documents':'elderly-documents').'&action=create">Tambah Dokumen</a></section>';
} else {
    echo '<section class="panel"><h2>'.h($title).'</h2><p>Catat program dan aktivitas menggunakan modul Program dan Kegiatan agar masuk dalam laporan dan kalender yayasan.</p><a class="btn btn-primary" href="index.php?page=programs">Buka Program dan Kegiatan</a></section>';
}
