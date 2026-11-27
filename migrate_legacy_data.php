<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("Jalankan melalui CLI PHP.\n");}
require __DIR__.'/../config/database.php';
$pdo=db();
$tableCheck=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='dashboard_records'");
$tableCheck->execute();
if((int)$tableCheck->fetchColumn()===0) {
    echo "Tidak ada tabel legacy dashboard_records; tidak ada data yang diubah.\n";
    exit(0);
}
$source=$pdo->query("SELECT id,module,payload FROM dashboard_records WHERE is_deleted=0 AND module IN ('anak_asuh','lansia') ORDER BY id")->fetchAll();
$inserted=['anak_asuh'=>0,'lansia'=>0];
$pdo->beginTransaction();
try {
    foreach($source as $bundle) {
        $records=json_decode((string)$bundle['payload'],true,512,JSON_THROW_ON_ERROR);
        if(!is_array($records))throw new RuntimeException('Format data lama tidak sesuai.');
        foreach($records as $position=>$record) {
            if(!is_array($record))throw new RuntimeException('Ada record lama yang formatnya tidak sesuai.');
            $legacyId=(string)($record['id']??($bundle['id'].'-'.$position));
            $suffix=substr(hash('sha256',$bundle['module'].':'.$legacyId),0,20);
            $name=trim((string)($record['nama']??''));
            if($name==='')throw new RuntimeException('Record lama tidak memiliki nama; tidak ada record sumber yang diubah.');
            $genderValue=strtolower(trim((string)($record['jk']??'')));
            if(in_array($genderValue,['perempuan','p','female','f'],true))$gender='Perempuan';
            elseif(in_array($genderValue,['laki-laki','laki laki','l','male','m'],true))$gender='Laki-laki';
            else throw new RuntimeException('Jenis kelamin data lama tidak dikenali; migrasi dihentikan tanpa menghapus sumber.');
            $birth=null;
            $rawDate=trim((string)($record['tglLahir']??''));
            if($rawDate!=='') {
                foreach(['Y-m-d','d-m-Y','d/m/Y','Y/m/d'] as $format) {
                    $date=DateTimeImmutable::createFromFormat('!'.$format,$rawDate);
                    if($date && $date->format($format)===$rawDate){$birth=$date->format('Y-m-d');break;}
                }
                if($birth===null)throw new RuntimeException('Tanggal lahir pada data lama tidak dapat dibaca; data sumber tetap utuh.');
            }
            $nik=preg_match('/^\d{16}$/',(string)($record['nik']??''))?(string)$record['nik']:null;
            $kk=preg_match('/^\d{16}$/',(string)($record['noKK']??''))?(string)$record['noKK']:null;
            if($bundle['module']==='lansia') {
                $status=trim((string)($record['status']??'Aktif'));
                if(!in_array($status,['Aktif','Tidak Aktif','Meninggal','Pindah'],true))$status='Aktif';
                $stmt=$pdo->prepare("INSERT IGNORE INTO elderly (registration_number,nik,family_card_number,full_name,birth_place,birth_date,gender,address,family_contact_name,family_contact_phone,health_needs,service_needs,status,notes) VALUES (:registration,:nik,:kk,:name,:birth_place,:birth,:gender,:address,:family,:phone,:health,:needs,:status,:notes)");
                $stmt->execute([
                    'registration'=>'LEGACY-'.$suffix,'nik'=>$nik,'kk'=>$kk,'name'=>$name,
                    'birth_place'=>trim((string)($record['tempatLahir']??'')),'birth'=>$birth,'gender'=>$gender,
                    'address'=>trim((string)($record['alamat']??'')),
                    'family'=>trim((string)($record['wali']??'')),
                    'phone'=>trim((string)($record['telpWali']??$record['telp']??'')),
                    'health'=>trim(implode(' · ',array_filter([(string)($record['kondisiKesehatan']??''),(string)($record['penyakit']??'')]))),
                    'needs'=>trim((string)($record['kebutuhanBantuan']??'')),
                    'status'=>$status,'notes'=>trim((string)($record['catatan']??'')),
                ]);
                $inserted['lansia']+=$stmt->rowCount();
            } else {
                $stmt=$pdo->prepare("INSERT IGNORE INTO children (nis,nik,family_card_number,full_name,birth_place,birth_date,gender,address,guardian_name,school_name,education_level,status,notes) VALUES (:nis,:nik,:kk,:name,:birth_place,:birth,:gender,:address,:guardian,:school,:education,'Aktif',:notes)");
                $stmt->execute([
                    'nis'=>'LEGACY-'.$suffix,'nik'=>$nik,'kk'=>$kk,'name'=>$name,
                    'birth_place'=>'','birth'=>$birth,'gender'=>$gender,'address'=>'',
                    'guardian'=>trim((string)($record['wali']??'')),
                    'school'=>trim((string)($record['pendidikan']??'')),
                    'education'=>trim((string)($record['kategori']??'')),
                    'notes'=>'Disalin dari data terdahulu; sumber tetap tersedia pada dashboard_records.',
                ]);
                $inserted['anak_asuh']+=$stmt->rowCount();
            }
        }
    }
    $pdo->commit();
} catch(PDOException|RuntimeException|JsonException $exception) {
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,$exception->getMessage().PHP_EOL);
    exit(1);
}
echo 'Data lama disalin tanpa menghapus sumber. Lansia baru: '.$inserted['lansia'].'; anak asuh baru: '.$inserted['anak_asuh'].PHP_EOL;
