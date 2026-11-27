<?php
declare(strict_types=1);
$pdo=db();
$p=$pdo->query('SELECT * FROM institution_profile WHERE id=1')->fetch();
$gallery=(int)$pdo->query('SELECT COUNT(*) FROM gallery_photos')->fetchColumn();
$docs=(int)$pdo->query('SELECT COUNT(*) FROM legal_documents')->fetchColumn();
$total=16;
$checks=[
    'Nama lembaga'=>$p['name']??'','Jenis lembaga'=>$p['institution_type']??'','Alamat'=>$p['address']??'',
    'Provinsi'=>$p['province']??'','Kota/Kabupaten'=>$p['city']??'','Kecamatan'=>$p['district']??'',
    'Kelurahan'=>$p['village']??'','Kode pos'=>$p['postal_code']??'','Email'=>$p['email']??'',
    'Telepon'=>$p['phone']??'','Website'=>$p['website']??'','Tahun berdiri'=>$p['founded_year']??'',
    'Nama pimpinan'=>$p['leader_name']??'','Deskripsi'=>$p['description']??'',
    'Sejarah'=>$p['history']??'','Visi dan misi'=>trim(($p['vision']??'').($p['mission']??'')),
];
$filled=count(array_filter($checks,static fn($value)=>trim((string)$value)!==''));$percent=(int)round($filled/$total*100);
?>
<section class="cards"><article class="card"><span class="card-label">Nama Lembaga</span><strong class="card-value"><?=h($p['name']??'Belum diisi')?></strong><span class="card-hint">Identitas yayasan</span></article><article class="card"><span class="card-label">Kota/Kabupaten</span><strong class="card-value"><?=h($p['city']?:'Belum diisi')?></strong><span class="card-hint">Lokasi lembaga</span></article><article class="card"><span class="card-label">Galeri Foto</span><strong class="card-value"><?=$gallery?></strong><span class="card-hint">Foto tersimpan</span></article><article class="card"><span class="card-label">Dokumen Legal</span><strong class="card-value"><?=$docs?></strong><span class="card-hint">Dokumen yang tercatat</span></article></section>
<section class="panel"><div class="panel-head"><div><h2>Status Kelengkapan Data</h2><span class="card-label"><?=$filled?> dari <?=$total?> unsur profil terisi</span></div><strong><?=$percent?>%</strong></div><div class="completeness"><div class="progress"><span style="width:<?=$percent?>%"></span></div></div>
<?php if($percent<100):?><div class="notice"><b>Data belum diisi:</b> <?=h(implode(', ',array_keys(array_filter($checks,static fn($value)=>trim((string)$value)===''))))?></div><?php else:?><div class="alert alert-success">Profil kelembagaan lengkap.</div><?php endif;?>
<div class="quick-actions"><a class="btn btn-primary" href="index.php?page=profile&edit=1">Isi Identitas</a><a class="btn btn-soft" href="index.php?page=locations">Isi Lokasi</a><a class="btn btn-soft" href="index.php?page=gallery">Upload Foto</a><a class="btn btn-soft" href="index.php?page=legal">Upload Dokumen</a></div></section>
<section class="panel"><h2>Identitas Lembaga</h2><dl class="detail-list"><?php foreach(['institution_type'=>'Jenis Lembaga','address'=>'Alamat','province'=>'Provinsi','city'=>'Kota/Kabupaten','district'=>'Kecamatan','village'=>'Kelurahan','postal_code'=>'Kode Pos','email'=>'Email','phone'=>'Telepon','website'=>'Website','founded_year'=>'Tahun Berdiri','leader_name'=>'Pimpinan','description'=>'Deskripsi'] as $field=>$label):?><dt><?=h($label)?></dt><dd><?=h($p[$field]?:'Belum diisi')?></dd><?php endforeach;?></dl></section>
