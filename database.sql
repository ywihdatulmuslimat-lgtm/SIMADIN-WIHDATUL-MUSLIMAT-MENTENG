CREATE DATABASE IF NOT EXISTS db_yayasan_wm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_yayasan_wm;

CREATE TABLE IF NOT EXISTS app_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(190) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','ketua_yayasan','admin_operator','petugas') NOT NULL DEFAULT 'petugas',
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_active(role,active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS institution_profile (
  id TINYINT UNSIGNED PRIMARY KEY,
  name VARCHAR(190) NOT NULL DEFAULT '',
  institution_type VARCHAR(100) NOT NULL DEFAULT '',
  address TEXT,
  province VARCHAR(100) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  district VARCHAR(100) NOT NULL DEFAULT '',
  village VARCHAR(100) NOT NULL DEFAULT '',
  postal_code VARCHAR(10) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(50) NOT NULL DEFAULT '',
  website VARCHAR(190) NOT NULL DEFAULT '',
  founded_year SMALLINT UNSIGNED NULL,
  leader_name VARCHAR(150) NOT NULL DEFAULT '',
  description TEXT,
  history TEXT,
  vision TEXT,
  mission TEXT,
  organization_structure TEXT,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS institution_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_name VARCHAR(150) NOT NULL,
  address TEXT NOT NULL,
  province VARCHAR(100) NOT NULL DEFAULT '',
  city VARCHAR(100) NOT NULL DEFAULT '',
  district VARCHAR(100) NOT NULL DEFAULT '',
  village VARCHAR(100) NOT NULL DEFAULT '',
  postal_code VARCHAR(10) NOT NULL DEFAULT '',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS children (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nis VARCHAR(40) NOT NULL UNIQUE,
  nik CHAR(16) NULL,
  family_card_number CHAR(16) NULL,
  full_name VARCHAR(150) NOT NULL,
  birth_place VARCHAR(100) NOT NULL DEFAULT '',
  birth_date DATE NULL,
  gender ENUM('Laki-laki','Perempuan') NOT NULL,
  address TEXT,
  guardian_name VARCHAR(150) NOT NULL DEFAULT '',
  guardian_phone VARCHAR(30) NOT NULL DEFAULT '',
  school_name VARCHAR(190) NOT NULL DEFAULT '',
  education_level VARCHAR(100) NOT NULL DEFAULT '',
  entry_year YEAR NULL,
  status ENUM('Aktif','Tidak Aktif','Lulus','Pindah') NOT NULL DEFAULT 'Aktif',
  photo_path VARCHAR(255) NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_children_name(full_name),
  INDEX idx_children_status(status),
  INDEX idx_children_entry_year(entry_year)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS elderly (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_number VARCHAR(40) NOT NULL UNIQUE,
  nik CHAR(16) NULL,
  family_card_number CHAR(16) NULL,
  full_name VARCHAR(150) NOT NULL,
  birth_place VARCHAR(100) NOT NULL DEFAULT '',
  birth_date DATE NULL,
  gender ENUM('Laki-laki','Perempuan') NOT NULL,
  address TEXT,
  family_contact_name VARCHAR(150) NOT NULL DEFAULT '',
  family_contact_phone VARCHAR(30) NOT NULL DEFAULT '',
  health_needs TEXT,
  service_needs TEXT,
  status ENUM('Aktif','Tidak Aktif','Meninggal','Pindah') NOT NULL DEFAULT 'Aktif',
  photo_path VARCHAR(255) NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_elderly_name(full_name),
  INDEX idx_elderly_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS programs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  category ENUM('Umum','Rantang Kasih Lansia','Anak Asuh','Sosial','Pendidikan') NOT NULL DEFAULT 'Umum',
  description TEXT,
  start_date DATE NULL,
  end_date DATE NULL,
  location VARCHAR(190) NOT NULL DEFAULT '',
  coordinator VARCHAR(150) NOT NULL DEFAULT '',
  budget DECIMAL(14,2) NULL,
  status ENUM('Rencana','Berjalan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Rencana',
  documentation_path VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_program_dates(start_date,end_date),
  INDEX idx_program_category(category),
  CONSTRAINT fk_program_user FOREIGN KEY(created_by) REFERENCES app_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assistance_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('anak_asuh','lansia') NOT NULL,
  child_id BIGINT UNSIGNED NULL,
  elderly_id BIGINT UNSIGNED NULL,
  assistance_date DATE NOT NULL,
  assistance_type VARCHAR(120) NOT NULL,
  description TEXT,
  amount DECIMAL(14,2) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_assistance_date(assistance_date),
  CONSTRAINT fk_assistance_child FOREIGN KEY(child_id) REFERENCES children(id) ON DELETE CASCADE,
  CONSTRAINT fk_assistance_elderly FOREIGN KEY(elderly_id) REFERENCES elderly(id) ON DELETE CASCADE,
  CONSTRAINT fk_assistance_user FOREIGN KEY(created_by) REFERENCES app_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS facilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  category ENUM('Tanah','Bangunan','Ruangan','Fasilitas','Inventaris','Lainnya') NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit VARCHAR(30) NOT NULL DEFAULT 'unit',
  condition_status ENUM('Baik','Perlu Perbaikan','Rusak') NOT NULL DEFAULT 'Baik',
  location VARCHAR(190) NOT NULL DEFAULT '',
  acquired_year YEAR NULL,
  funding_source VARCHAR(150) NOT NULL DEFAULT '',
  description TEXT,
  photo_path VARCHAR(255) NULL,
  document_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_facilities_category(category),
  INDEX idx_facilities_condition(condition_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gallery_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT,
  photo_path VARCHAR(255) NOT NULL,
  taken_at DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS legal_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(100) NOT NULL,
  document_name VARCHAR(190) NOT NULL,
  document_number VARCHAR(120) NULL,
  issuer VARCHAR(190) NOT NULL DEFAULT '',
  issued_at DATE NULL,
  valid_until DATE NULL,
  file_path VARCHAR(255) NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_legal_category(category),
  INDEX idx_legal_validity(valid_until)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accreditations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_name VARCHAR(190) NOT NULL DEFAULT '',
  accreditation_number VARCHAR(120) NULL,
  status VARCHAR(80) NOT NULL DEFAULT '',
  issued_at DATE NULL,
  valid_until DATE NULL,
  file_path VARCHAR(255) NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS child_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  child_id BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_child_docs_child(child_id),
  CONSTRAINT fk_child_docs_child FOREIGN KEY(child_id) REFERENCES children(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS elderly_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  elderly_id BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_elderly_docs_person(elderly_id),
  CONSTRAINT fk_elderly_docs_person FOREIGN KEY(elderly_id) REFERENCES elderly(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS school_years (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,
  starts_on DATE NULL,
  ends_on DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS madrasah (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  address TEXT,
  phone VARCHAR(50) NOT NULL DEFAULT '',
  head_name VARCHAR(150) NOT NULL DEFAULT '',
  founded_year YEAR NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teachers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_number VARCHAR(50) NOT NULL UNIQUE,
  nik CHAR(16) NULL,
  full_name VARCHAR(150) NOT NULL,
  gender ENUM('Laki-laki','Perempuan') NOT NULL,
  education VARCHAR(100) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  address TEXT,
  status ENUM('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_teacher_name(full_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  grade_level VARCHAR(50) NOT NULL DEFAULT '',
  teacher_id INT UNSIGNED NULL,
  CONSTRAINT fk_class_teacher FOREIGN KEY(teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_number VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  gender ENUM('Laki-laki','Perempuan') NOT NULL,
  class_id INT UNSIGNED NULL,
  school_year_id INT UNSIGNED NULL,
  status ENUM('Aktif','Tidak Aktif','Lulus','Pindah') NOT NULL DEFAULT 'Aktif',
  CONSTRAINT fk_student_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE SET NULL,
  CONSTRAINT fk_student_year FOREIGN KEY(school_year_id) REFERENCES school_years(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  minimum_score DECIMAL(5,2) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lesson_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  weekday ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Ahad') NOT NULL,
  starts_at TIME NOT NULL,
  ends_at TIME NOT NULL,
  CONSTRAINT fk_schedule_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_teacher FOREIGN KEY(teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  CONSTRAINT fk_schedule_subject FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  attendance_status ENUM('Hadir','Izin','Sakit','Alpa') NOT NULL,
  notes VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY uq_attendance_student_day(student_id,attendance_date),
  CONSTRAINT fk_attendance_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grades (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  school_year_id INT UNSIGNED NOT NULL,
  semester ENUM('Ganjil','Genap') NOT NULL,
  assignment_score DECIMAL(5,2) NULL,
  midterm_score DECIMAL(5,2) NULL,
  final_score DECIMAL(5,2) NULL,
  teacher_notes TEXT,
  CONSTRAINT fk_grade_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_grade_subject FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_grade_year FOREIGN KEY(school_year_id) REFERENCES school_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS report_cards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  school_year_id INT UNSIGNED NOT NULL,
  semester ENUM('Ganjil','Genap') NOT NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_report_year FOREIGN KEY(school_year_id) REFERENCES school_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financial_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_date DATE NOT NULL,
  direction ENUM('Masuk','Keluar') NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT,
  amount DECIMAL(14,2) NOT NULL,
  reference_number VARCHAR(100) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_financial_date(transaction_date),
  CONSTRAINT fk_financial_user FOREIGN KEY(created_by) REFERENCES app_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity VARCHAR(100) NOT NULL DEFAULT '',
  details TEXT,
  ip_address VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_created(created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY(user_id) REFERENCES app_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB;

INSERT INTO institution_profile (id,name,city,province)
SELECT 1,'Yayasan Wihdatul Muslimat','Jakarta Pusat','DKI Jakarta'
WHERE NOT EXISTS (SELECT 1 FROM institution_profile WHERE id=1);

INSERT IGNORE INTO app_users (name,username,password_hash,role,active,must_change_password)
VALUES ('Administrator','admin','$2y$10$2SqAqLfGR.JLQm.Zxu16EOviiPgngmBbNfpJCXmQacMbtZBtUyvMi','super_admin',1,1);
