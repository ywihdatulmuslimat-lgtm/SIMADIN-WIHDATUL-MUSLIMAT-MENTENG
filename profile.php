<?php
declare(strict_types=1);
$pdo=db();
$profile=$pdo->query('SELECT * FROM institution_profile WHERE id=1')->fetch();
$fields=[
    'name'=>['Nama Lembaga','text',true],'institution_type'=>['Jenis Lembaga','text',false],
    'address'=>['Alamat','textarea',false],'province'=>['Provinsi','text',false],
    'city'=>['Kota/Kabupaten','text',false],'district'=>['Kecamatan','text',false],
    'village'=>['Kelurahan','text',false],'postal_code'=>['Kode Pos','text',false],
    'email'=>['Email','email',false],'phone'=>['Telepon','tel',false],
    'website'=>['Website','url',false],'founded_year'=>['Tahun Berdiri','number',false],
    'leader_name'=>['Nama Pimpinan','text',false],'description'=>['Deskripsi','textarea',false],
    'history'=>['Sejarah Yayasan','textarea',false],'vision'=>['Visi','textarea',false],
    'mission'=>['Misi','textarea',false],'organization_structure'=>['Struktur Organisasi','textarea',false],
];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_roles(['super_admin','admin_operator']);
    verify_csrf();
    $values=[];
    foreach($fields as $key=>$meta) {
        $value=trim((string)($_POST[$key]??''));
        if($meta[2] && $value==='') {
            $_SESSION['flash']=['type'=>'error','text'=>'Nama lembaga wajib diisi.'];
            header('Location: index.php?page=profile&edit=1');exit;
        }
        if($key==='email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash']=['type'=>'error','text'=>'Format email tidak valid.'];
            header('Location: index.php?page=profile&edit=1');exit;
        }
        $values[$key]=$value===''?($key==='founded_year'?null:''):$value;
    }
    $sets=[];foreach(array_keys($fields) as $key)$sets[]="`$key`=:$key";
    $stmt=$pdo->prepare('UPDATE institution_profile SET '.implode(',',$sets).' WHERE id=1');
    $stmt->execute($values);
    log_activity('update','institution_profile','Profil yayasan diperbarui');
    $_SESSION['flash']=['type'=>'success','text'=>'Profil lembaga berhasil disimpan.'];
    header('Location: index.php?page=profile');exit;
}
$edit=!empty($_GET['edit']) && in_array($user['role'],['super_admin','admin_operator'],true);
$flash=flash_message();
?>
<?php if($flash):?><div class="alert alert-<?=h($flash['type'])?>"><?=h($flash['text'])?></div><?php endif;?><?php if($edit):?><section class="panel"><div class="panel-head"><h2>Edit Profil Lembaga</h2><a class="btn" href="index.php?page=profile">Batal</a></div><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><div class="form-grid"><?php foreach($fields as $key=>$meta):?><div class="field <?=$meta[1]==='textarea'?'full':''?>"><label for="<?=h($key)?>"><?=h($meta[0])?><?=$meta[2]?' *':''?></label><?php if($meta[1]==='textarea'):?><textarea class="form-control" id="<?=h($key)?>" name="<?=h($key)?>"><?=h($profile[$key]??'')?></textarea><?php else:?><input class="form-control" id="<?=h($key)?>" name="<?=h($key)?>" type="<?=h($meta[1])?>" value="<?=h($profile[$key]??'')?>" <?=$meta[2]?'required':''?><?php endif;?></div><?php endforeach;?></div><button class="btn btn-primary" style="margin-top:14px">Simpan Profil</button></form></section><?php else:?><section class="panel"><div class="panel-head"><h2>Profil dan Identitas Lembaga</h2><?php if(in_array($user['role'],['super_admin','admin_operator'],true)):?><a class="btn btn-primary" href="index.php?page=profile&edit=1">Edit Profil</a><?php endif;?></div><dl class="detail-list"><?php foreach($fields as $key=>$meta):?><dt><?=h($meta[0])?></dt><dd><?=nl2br(h($profile[$key]??''))?:'Belum diisi'?></dd><?php endforeach;?></dl></section><?php endif;?>
