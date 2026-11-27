<?php
declare(strict_types=1);

$config=$definitions[$moduleKey];
$pdo=db();
$table=$config['table'];
$allowedTables=['children','elderly','child_documents','elderly_documents','assistance_records','programs','facilities','institution_locations','gallery_photos','legal_documents','accreditations','school_years','madrasah','teachers','classes','students','subjects','lesson_schedules','attendance','grades','report_cards','financial_records','app_users'];
if(!in_array($table,$allowedTables,true)){http_response_code(400);exit('Modul tidak valid.');}
$readOnly=$user['role']==='ketua_yayasan';
$categoryForPage=[
    'program-rantang'=>'Rantang Kasih Lansia','program-anak'=>'Anak Asuh',
    'program-social'=>'Sosial','program-education'=>'Pendidikan',
];
$recipientForPage=['child-assistance'=>'anak_asuh','elderly-assistance'=>'lansia'];
$legalForPage=['legal-akta'=>'Akta Yayasan','legal-sk'=>'SK Kemenkumham','legal-npwp'=>'NPWP','legal-izin'=>'Izin Operasional','legal-support'=>'Dokumen Pendukung'];
$where=[];$params=[];
if(isset($categoryForPage[$page])){$where[]='category=:page_category';$params['page_category']=$categoryForPage[$page];}
if(isset($legalForPage[$page])){$where[]='category=:page_category';$params['page_category']=$legalForPage[$page];}
if(isset($recipientForPage[$page])){$where[]='recipient_type=:recipient_type';$params['recipient_type']=$recipientForPage[$page];}
if($page==='legal-expiry')$where[]='valid_until IS NOT NULL';
$search=trim((string)($_GET['q']??''));
if($search!=='') {
    $parts=[];
    foreach($config['search'] as $i=>$column){$parts[]="`$column` LIKE :search$i";$params["search$i"]='%'.$search.'%';}
    if($parts)$where[]='('.implode(' OR ',$parts).')';
}
$filter=trim((string)($_GET['status']??''));
if($filter!=='' && in_array('status',array_column($config['fields'],0),true)){$where[]='status=:status';$params['status']=$filter;}
$dateColumns=[
    'children'=>'created_at','elderly'=>'created_at','assistance_records'=>'assistance_date',
    'programs'=>'start_date','financial_records'=>'transaction_date','legal_documents'=>'issued_at',
    'accreditations'=>'issued_at','attendance'=>'attendance_date','gallery_photos'=>'taken_at',
];
$dateColumn=$dateColumns[$table]??null;
$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));
if($dateColumn && $from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]="`$dateColumn`>=:date_from";$params['date_from']=$from;}
if($dateColumn && $to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]="`$dateColumn`<=:date_to";$params['date_to']=$to;}
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';

