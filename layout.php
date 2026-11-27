<?php
declare(strict_types=1);

function menu_groups(): array
{
    return [
        'Kelembagaan'=>[
            'institution'=>'Dashboard Kelembagaan','profile'=>'Profil / Identitas Lembaga','locations'=>'Lokasi Lembaga',
            'gallery'=>'Galeri Foto','legal'=>'Dokumen Perizinan','history'=>'Sejarah Yayasan',
            'vision'=>'Visi dan Misi','structure'=>'Struktur Organisasi','work-programs'=>'Program Kerja',
        ],
        'Sarana & Prasarana'=>[
            'facilities'=>'Tanah, Bangunan, Ruangan, Fasilitas & Inventaris','facility-photos'=>'Foto Sarana dan Prasarana',
        ],
        'Data Anak Asuh'=>[
            'children-dashboard'=>'Dashboard Anak Asuh','children'=>'Data Anak Asuh','parents'=>'Data Orang Tua/Wali',
            'education'=>'Data Pendidikan','child-documents'=>'Data Dokumen','child-assistance'=>'Riwayat Bantuan Anak Asuh','children-report'=>'Laporan Anak Asuh',
        ],
        'Data Lansia'=>[
            'elderly-dashboard'=>'Dashboard Lansia','elderly'=>'Data Lansia','families'=>'Data Keluarga',
            'health'=>'Kesehatan/Kebutuhan Layanan','elderly-assistance'=>'Riwayat Bantuan Lansia','elderly-report'=>'Laporan Lansia',
        ],
        'Program dan Kegiatan'=>[
            'programs'=>'Daftar Program','program-rantang'=>'Rantang Kasih Lansia','program-anak'=>'Program Anak Asuh',
            'program-social'=>'Kegiatan Sosial','program-education'=>'Kegiatan Pendidikan','program-gallery'=>'Dokumentasi Kegiatan','program-report'=>'Laporan Kegiatan',
        ],
        'Pendidikan'=>[
            'madrasah'=>'Data Madrasah','teachers'=>'Data Guru','students'=>'Data Santri','classes'=>'Data Kelas',
            'school_years'=>'Tahun Ajaran','subjects'=>'Mata Pelajaran','schedules'=>'Jadwal Pelajaran',
            'attendance'=>'Absensi','grades'=>'Penilaian','reports'=>'Rapor',
        ],
        'Akreditasi dan Legalitas'=>[
            'accreditations'=>'Akreditasi NPSAA / Data Akreditasi','legal'=>'Dokumen Legalitas','legal-akta'=>'Akta Yayasan',
            'legal-sk'=>'SK Kemenkumham','legal-npwp'=>'NPWP','legal-izin'=>'Izin Operasional','legal-support'=>'Dokumen Pendukung','legal-expiry'=>'Masa Berlaku Dokumen',
        ],
        'Laporan'=>[
            'children-report'=>'Laporan Anak Asuh','elderly-report'=>'Laporan Lansia','program-report'=>'Laporan Program',
            'finance'=>'Laporan Keuangan','institution-report'=>'Laporan Kelembagaan','facility-report'=>'Laporan Sarana dan Prasarana',
        ],
        'Pengaturan'=>[
            'settings-profile'=>'Pengaturan Profil','users'=>'Manajemen Pengguna','roles'=>'Manajemen Role',
            'settings-website'=>'Pengaturan Website','backup'=>'Backup Database','activity'=>'Log Aktivitas','change-password'=>'Ubah Password',
        ],
    ];
}

function render_header(string $title, string $active, array $user): void
{
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.h($title).' | Yayasan Wihdatul Muslimat</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/app.css">';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head><body>';
    echo '<aside class="sidebar" id="sidebar"><a class="brand" href="index.php"><img src="assets/img/logo-wihdatul-muslimat.jpeg" alt="Logo Yayasan Wihdatul Muslimat"><span><b>YAYASAN WIHDATUL</b><small>MUSLIMAT · JAKARTA PUSAT</small></span></a>';
    echo '<nav class="side-nav"><a class="nav-link '.($active==='dashboard'?'active':'').'" href="index.php?page=dashboard"><span>▦</span>Dashboard</a>';
    foreach (menu_groups() as $group=>$items) {
        $open = in_array($active,array_keys($items),true) ? ' open' : '';
        echo '<details class="nav-group"'.$open.'><summary>'.h($group).'<span class="chevron">⌄</span></summary><div class="submenu">';
        foreach ($items as $key=>$label) {
            echo '<a class="nav-link '.($active===$key?'active':'').'" href="index.php?page='.rawurlencode($key).'">'.h($label).'</a>';
        }
        echo '</div></details>';
    }
    echo '</nav><div class="sidebar-foot">Sistem Administrasi Yayasan<br><small>Versi 1.0</small></div></aside>';
    echo '<main class="main"><header class="topbar"><button class="mobile-menu" type="button" aria-label="Buka navigasi" onclick="document.getElementById(\'sidebar\').classList.toggle(\'show\')">☰</button><div class="heading"><span class="eyebrow">Yayasan Wihdatul Muslimat</span><h1>'.h($title).'</h1></div><div class="top-right"><form class="global-search" action="index.php" method="get"><input type="hidden" name="page" value="'.h($active).'"><span>⌕</span><input name="q" value="'.h($_GET['q']??'').'" placeholder="Cari data..." aria-label="Cari data"></form><div class="user-menu"><span class="avatar">'.h(mb_substr($user['name'],0,1)).'</span><span class="user-name">'.h($user['name']).'<small>'.h(role_label($user['role'])).'</small></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="'.h(csrf_token()).'"><button class="btn btn-soft" type="submit">Keluar</button></form></div></div></header><section class="page-content">';
}

function render_footer(): void
{
    echo '</section><footer>Yayasan Wihdatul Muslimat · Jakarta Pusat</footer></main><script src="assets/js/app.js"></script></body></html>';
}
