<?php
declare(strict_types=1);
$kind=$page==='children-dashboard'?'children':'elderly';
$label=$kind==='children'?'Anak Asuh':'Lansia';
$table=$kind;
$total=(int)db()->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
$active=(int)db()->query("SELECT COUNT(*) FROM `$table` WHERE status='Aktif'")->fetchColumn();
$gender=db()->query("SELECT gender,COUNT(*) AS total FROM `$table` GROUP BY gender")->fetchAll();
$recent=db()->query("SELECT id,full_name,status,birth_date FROM `$table` ORDER BY id DESC LIMIT 8")->fetchAll();
?>
<section class="cards"><article class="card"><span class="card-label">Total <?=h($label)?></span><strong class="card-value"><?=$total?></strong><span class="card-hint">Data terdaftar</span></article><article class="card"><span class="card-label">Status Aktif</span><strong class="card-value"><?=$active?></strong><span class="card-hint">Binaan aktif</span></article><?php foreach($gender as $row):?><article class="card"><span class="card-label"><?=h($row['gender'])?></span><strong class="card-value"><?=(int)$row['total']?></strong><span class="card-hint">Berdasarkan data yang diinput</span></article><?php endforeach;?></section>
<section class="panel"><div class="panel-head"><h2>Data Terbaru: <?=h($label)?></h2><a class="btn btn-primary" href="index.php?page=<?=$kind?>">Kelola Data</a></div><?php if(!$recent):?><div class="empty">Belum ada data <?=h(strtolower($label))?>.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Tanggal Lahir</th><th>Usia</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php foreach($recent as $person):?><tr><td><?=h($person['full_name'])?></td><td><?=$person['birth_date']?h(date('d-m-Y',strtotime($person['birth_date']))):'Belum diisi'?></td><td><?=h((string)(age_from_date($person['birth_date'])??'—'))?></td><td><span class="tag"><?=h($person['status'])?></span></td><td><a class="btn btn-soft" href="index.php?page=<?=$kind?>&action=view&id=<?=(int)$person['id']?>">Detail</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
