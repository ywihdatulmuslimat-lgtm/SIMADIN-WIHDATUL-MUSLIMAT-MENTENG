<?php
declare(strict_types=1);
$reportMap=[
    'children-report'=>['children','Laporan Anak Asuh',['nis','full_name','gender','school_name','status'],'created_at'],
    'elderly-report'=>['elderly','Laporan Lansia',['registration_number','full_name','gender','status'],'created_at'],
    'program-report'=>['programs','Laporan Program',['name','category','start_date','location','coordinator','budget','status'],'start_date'],
    'facility-report'=>['facilities','Laporan Sarana dan Prasarana',['asset_code','name','category','quantity','condition_status','location'],'created_at'],
    'institution-report'=>['institution_profile','Laporan Kelembagaan',['name','institution_type','province','city','district','email','phone','leader_name'],'updated_at'],
];
$definition=$reportMap[$page]??$reportMap['children-report'];
[$table,$reportTitle,$columns,$dateColumn]=$definition;
$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));
$where=[];$params=[];
if($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]="`$dateColumn`>=:from_date";$params['from_date']=$from;}
if($to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]="`$dateColumn`<=:to_date";$params['to_date']=$to;}
$search=trim((string)($_GET['q']??''));
if($search!==''){$parts=[];foreach($columns as $i=>$column){if(in_array($column,['budget','quantity'],true))continue;$parts[]="`$column` LIKE :q$i";$params["q$i"]='%'.$search.'%';}if($parts)$where[]='('.implode(' OR ',$parts).')';}
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
$sql='SELECT '.implode(',',array_map(static fn($column)=>"`$column`",$columns))." FROM `$table`".$whereSql." ORDER BY `$dateColumn` DESC";
$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
if(($_GET['export']??'')==='csv') {
    if(ob_get_level()>0)ob_clean();
    header('Content-Type:text/csv; charset=utf-8');header('Content-Disposition:attachment; filename="'.str_replace('_','-',$page).'.csv"');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$columns);
    foreach($rows as $row){$values=[];foreach($columns as $column){$value=(string)($row[$column]??'');if(preg_match('/^[=+\-@]/',$value))$value="'".$value;$values[]=$value;}fputcsv($out,$values);}fclose($out);exit;
}
?>
<section class="panel"><div class="toolbar"><div><h2><?=h($reportTitle)?></h2><span class="card-label"><?=count($rows)?> baris berdasarkan data tersimpan</span></div><div class="toolbar-right"><a class="btn btn-soft" href="index.php?page=<?=h($page)?>&export=csv&from=<?=h($from)?>&to=<?=h($to)?>&q=<?=rawurlencode($search)?>">Ekspor Excel/CSV</a><button type="button" class="btn" onclick="window.print()">Cetak / Simpan PDF</button></div></div><form class="toolbar" method="get"><input type="hidden" name="page" value="<?=h($page)?>"><div class="toolbar-left"><input class="form-control" type="date" name="from" value="<?=h($from)?>" aria-label="Tanggal mulai"><input class="form-control" type="date" name="to" value="<?=h($to)?>" aria-label="Tanggal akhir"><input class="search-input" name="q" value="<?=h($search)?>" placeholder="Cari laporan..."><button class="btn">Filter</button></div></form><div class="table-wrap"><table><thead><tr><?php foreach($columns as $column):?><th><?=h(ucwords(str_replace('_',' ',$column)))?></th><?php endforeach;?></tr></thead><tbody><?php if(!$rows):?><tr><td class="empty" colspan="<?=count($columns)?>">Belum ada data untuk filter ini.</td></tr><?php else:foreach($rows as $row):?><tr><?php foreach($columns as $column):?><td><?=h($row[$column]??'—')?></td><?php endforeach;?></tr><?php endforeach;endif;?></tbody></table></div></section>
