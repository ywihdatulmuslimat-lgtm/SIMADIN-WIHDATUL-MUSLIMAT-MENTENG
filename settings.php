<?php
declare(strict_types=1);
$roles=[
    'super_admin'=>'Akses seluruh modul, pengguna, konfigurasi, dan cadangan.',
    'ketua_yayasan'=>'Melihat dashboard, laporan, serta data operasional tanpa mengubahnya.',
    'admin_operator'=>'Mengelola administrasi operasional, profil, laporan, dan cadangan.',
    'petugas'=>'Mengelola data binaan, pendidikan, program, fasilitas, dan dokumen sesuai penugasan.',
];
?>
<section class="panel"><h2><?=h($title)?></h2><?php if($page==='roles'):?><p>Aturan akses diterapkan di sisi server untuk setiap rute dan tindakan simpan/hapus.</p><div class="table-wrap"><table><thead><tr><th>Role</th><th>Hak Akses</th></tr></thead><tbody><?php foreach($roles as $role=>$description):?><tr><td><span class="tag"><?=h(role_label($role))?></span></td><td><?=h($description)?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><p>Pengaturan profil lembaga diubah melalui menu Profil / Identitas.</p><a class="btn btn-primary" href="index.php?page=profile&edit=1">Buka Pengaturan Profil</a><?php endif;?></section>
