-- ===========================================================================
-- SiPrioritas Bansos — Skema + Seed (MySQL / phpMyAdmin)
-- Kecamatan Sukolilo. Peran: Petugas (register + verifikasi admin) & Admin.
--
-- Cara pakai: phpMyAdmin -> pilih database -> tab Import -> unggah file ini.
-- Skrip drop-safe: aman diimpor ulang.
-- ===========================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS penerima;
DROP TABLE IF EXISTS periode_bantuan;
DROP TABLE IF EXISTS households;
DROP TABLE IF EXISTS assessments;   -- tabel lama (model warga), dibuang
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- users : akun Petugas & Admin
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nik           VARCHAR(16)  NOT NULL UNIQUE,
    nama_lengkap  VARCHAR(120) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('petugas','admin') NOT NULL DEFAULT 'petugas',
    kelurahan     VARCHAR(50)  NULL,           -- NULL untuk admin (akses se-kecamatan)
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- households : data rumah tangga (banyak record per petugas, per kelurahan)
-- ---------------------------------------------------------------------------
CREATE TABLE households (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    petugas_id             INT NULL,
    kelurahan              VARCHAR(50)  NOT NULL,
    nama_kk                VARCHAR(120) NOT NULL,
    nik_kk                 VARCHAR(16)  NOT NULL UNIQUE,   -- deteksi duplikat (E2)
    alamat                 TEXT NOT NULL,
    pendapatan_bulanan     DECIMAL(15,2) NOT NULL,
    jumlah_tanggungan      INT NOT NULL,
    kondisi_tempat_tinggal VARCHAR(20) NOT NULL,
    kepemilikan_aset       VARCHAR(20) NOT NULL,
    indikator_tambahan     VARCHAR(30) NOT NULL,
    skor                   DECIMAL(5,2) NOT NULL,
    kategori               VARCHAR(20)  NOT NULL,
    faktor_json            TEXT NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kelurahan (kelurahan),
    INDEX idx_skor (skor),
    CONSTRAINT fk_households_petugas FOREIGN KEY (petugas_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- periode_bantuan & penerima : riwayat penyaluran (UC-08)
-- ---------------------------------------------------------------------------
CREATE TABLE periode_bantuan (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    label  VARCHAR(40) NOT NULL,
    tahun  INT NOT NULL,
    bulan  INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE penerima (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    periode_id INT NOT NULL,
    kelurahan  VARCHAR(50) NOT NULL,
    nama       VARCHAR(120) NOT NULL,
    skor       DECIMAL(5,2) NOT NULL,
    kategori   VARCHAR(20) NOT NULL,
    INDEX idx_periode (periode_id),
    INDEX idx_pen_kelurahan (kelurahan),
    CONSTRAINT fk_penerima_periode FOREIGN KEY (periode_id)
        REFERENCES periode_bantuan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===========================================================================
-- SEED
-- ===========================================================================

-- Admin (preprogrammed, hanya login). Password: Admin#2026
INSERT INTO users (nik, nama_lengkap, email, password_hash, role, kelurahan, status) VALUES
('0000000000000000', 'Administrator', 'admin@bansos.local',
 '$2b$12$mzAAhnCDY/PQdMXolE63ruJuXjNzxGEACuLlc0gZIpQjPZbZmdCBm',
 'admin', NULL, 'approved');

-- Petugas contoh (sudah disetujui). Kelurahan Keputih. Password: Admin#2026
INSERT INTO users (nik, nama_lengkap, email, password_hash, role, kelurahan, status) VALUES
('1111111111111111', 'Petugas Keputih', 'petugas@bansos.local',
 '$2b$12$mzAAhnCDY/PQdMXolE63ruJuXjNzxGEACuLlc0gZIpQjPZbZmdCBm',
 'petugas', 'Keputih', 'approved');

-- Data rumah tangga contoh (skor sudah dihitung sesuai bobot MCDM).
INSERT INTO households
   (petugas_id, kelurahan, nama_kk, nik_kk, alamat, pendapatan_bulanan,
    jumlah_tanggungan, kondisi_tempat_tinggal, kepemilikan_aset, indikator_tambahan,
    skor, kategori, faktor_json) VALUES
(NULL, 'Keputih',    'Budi Santoso', '3578010101800001', 'Jl. Keputih Tegal No. 10',
   800000,  4, 'Rusak Sedang', 'Rendah', 'Anak Putus Sekolah', 74.83, 'Layak', NULL),
(NULL, 'Keputih',    'Siti Aminah',  '3578010101850002', 'Jl. Keputih Gg. II No. 5',
   1500000, 2, 'Layak', 'Sedang', 'Tidak Ada', 40.71, 'Kurang Layak', NULL),
(NULL, 'Semolowaru', 'Warsito',      '3578010101700003', 'Jl. Semolowaru Bahari No. 3',
   300000,  6, 'Rusak Berat', 'Rendah', 'Disabilitas', 95.04, 'Sangat Layak', NULL);

-- Periode riwayat.
INSERT INTO periode_bantuan (id, label, tahun, bulan) VALUES
(1, 'Januari 2026', 2026, 1),
(2, 'Maret 2026',   2026, 3);

-- Penerima per periode (dengan kelurahan untuk filter admin).
INSERT INTO penerima (periode_id, kelurahan, nama, skor, kategori) VALUES
(1, 'Keputih',         'Budi Santoso', 81.50, 'Sangat Layak'),
(1, 'Semolowaru',      'Warsito',      63.00, 'Layak'),
(2, 'Semolowaru',      'Warsito',      88.00, 'Sangat Layak'),
(2, 'Menur Pumpungan', 'Dewi Rahayu',  72.30, 'Layak');
