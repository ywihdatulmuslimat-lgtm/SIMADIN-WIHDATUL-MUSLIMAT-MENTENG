<?php
declare(strict_types=1);
$rows=db()->query('SELECT a.*,u.name FROM activity_log a LEFT JOIN app_users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 200')->fetchAll();
?>
<section class="panel"><h2>Log Aktivitas</h2><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Modul</th><th>Detail</th><th>Alamat IP</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty">Belum ada log aktivitas.</td></tr><?php else:foreach($rows as $row):?><tr><td><?=h(date('d-m-Y H:i:s',strtotime($row['created_at'])))?></td><td><?=h($row['name']??'Tidak diketahui')?></td><td><?=h($row['action'])?></td><td><?=h($row['entity'])?></td><td><?=h($row['details'])?></td><td><?=h($row['ip_address'])?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
