<?php
declare(strict_types=1);

$month = filter_var($_GET['bulan'] ?? date('n'), FILTER_VALIDATE_INT);
$year = filter_var($_GET['tahun'] ?? date('Y'), FILTER_VALIDATE_INT);
if (!$month || $month < 1 || $month > 12 || !$year || $year < 2000 || $year > 2100) {
    http_response_code(400);
    exit('Periode kalender tidak valid.');
}
$first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$last = $first->modify('last day of this month');
$start = $first->modify('monday this week');
$end = $last->modify('sunday this week');
$query = db()->prepare(
    'SELECT id, nama_kegiatan, tanggal, waktu, tempat, status FROM kegiatan
     WHERE tanggal BETWEEN :start_date AND :end_date
     ORDER BY tanggal, waktu, nama_kegiatan'
);
$query->execute(['start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d')]);
$byDate = [];
foreach ($query->fetchAll() as $event) {
    $byDate[$event['tanggal']][] = $event;
}
$previous = $first->modify('-1 month');
$next = $first->modify('+1 month');
$monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$canWrite = in_array($_SESSION['user']['role'] ?? '', ['Super Admin', 'Operator'], true);
?>
<section class="panel">
    <div class="panel-heading"><div><h2><?= e($monthNames[$month] . ' ' . $year) ?></h2><p class="subtitle">Agenda berasal dari data Kegiatan Madrasah.</p></div><?php if ($canWrite): ?><a class="btn btn-primary" href="index.php?page=kegiatan&action=form">+ Tambah Kegiatan</a><?php endif; ?></div>
    <div class="calendar calendar-month">
        <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day): ?><div class="dayname"><?= e($day) ?></div><?php endforeach; ?>
        <?php for ($day = $start; $day <= $end; $day = $day->modify('+1 day')): $key = $day->format('Y-m-d'); $inMonth = $day->format('n') === $first->format('n'); ?>
            <div class="day <?= $day->format('Y-m-d') === date('Y-m-d') ? 'today' : '' ?> <?= $inMonth ? '' : 'outside-month' ?>">
                <span><?= $day->format('j') ?></span>
                <?php foreach ($byDate[$key] ?? [] as $event): ?><a class="calendar-event" href="index.php?page=kegiatan&view=<?= (int)$event['id'] ?>"><?= e($event['nama_kegiatan']) ?></a><?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
    <div class="calendar-nav"><a class="btn" href="index.php?page=kalender&bulan=<?= $previous->format('n') ?>&tahun=<?= $previous->format('Y') ?>">Bulan Sebelumnya</a><a class="btn" href="index.php?page=kalender&bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>">Hari Ini</a><a class="btn" href="index.php?page=kalender&bulan=<?= $next->format('n') ?>&tahun=<?= $next->format('Y') ?>">Bulan Berikutnya</a></div>
</section>
