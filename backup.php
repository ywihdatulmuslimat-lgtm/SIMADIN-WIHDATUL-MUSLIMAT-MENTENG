<?php
declare(strict_types=1);
?>
<section class="panel"><h2>Backup Database</h2><p>Unduh salinan SQL database yang sedang terhubung. Simpan file cadangan di lokasi privat dan lakukan secara berkala.</p><form method="post" action="backup.php"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><button class="btn btn-primary">Unduh Backup SQL</button></form></section>
