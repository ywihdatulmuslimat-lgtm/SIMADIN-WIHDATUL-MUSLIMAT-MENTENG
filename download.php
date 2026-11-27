<?php
declare(strict_types=1);
require __DIR__.'/config/database.php';require __DIR__.'/includes/auth.php';require __DIR__.'/includes/modules.php';
$user=require_login();
$table=(string)($_GET['table']??'');$id=(int)($_GET['id']??0);$field=(string)($_GET['field']??'');
$allowed=[
 'children'=>['photo_path'],'elderly'=>['photo_path'],'child_documents'=>['file_path'],'elderly_documents'=>['file_path'],
 'programs'=>['documentation_path'],'facilities'=>['photo_path','document_path'],'gallery_photos'=>['photo_path'],
 'legal_documents'=>['file_path'],'accreditations'=>['file_path'],
];
if(!isset($allowed[$table])||!in_array($field,$allowed[$table],true)){http_response_code(404);exit('File tidak ditemukan.');}
if(in_array($user['role'],['ketua_yayasan','petugas'],true)&&in_array($table,['children','elderly','child_documents','elderly_documents'],true)) {
    http_response_code(403);exit('Akses file dibatasi untuk melindungi data pribadi.');
}
$stmt=db()->prepare("SELECT `$field` AS file_path FROM `$table` WHERE id=:id");
$stmt->execute(['id'=>$id]);$path=$stmt->fetchColumn();
$root=realpath(__DIR__.'/storage/uploads');$file=$path?realpath(__DIR__.'/'.$path):false;
if(!$root||!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit('File tidak ditemukan.');}
$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file)?:'application/octet-stream';
if(!in_array($mime,['image/jpeg','image/png','application/pdf'],true)){http_response_code(415);exit('Jenis file tidak didukung.');}
header('X-Content-Type-Options: nosniff');header('Content-Type:'.$mime);
header('Content-Disposition:'.($mime==='application/pdf'?'inline':'inline').'; filename="'.basename($file).'"');
header('Content-Length:'.(string)filesize($file));readfile($file);