if($_SERVER['REQUEST_METHOD']==='POST') {
    if($readOnly){http_response_code(403);exit('Akses hanya untuk melihat data.');}
    require_roles($config['roles']);
    verify_csrf();
    $id=(int)($_POST['id']??0);
    try {
        if(isset($_POST['delete_id'])) {
            $deleteId=(int)$_POST['delete_id'];
            $stmt=$pdo->prepare("DELETE FROM `$table` WHERE id=:id");
            $stmt->execute(['id'=>$deleteId]);
            log_activity('delete',$table,'ID '.$deleteId);
            $_SESSION['flash']=['type'=>'success','text'=>'Data berhasil dihapus.'];
            header('Location: index.php?page='.rawurlencode($page));exit;
        }
        $values=[];$errors=[];
        foreach($config['fields'] as [$key,$label,$type,$required]) {
            if($type==='file') {
                if(!empty($_FILES[$key]['name'])) {
                    $isPhoto=$key==='photo_path';
                    $path=store_upload($_FILES[$key],$isPhoto);
                    $values[$key]=$path;
                    if(in_array($table,['child_documents','elderly_documents'],true))$values['original_name']=basename((string)$_FILES[$key]['name']);
                } elseif($required && !$id) $errors[]="$label wajib diunggah.";
                continue;
            }
            if($table==='app_users' && $key==='initial_password')continue;
            $value=trim((string)($_POST[$key]??''));
            if($required && $value==='')$errors[]="$label wajib diisi.";
            if(in_array($key,['nik','family_card_number'],true) && $value!=='' && !preg_match('/^\d{16}$/',$value))$errors[]="$label harus terdiri dari 16 digit.";
            if($type==='email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL))$errors[]="Format $label tidak valid.";
            if($type==='number' && $value!=='' && !is_numeric($value))$errors[]="$label harus berupa angka.";
            if($key==='birth_date' && $value!=='' && $value>date('Y-m-d'))$errors[]='Tanggal lahir tidak boleh di masa depan.';
            if($type==='select' && $value!=='') {
                $options=[];
                foreach($config['fields'] as $candidate)if(($candidate[0]??'')===$key){$options=$candidate[4]??[];break;}
                if(!in_array($value,$options,true))$errors[]="Pilihan $label tidak valid.";
            }
            $values[$key]=$value===''?(in_array($type,['date','number'],true)||$type==='email'?null:''):$value;
        }
        if($table==='assistance_records') {
            $recipient=(string)($values['recipient_type']??'');
            if($recipient==='anak_asuh' && empty($values['child_id']))$errors[]='Pilih ID anak asuh sebagai penerima bantuan.';
            if($recipient==='lansia' && empty($values['elderly_id']))$errors[]='Pilih ID lansia sebagai penerima bantuan.';
        }
        if($table==='app_users' && !$id) {
            $initial=(string)($_POST['initial_password']??'');
            if(strlen($initial)<12)$errors[]='Password awal harus minimal 12 karakter.';
            else{$values['password_hash']=password_hash($initial,PASSWORD_DEFAULT);$values['must_change_password']=1;}
        } elseif($table==='app_users') {
            unset($values['initial_password']);
        }
        if($errors) {
            $_SESSION['flash']=['type'=>'error','text'=>implode(' ',$errors)];
            header('Location: index.php?page='.rawurlencode($page).'&action='.($id?'edit&id='.$id:'create'));exit;
        }
        if($table==='programs' || $table==='financial_records') {
            if(!$id)$values['created_by']=(int)$user['id'];
        }
        if($id) {
            $sets=[];foreach(array_keys($values) as $key)$sets[]="`$key`=:$key";
            if(!$sets)throw new RuntimeException('Tidak ada perubahan untuk disimpan.');
            $values['id']=$id;
            $stmt=$pdo->prepare("UPDATE `$table` SET ".implode(',',$sets).' WHERE id=:id');
            $stmt->execute($values);$action='update';
        } else {
            $columns=array_keys($values);$placeholders=array_map(static fn($key)=>':'.$key,$columns);
            $stmt=$pdo->prepare("INSERT INTO `$table` (`".implode('`,`',$columns).'`) VALUES ('.implode(',',$placeholders).')');
            $stmt->execute($values);$action='create';
        }
        log_activity($action,$table,'Data '.$action.' berhasil');
        $_SESSION['flash']=['type'=>'success','text'=>'Data berhasil disimpan.'];
        header('Location: index.php?page='.rawurlencode($page));exit;
    } catch(PDOException $exception) {
        error_log('SIMADIN db operation: '.$exception->getMessage());
        $_SESSION['flash']=['type'=>'error','text'=>'Data tidak dapat disimpan. Periksa duplikasi atau relasi data, lalu coba lagi.'];
        header('Location: index.php?page='.rawurlencode($page).'&action='.($id?'edit&id='.$id:'create'));exit;
    } catch(RuntimeException $exception) {
        $_SESSION['flash']=['type'=>'error','text'=>$exception->getMessage()];
        header('Location: index.php?page='.rawurlencode($page).'&action='.($id?'edit&id='.$id:'create'));exit;
    }
}

$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
$limit=20;$currentPage=max(1,(int)($_GET['p']??1));
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM `$table`".$whereSql);$countStmt->execute($params);$total=(int)$countStmt->fetchColumn();
$pages=max(1,(int)ceil($total/$limit));$currentPage=min($currentPage,$pages);$offset=($currentPage-1)*$limit;
if(isset($_GET['export']) && $_GET['export']==='csv') {
    if(ob_get_level()>0)ob_clean();
    if(ob_get_level()>0)ob_clean();
    $stmt=$pdo->prepare("SELECT * FROM `$table`".$whereSql.' ORDER BY id DESC');$stmt->execute($params);
    $records=$stmt->fetchAll();header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="'.$moduleKey.'-yayasan.csv"');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    $columns=array_map(static fn($field)=>$field[0],array_filter($config['fields'],static fn($field)=>$field[2]!=='file' && $field[0]!=='initial_password'));
    fputcsv($out,$columns);
    foreach($records as $record){if($restrictedSensitive)foreach(['nik','family_card_number','address','guardian_phone','family_contact_phone'] as $sensitive)if(isset($record[$sensitive]))$record[$sensitive]='';fputcsv($out,array_map(static fn($column)=>$record[$column]??'', $columns));}
    fclose($out);exit;
}
$stmt=$pdo->prepare("SELECT * FROM `$table`".$whereSql.' ORDER BY id DESC LIMIT :limit OFFSET :offset');
foreach($params as $key=>$value)$stmt->bindValue(':'.$key,$value);
$stmt->bindValue(':limit',$limit,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();$rows=$stmt->fetchAll();
$editRecord=null;$detailRecord=null;
if(isset($_GET['id']) && in_array($_GET['action']??'',['edit','view'],true)) {
    $one=$pdo->prepare("SELECT * FROM `$table` WHERE id=:id");$one->execute(['id'=>(int)$_GET['id']]);$record=$one->fetch();
    if(($_GET['action']??'')==='edit')$editRecord=$record;else $detailRecord=$record;
}
$canWrite=!$readOnly && in_array($user['role'],$config['roles'],true);
$restrictedSensitive=in_array($user['role'],['petugas','ketua_yayasan'],true);
function upload_url(string $table,int $id,string $field):string{return 'download.php?table='.rawurlencode($table).'&id='.$id.'&field='.rawurlencode($field);}
?>
<?php if($flash):?><div class="alert alert-<?=h($flash['type'])?>"><?=h($flash['text'])?></div><?php endif;?>
<?php if($detailRecord):?><section class="panel"><div class="panel-head"><h2>Detail <?=h($config['title'])?></h2><a class="btn" href="index.php?page=<?=h($page)?>">Kembali</a></div><dl class="detail-list"><?php foreach($config['fields'] as [$key,$label,$type]):if($restrictedSensitive && in_array($key,['nik','family_card_number','address','guardian_phone','family_contact_phone'],true))continue;?><dt><?=h($label)?></dt><dd><?php if($type==='file' && !empty($detailRecord[$key])):?><a class="btn btn-soft" href="<?=h(upload_url($table,(int)$detailRecord['id'],$key))?>">Lihat / Unduh</a><?php elseif($key==='birth_date' && !empty($detailRecord[$key])):?><?=h(date('d-m-Y',strtotime($detailRecord[$key])))?> (<?=h((string)age_from_date($detailRecord[$key]))?> tahun)<?php else:?><?=nl2br(h($detailRecord[$key]??''))?:'Belum diisi'?><?php endif;?></dd><?php endforeach;?></dl></section>
<?php else:?>
<?php if($editRecord || ($_GET['action']??'')==='create'):if(!$canWrite):?><div class="notice">Akun Anda hanya dapat melihat data pada modul ini.</div><?php else:$record=$editRecord??[];?><section class="panel"><div class="panel-head"><h2><?=$editRecord?'Edit':'Tambah'?> <?=h($config['title'])?></h2><a class="btn" href="index.php?page=<?=h($page)?>">Tutup</a></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)($record['id']??0)?>"><div class="form-grid">
<?php foreach($config['fields'] as $field):
    [$key,$label,$type]=$field;
    $required=$field[3]??false;
    $options=$field[4]??[];
    $value=$record[$key]??'';
    if($key==='category' && isset($categoryForPage[$page]))$value=$categoryForPage[$page];
    if($key==='category' && isset($legalForPage[$page]))$value=$legalForPage[$page];
    if($type==='password' && $editRecord)continue;
?>
<div class="field <?=in_array($type,['textarea'],true)?'full':''?>"><label for="field-<?=h($key)?>"><?=h($label)?><?=$required?' *':''?></label>
<?php if($type==='textarea'):?><textarea class="form-control" id="field-<?=h($key)?>" name="<?=h($key)?>" <?=$required?'required':''?>><?=h($value)?></textarea>
<?php elseif($type==='select'):?><select class="form-select" id="field-<?=h($key)?>" name="<?=h($key)?>" <?=$required?'required':''?>><option value="">Pilih...</option><?php foreach($options as $option):?><option value="<?=h($option)?>" <?=$value===$option?'selected':''?>><?=h($option)?></option><?php endforeach;?></select>
<?php elseif($type==='file'):?><input class="form-control" id="field-<?=h($key)?>" type="file" name="<?=h($key)?>" accept="<?=in_array($key,['photo_path'],true)||$key==='documentation_path'?'.jpg,.jpeg,.png':'.pdf,.jpg,.jpeg,.png'?>" <?=$required && !$editRecord?'required':''?>><small>Maksimum 5 MB. File privat hanya dapat diakses melalui aplikasi.<?php if(!empty($value)):?> File saat ini: <a href="<?=h(upload_url($table,(int)$record['id'],$key))?>">Lihat</a><?php endif;?></small>
<?php else:?><input class="form-control" id="field-<?=h($key)?>" type="<?=h($type)?>" name="<?=h($key)?>" value="<?=h($value)?>" <?=$required?'required':''?> <?php if(in_array($key,['nik','family_card_number'],true)):?>inputmode="numeric" pattern="[0-9]{16}" minlength="16" maxlength="16"<?php endif;?> <?php if(in_array($key,['birth_date'],true)):?>data-age-from="#age-result"<?php endif;?>>
<?php if($key==='birth_date'):?><small>Usia: <input id="age-result" class="form-control" readonly aria-label="Usia otomatis"></small><?php endif;?>
<?php endif;?></div>
<?php endforeach;?>
</div><div class="button-row" style="margin-top:14px"><button class="btn btn-primary">Simpan</button><a class="btn" href="index.php?page=<?=h($page)?>">Batal</a></div></form></section><?php endif;endif;?>
<section class="panel"><div class="toolbar"><div><h2><?=h($config['title'])?></h2><span class="card-label"><?=$total?> data</span></div><div class="toolbar-right"><?php if($canWrite):?><a class="btn btn-primary" href="index.php?page=<?=h($page)?>&action=create">+ Tambah</a><?php endif;?><a class="btn btn-soft" href="index.php?page=<?=h($page)?>&export=csv">Ekspor Excel/CSV</a><button type="button" class="btn" onclick="window.print()">Cetak / Simpan PDF</button></div></div>
<form class="toolbar" method="get"><input type="hidden" name="page" value="<?=h($page)?>"><div class="toolbar-left"><input class="search-input" name="q" value="<?=h($search)?>" placeholder="Cari data..."><?php if(in_array('status',array_column($config['fields'],0),true)):?><select class="form-select" name="status" style="width:auto"><option value="">Semua status</option><?php foreach($config['fields'] as $field)if($field[0]==='status'&&$field[2]==='select')foreach($field[4] as $option):?><option value="<?=h($option)?>" <?=$filter===$option?'selected':''?>><?=h($option)?></option><?php endforeach;?></select><?php endif;?><?php if($dateColumn):?><input class="form-control" type="date" name="from" value="<?=h($from)?>" aria-label="Tanggal mulai"><input class="form-control" type="date" name="to" value="<?=h($to)?>" aria-label="Tanggal akhir"><?php endif;?><button class="btn">Terapkan</button></div></form>
<div class="table-wrap"><table><thead><tr><?php foreach($config['fields'] as [$key,$label,$type]):if($type==='file'||$key==='initial_password')continue;if($user['role']==='petugas' && in_array($key,['nik','family_card_number','address','guardian_phone','family_contact_phone'],true))continue;?><th><?=h($label)?></th><?php endforeach;?><th>Aksi</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td class="empty" colspan="12">Belum ada data. Tidak ada data contoh yang ditampilkan.</td></tr><?php endif;?>
<?php foreach($rows as $row):?><tr><?php foreach($config['fields'] as [$key,$label,$type]):if($type==='file'||$key==='initial_password')continue;if($restrictedSensitive && in_array($key,['nik','family_card_number','address','guardian_phone','family_contact_phone'],true))continue;?><td><?php if($type==='password'):?>••••••<?php elseif($type==='date'&&!empty($row[$key])):?><?=h(date('d-m-Y',strtotime($row[$key])))?><?php elseif($key==='birth_date'&&!empty($row[$key])):?><?=h((string)age_from_date($row[$key]))?> tahun<?php else:?><?=h($row[$key]??'—')?><?php endif;?></td><?php endforeach;?><td><div class="button-row"><a class="btn btn-soft" href="index.php?page=<?=h($page)?>&action=view&id=<?=(int)$row['id']?>">Detail</a><?php if($canWrite):?><a class="btn" href="index.php?page=<?=h($page)?>&action=edit&id=<?=(int)$row['id']?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?=h(csrf_token())?>"><input type="hidden" name="delete_id" value="<?=(int)$row['id']?>"><button class="btn btn-danger" data-confirm="Hapus data ini secara permanen?">Hapus</button></form><?php endif;?></div></td></tr><?php endforeach;?>
</tbody></table></div><div class="pagination"><?php for($p=1;$p<=$pages;$p++):?><a class="<?=$p===$currentPage?'current':''?>" href="index.php?page=<?=h($page)?>&q=<?=rawurlencode($search)?>&status=<?=rawurlencode($filter)?>&p=<?=$p?>"><?=$p?></a><?php endfor;?></div></section>
<?php endif;?>
