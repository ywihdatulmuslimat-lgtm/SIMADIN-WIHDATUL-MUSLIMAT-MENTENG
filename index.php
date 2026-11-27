<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/config/database.php';
require __DIR__.'/includes/auth.php';
require __DIR__.'/includes/helpers.php';
require __DIR__.'/includes/modules.php';
require __DIR__.'/includes/layout.php';

$user=require_login();
$page=preg_replace('/[^a-z0-9-]/','',(string)($_GET['page']??'dashboard'));
$definitions=module_definitions();
$titles=[
    'dashboard'=>'Dashboard',    'institution'=>'Dashboard Kelembagaan','profile'=>'Profil / Identitas Lembaga',
    'history'=>'Sejarah Yayasan','vision'=>'Visi dan Misi','structure'=>'Struktur Organisasi','work-programs'=>'Program Kerja',
    'facility-photos'=>'Foto Sarana dan Prasarana','children-dashboard'=>'Dashboard Anak Asuh','parents'=>'Data Orang Tua/Wali',
    'education'=>'Data Pendidikan','child-documents'=>'Data Dokumen Anak Asuh','child-assistance'=>'Riwayat Bantuan Anak Asuh',
    'children-report'=>'Laporan Anak Asuh','elderly-dashboard'=>'Dashboard Lansia','families'=>'Data Keluarga Lansia',
    'health'=>'Kesehatan/Kebutuhan Layanan Lansia','elderly-assistance'=>'Riwayat Bantuan Lansia','elderly-report'=>'Laporan Lansia',
    'program-rantang'=>'Rantang Kasih Lansia','program-anak'=>'Program Anak Asuh','program-social'=>'Kegiatan Sosial',
    'program-education'=>'Kegiatan Pendidikan','program-gallery'=>'Dokumentasi Kegiatan','program-report'=>'Laporan Program',
    'legal-akta'=>'Akta Yayasan','legal-sk'=>'SK Kemenkumham','legal-npwp'=>'NPWP','legal-izin'=>'Izin Operasional',
    'legal-support'=>'Dokumen Pendukung','legal-expiry'=>'Masa Berlaku Dokumen','institution-report'=>'Laporan Kelembagaan',
    'facility-report'=>'Laporan Sarana dan Prasarana','roles'=>'Manajemen Role','settings-profile'=>'Pengaturan Profil',
    'settings-website'=>'Pengaturan Website','backup'=>'Backup Database','activity'=>'Log Aktivitas',
];
$modulePages=[
    'children'=>'children','elderly'=>'elderly','locations'=>'locations','gallery'=>'gallery','legal'=>'legal',
    'accreditations'=>'accreditations','programs'=>'programs','facilities'=>'facilities','school_years'=>'school_years',
    'madrasah'=>'madrasah','teachers'=>'teachers','students'=>'students','classes'=>'classes','subjects'=>'subjects',
    'schedules'=>'schedules','attendance'=>'attendance','grades'=>'grades','reports'=>'reports','finance'=>'finance',
    'users'=>'users','child-assistance'=>'assistance','elderly-assistance'=>'assistance',
    'parents'=>'children','families'=>'elderly','health'=>'elderly','education'=>'students',
    'child-documents'=>'child_documents','elderly-documents'=>'elderly_documents',
    'program-rantang'=>'programs',
    'program-anak'=>'programs','program-social'=>'programs','program-education'=>'programs',
    'program-gallery'=>'programs','legal-akta'=>'legal','legal-sk'=>'legal','legal-npwp'=>'legal',
    'legal-izin'=>'legal','legal-support'=>'legal','legal-expiry'=>'legal',
];
if (isset($modulePages[$page])) $title=$titles[$page]??$definitions[$modulePages[$page]]['title'];
else $title=$titles[$page]??'Dashboard';

$allRoles=['super_admin','ketua_yayasan','admin_operator','petugas'];
$adminRoles=['super_admin','admin_operator'];
$roleRules=[
    'dashboard'=>$allRoles,'institution'=>$allRoles,'profile'=>$allRoles,'history'=>$allRoles,'vision'=>$allRoles,
    'structure'=>$allRoles,'work-programs'=>$allRoles,'facility-photos'=>$allRoles,
    'children-dashboard'=>['super_admin','admin_operator','petugas'],'elderly-dashboard'=>['super_admin','admin_operator','petugas'],
    'parents'=>['super_admin','admin_operator','petugas'],'education'=>['super_admin','admin_operator','petugas'],
    'child-documents'=>['super_admin','admin_operator','petugas'],'families'=>['super_admin','admin_operator','petugas'],
    'health'=>['super_admin','admin_operator','petugas'],'program-rantang'=>$allRoles,'program-anak'=>$allRoles,
    'program-social'=>$allRoles,'program-education'=>$allRoles,'program-gallery'=>$allRoles,
    'children-report'=>$allRoles,'elderly-report'=>$allRoles,'program-report'=>$allRoles,
    'institution-report'=>$allRoles,'facility-report'=>$allRoles,'legal-akta'=>$allRoles,'legal-sk'=>$allRoles,
    'legal-npwp'=>$allRoles,'legal-izin'=>$allRoles,'legal-support'=>$allRoles,'legal-expiry'=>$allRoles,
    'roles'=>['super_admin'],'settings-profile'=>$adminRoles,'settings-website'=>$adminRoles,
    'backup'=>$adminRoles,'activity'=>$adminRoles,
];
$routeModule=$modulePages[$page]??null;
$chairReadModules=['children','elderly','assistance','programs','facilities','locations','gallery','legal','accreditations','school_years','madrasah','teachers','classes','students','subjects','schedules','attendance','grades','reports','financial_records'];
$chairCanRead=$user['role']==='ketua_yayasan' && $routeModule && in_array($routeModule,$chairReadModules,true);
if ($routeModule && !in_array($user['role'],$definitions[$routeModule]['roles'],true) && !$chairCanRead) {
    http_response_code(403); exit('Anda tidak memiliki hak akses untuk modul ini.');
}
if (isset($roleRules[$page]) && !in_array($user['role'],$roleRules[$page],true)) {
    http_response_code(403); exit('Anda tidak memiliki hak akses untuk halaman ini.');
}

render_header($title,$page,$user);
if ($page==='dashboard') {
    require __DIR__.'/modules/dashboard.php';
} elseif ($page==='institution') {
    require __DIR__.'/modules/institution.php';
} elseif ($page==='profile' || $page==='settings-profile') {
    require __DIR__.'/modules/profile.php';
} elseif ($routeModule) {
    $moduleKey=$routeModule;
    require __DIR__.'/modules/crud.php';
} elseif (str_ends_with($page,'-dashboard')) {
    require __DIR__.'/modules/beneficiary-dashboard.php';
} elseif (str_contains($page,'report') || $page==='finance') {
    require __DIR__.'/modules/reports.php';
} elseif ($page==='activity') {
    require __DIR__.'/modules/activity.php';
} elseif ($page==='backup') {
    require __DIR__.'/modules/backup.php';
} elseif ($page==='roles' || $page==='settings-website') {
    require __DIR__.'/modules/settings.php';
} else {
    require __DIR__.'/modules/section.php';
}
render_footer();
