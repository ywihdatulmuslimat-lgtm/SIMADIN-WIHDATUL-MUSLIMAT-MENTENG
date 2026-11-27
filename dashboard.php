<?php
declare(strict_types=1);
$pdo=db();
$counts=[
    'Anak Asuh'=>(int)$pdo->query("SELECT COUNT(*) FROM children WHERE status='Aktif'")->fetchColumn(),
    'Lansia Binaan'=>(int)$pdo->query("SELECT COUNT(*) FROM elderly WHERE status='Aktif'")->fetchColumn(),
    'Tenaga Pendidik'=>(int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Aktif'")->fetchColumn(),
    'Program Kegiatan'=>(int)$pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn(),
    'Dokumen Legal'=>(int)$pdo->query("SELECT COUNT(*) FROM legal_documents")->fetchColumn(),
];
$profile=$pdo->query('SELECT name,city FROM institution_profile WHERE id=1')->fetch() ?: ['name'=>'','city'=>''];
?>
<section class="cards">
<?php foreach($counts as $label=>$value):?><article class="card"><span class="card-label"><?=h($label)?></span><strong class="card-value"><?=number_format($value)?></strong><span class="card-hint">Data terdaftar pada sistem</span></article><?php endforeach;?>
</section>
<section class="panel"><div class="panel-head"><div><h2>Ringkasan Yayasan</h2><span class="card-label"><?=h($profile['name'])?> · <?=h($profile['city'])?></span></div><a class="btn btn-soft" href="index.php?page=institution">Dashboard Kelembagaan</a></div><div class="columns"><div><canvas id="overviewChart" height="130" aria-label="Grafik statistik data yayasan"></canvas></div><div><h3>Aktivitas Terbaru</h3><?php $logs=$pdo->query('SELECT l.action,l.entity,l.details,l.created_at,u.name FROM activity_log l LEFT JOIN app_users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 6')->fetchAll();if(!$logs):?><div class="empty">Belum ada aktivitas tercatat.</div><?php else:foreach($logs as $log):?><div class="activity-row"><b><?=h($log['action'])?></b> · <?=h($log['entity'])?><small><?=h($log['name']??'Pengguna tidak diketahui')?> · <?=h(date('d-m-Y H:i',strtotime($log['created_at'])))?></small></div><?php endforeach;endif;?></div></div></section>
<section class="columns"><article class="panel"><div class="panel-head"><h2>Kalender Kegiatan</h2><a class="btn btn-soft" href="index.php?page=programs">Kelola Program</a></div><?php
$now=new DateTimeImmutable('today');$start=$now->modify('first day of this month');$offset=(int)$start->format('w');$days=(int)$start->format('t');
$events=$pdo->prepare("SELECT name,start_date FROM programs WHERE start_date BETWEEN :from AND :to ORDER BY start_date");$events->execute(['from'=>$start->format('Y-m-01'),'to'=>$start->format('Y-m-t')]);$eventDays=[];foreach($events->fetchAll() as $event)$eventDays[date('j',strtotime($event['start_date']))][]=$event['name'];
?><div class="calendar"><?php foreach(['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $day):?><div class="dayname"><?=h($day)?></div><?php endforeach;for($i=0;$i<$offset;$i++):?><div></div><?php endfor;for($day=1;$day<=$days;$day++):?><div class="<?=!empty($eventDays[$day])?'event-day ':''?><?=((int)$now->format('j')===$day)?'today':''?>" title="<?=h(implode(', ',$eventDays[$day]??[]))?>"><?=$day?></div><?php endfor;?></div></article><article class="panel"><h2>Agenda Terdekat</h2><?php $upcoming=$pdo->query("SELECT name,start_date,location,status FROM programs WHERE start_date>=CURDATE() ORDER BY start_date LIMIT 5")->fetchAll();if(!$upcoming):?><div class="empty">Belum ada kegiatan terjadwal.</div><?php else:foreach($upcoming as $item):?><div class="activity-row"><b><?=h($item['name'])?></b><small><?=h(date('d-m-Y',strtotime($item['start_date'])))?> · <?=h($item['location'])?> · <?=h($item['status'])?></small></div><?php endforeach;endif;?></article></section>
<script>if(typeof Chart!=='undefined'){new Chart(document.getElementById('overviewChart'),{type:'bar',data:{labels:<?=json_encode(array_keys($counts),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>,datasets:[{label:'Jumlah',data:<?=json_encode(array_values($counts))?>,backgroundColor:'#39a56a',borderRadius:7}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});}</script>
