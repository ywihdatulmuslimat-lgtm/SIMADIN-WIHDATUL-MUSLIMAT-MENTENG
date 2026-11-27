<?php
declare(strict_types=1);

function module_definitions(): array
{
    $admin = ['super_admin','admin_operator'];
    $staff = ['super_admin','admin_operator','petugas'];
    $everyone = ['super_admin','ketua_yayasan','admin_operator','petugas'];
    return [
        'children'=>['title'=>'Data Anak Asuh','table'=>'children','roles'=>$staff,'search'=>['nis','full_name','guardian_name'],'fields'=>[
            ['nis','NIS / Nomor Induk','text',true],['nik','NIK','text',false],['family_card_number','Nomor KK','text',false],['full_name','Nama Lengkap','text',true],['birth_place','Tempat Lahir','text',false],['birth_date','Tanggal Lahir','date',false],['gender','Jenis Kelamin','select',true,['Laki-laki','Perempuan']],['address','Alamat','textarea',false],['guardian_name','Orang Tua / Wali','text',false],['guardian_phone','Telepon Wali','tel',false],['school_name','Sekolah','text',false],['education_level','Pendidikan','text',false],['entry_year','Tahun Masuk','number',false],['status','Status','select',true,['Aktif','Tidak Aktif','Lulus','Pindah']],['photo_path','Foto','file',false]
        ]],
        'child_documents'=>['title'=>'Data Dokumen Anak Asuh','table'=>'child_documents','roles'=>$staff,'search'=>['document_type','original_name'],'fields'=>[
            ['child_id','ID Anak Asuh','number',true],['document_type','Jenis Dokumen','text',true],['file_path','File PDF/JPG/PNG','file',true]
        ]],
        'elderly_documents'=>['title'=>'Data Dokumen Lansia','table'=>'elderly_documents','roles'=>$staff,'search'=>['document_type','original_name'],'fields'=>[
            ['elderly_id','ID Lansia','number',true],['document_type','Jenis Dokumen','text',true],['file_path','File PDF/JPG/PNG','file',true]
        ]],
        'elderly'=>['title'=>'Data Lansia','table'=>'elderly','roles'=>$staff,'search'=>['registration_number','full_name','family_contact_name'],'fields'=>[
            ['registration_number','Nomor Registrasi','text',true],['nik','NIK','text',false],['family_card_number','Nomor KK','text',false],['full_name','Nama Lengkap','text',true],['birth_place','Tempat Lahir','text',false],['birth_date','Tanggal Lahir','date',false],['gender','Jenis Kelamin','select',true,['Laki-laki','Perempuan']],['address','Alamat','textarea',false],['family_contact_name','Nama Keluarga','text',false],['family_contact_phone','Telepon Keluarga','tel',false],['health_needs','Kesehatan','textarea',false],['service_needs','Kebutuhan Layanan','textarea',false],['status','Status','select',true,['Aktif','Tidak Aktif','Meninggal','Pindah']],['photo_path','Foto','file',false],['notes','Catatan','textarea',false]
        ]],
        'assistance'=>['title'=>'Riwayat Bantuan','table'=>'assistance_records','roles'=>$staff,'search'=>['assistance_type','description'],'fields'=>[
            ['recipient_type','Penerima','select',true,['anak_asuh','lansia']],['child_id','ID Anak Asuh','number',false],['elderly_id','ID Lansia','number',false],['assistance_date','Tanggal','date',true],['assistance_type','Jenis Bantuan','text',true],['description','Keterangan','textarea',false],['amount','Jumlah / Nilai Rupiah','number',false]
        ]],
        'programs'=>['title'=>'Program dan Kegiatan','table'=>'programs','roles'=>$staff,'search'=>['name','category','coordinator'],'fields'=>[
            ['name','Nama Program','text',true],['category','Kategori','select',true,['Umum','Rantang Kasih Lansia','Anak Asuh','Sosial','Pendidikan']],['description','Deskripsi','textarea',false],['start_date','Tanggal Mulai','date',false],['end_date','Tanggal Selesai','date',false],['location','Lokasi','text',false],['coordinator','Penanggung Jawab','text',false],['budget','Anggaran (Rp)','number',false],['status','Status','select',true,['Rencana','Berjalan','Selesai','Dibatalkan']],['documentation_path','Dokumentasi','file',false]
        ]],
        'facilities'=>['title'=>'Sarana dan Prasarana','table'=>'facilities','roles'=>$staff,'search'=>['asset_code','name','category'],'fields'=>[
            ['asset_code','Kode Aset','text',true],['name','Nama','text',true],['category','Kategori','select',true,['Tanah','Bangunan','Ruangan','Fasilitas','Inventaris','Lainnya']],['quantity','Jumlah','number',true],['unit','Satuan','text',false],['condition_status','Kondisi','select',true,['Baik','Perlu Perbaikan','Rusak']],['location','Lokasi','text',false],['acquired_year','Tahun Pengadaan','number',false],['funding_source','Sumber Dana','text',false],['description','Keterangan','textarea',false],['photo_path','Foto','file',false],['document_path','Dokumen','file',false]
        ]],
        'locations'=>['title'=>'Lokasi Lembaga','table'=>'institution_locations','roles'=>$admin,'search'=>['location_name','city','district'],'fields'=>[
            ['location_name','Nama Lokasi','text',true],['address','Alamat','textarea',true],['province','Provinsi','text',false],['city','Kota/Kabupaten','text',false],['district','Kecamatan','text',false],['village','Kelurahan','text',false],['postal_code','Kode Pos','text',false],['latitude','Latitude','number',false],['longitude','Longitude','number',false],['notes','Catatan','textarea',false]
        ]],
        'gallery'=>['title'=>'Galeri Foto','table'=>'gallery_photos','roles'=>$staff,'search'=>['title','description'],'fields'=>[
            ['title','Judul Foto','text',true],['description','Keterangan','textarea',false],['photo_path','File Foto','file',true],['taken_at','Tanggal Foto','date',false]
        ]],
        'legal'=>['title'=>'Dokumen Legalitas','table'=>'legal_documents','roles'=>$staff,'search'=>['category','document_name','document_number'],'fields'=>[
            ['category','Jenis Dokumen','text',true],['document_name','Nama Dokumen','text',true],['document_number','Nomor Dokumen','text',false],['issuer','Instansi Penerbit','text',false],['issued_at','Tanggal Terbit','date',false],['valid_until','Masa Berlaku','date',false],['file_path','PDF/JPG/PNG','file',false],['notes','Keterangan','textarea',false]
        ]],
        'accreditations'=>['title'=>'Data Akreditasi NPSAA','table'=>'accreditations','roles'=>$staff,'search'=>['institution_name','accreditation_number','status'],'fields'=>[
            ['institution_name','Nama Lembaga','text',true],['accreditation_number','Nomor Akreditasi','text',false],['status','Status Akreditasi','text',false],['issued_at','Tanggal Terbit','date',false],['valid_until','Masa Berlaku','date',false],['file_path','Dokumen','file',false],['notes','Keterangan','textarea',false]
        ]],
        'school_years'=>['title'=>'Tahun Ajaran','table'=>'school_years','roles'=>$staff,'search'=>['name'],'fields'=>[
            ['name','Nama Tahun Ajaran','text',true],['starts_on','Mulai','date',false],['ends_on','Selesai','date',false],['active','Aktif (1/0)','number',false]
        ]],
        'madrasah'=>['title'=>'Data Madrasah','table'=>'madrasah','roles'=>$staff,'search'=>['name','head_name'],'fields'=>[
            ['name','Nama Madrasah','text',true],['address','Alamat','textarea',false],['phone','Telepon','tel',false],['head_name','Kepala Madrasah','text',false],['founded_year','Tahun Berdiri','number',false]
        ]],
        'teachers'=>['title'=>'Data Guru','table'=>'teachers','roles'=>$staff,'search'=>['employee_number','full_name','email'],'fields'=>[
            ['employee_number','NIP/Nomor Guru','text',true],['nik','NIK','text',false],['full_name','Nama Lengkap','text',true],['gender','Jenis Kelamin','select',true,['Laki-laki','Perempuan']],['education','Pendidikan','text',false],['phone','Telepon','tel',false],['email','Email','email',false],['address','Alamat','textarea',false],['status','Status','select',true,['Aktif','Tidak Aktif']]
        ]],
        'classes'=>['title'=>'Data Kelas','table'=>'classes','roles'=>$staff,'search'=>['name','grade_level'],'fields'=>[
            ['name','Nama Kelas','text',true],['grade_level','Tingkat','text',false],['teacher_id','ID Wali Kelas','number',false]
        ]],
        'students'=>['title'=>'Data Santri','table'=>'students','roles'=>$staff,'search'=>['student_number','full_name'],'fields'=>[
            ['student_number','NIS','text',true],['full_name','Nama Lengkap','text',true],['gender','Jenis Kelamin','select',true,['Laki-laki','Perempuan']],['class_id','ID Kelas','number',false],['school_year_id','ID Tahun Ajaran','number',false],['status','Status','select',true,['Aktif','Tidak Aktif','Lulus','Pindah']]
        ]],
        'subjects'=>['title'=>'Mata Pelajaran','table'=>'subjects','roles'=>$staff,'search'=>['code','name'],'fields'=>[
            ['code','Kode','text',true],['name','Mata Pelajaran','text',true],['minimum_score','KKM','number',false]
        ]],
        'schedules'=>['title'=>'Jadwal Pelajaran','table'=>'lesson_schedules','roles'=>$staff,'search'=>['weekday'],'fields'=>[
            ['class_id','ID Kelas','number',true],['teacher_id','ID Guru','number',true],['subject_id','ID Mata Pelajaran','number',true],['weekday','Hari','select',true,['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Ahad']],['starts_at','Jam Mulai','time',true],['ends_at','Jam Selesai','time',true]
        ]],
        'attendance'=>['title'=>'Absensi','table'=>'attendance','roles'=>$staff,'search'=>['attendance_date','attendance_status'],'fields'=>[
            ['student_id','ID Santri','number',true],['attendance_date','Tanggal','date',true],['attendance_status','Status','select',true,['Hadir','Izin','Sakit','Alpa']],['notes','Catatan','text',false]
        ]],
        'grades'=>['title'=>'Penilaian','table'=>'grades','roles'=>$staff,'search'=>['semester'],'fields'=>[
            ['student_id','ID Santri','number',true],['subject_id','ID Mata Pelajaran','number',true],['school_year_id','ID Tahun Ajaran','number',true],['semester','Semester','select',true,['Ganjil','Genap']],['assignment_score','Nilai Tugas','number',false],['midterm_score','Nilai UTS','number',false],['final_score','Nilai UAS','number',false],['teacher_notes','Catatan Guru','textarea',false]
        ]],
        'reports'=>['title'=>'Rapor','table'=>'report_cards','roles'=>$staff,'search'=>['semester','notes'],'fields'=>[
            ['student_id','ID Santri','number',true],['school_year_id','ID Tahun Ajaran','number',true],['semester','Semester','select',true,['Ganjil','Genap']],['notes','Catatan','textarea',false]
        ]],
        'finance'=>['title'=>'Laporan Keuangan','table'=>'financial_records','roles'=>$admin,'search'=>['category','description','reference_number'],'fields'=>[
            ['transaction_date','Tanggal','date',true],['direction','Jenis','select',true,['Masuk','Keluar']],['category','Kategori','text',true],['description','Keterangan','textarea',false],['amount','Jumlah Rupiah','number',true],['reference_number','Nomor Referensi','text',false]
        ]],
        'users'=>['title'=>'Manajemen Pengguna','table'=>'app_users','roles'=>['super_admin'],'search'=>['name','username','email'],'fields'=>[
            ['name','Nama','text',true],['username','Username','text',true],['email','Email','email',false],['role','Role','select',true,['super_admin','ketua_yayasan','admin_operator','petugas']],['active','Aktif (1/0)','number',true],['initial_password','Password Awal (min. 12 karakter)','password',true]
        ]],
    ];
}

function role_label(string $role): string
{
    return [
        'super_admin'=>'Super Admin',
        'ketua_yayasan'=>'Ketua Yayasan',
        'admin_operator'=>'Admin/Operator',
        'petugas'=>'Petugas',
    ][$role] ?? $role;
}
