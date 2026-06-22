# SiPrioritas Bansos — PHP-native + MySQL (InfinityFree) — Design

Tanggal: 2026-06-22

## Tujuan
Port aplikasi prioritas penerima bansos (sebelumnya FastAPI/SQLite) ke stack
PHP-native + MySQL agar bisa di-deploy gratis di InfinityFree. Tambah autentikasi
(register/login/logout), halaman akun + upload foto profil, halaman "Status Saya"
untuk warga, dan dashboard admin. Logika scoring MCDM & aturan validasi UAT
di-port identik.

## Stack & Batasan
- PHP 8 native (PDO + prepared statements). Tanpa framework, tanpa Composer
  (InfinityFree tidak punya shell).
- MySQL (import `database.sql` via phpMyAdmin).
- HTML + CSS + JS murni (tanpa framework).
- Auth: `$_SESSION` + `password_hash`/`password_verify` (bcrypt bawaan PHP).
- Upload: validasi `finfo`, whitelist jpg/png, ≤2MB, nama file di-hash, folder
  `uploads/` (eksekusi PHP dimatikan via `.htaccess`).

## Peran (Roles)
- **Warga**: register (data KTP inti), login (NIK+Email+Password ketiganya wajib
  cocok), Page Akun (edit email, lengkapi field assessment, upload foto), Page
  "Status Saya" (skor/kategori/breakdown faktor — hanya miliknya). Tidak bisa
  melihat data warga lain.
- **Admin**: 1 akun preprogrammed (login only, tanpa register) dengan profil
  preprogrammed. Default kredensial: NIK `0000000000000000`,
  email `admin@bansos.local`, password `Admin#2026` (tersimpan hashed di seed).
  Dashboard: ranking + status semua warga. Halaman Riwayat per periode.

## Field KTP (register, set "Inti")
NIK (unik, 16 digit), Nama lengkap, Email (unik), Password, Alamat, Tempat lahir,
Tanggal lahir, Jenis kelamin, Agama, Status perkawinan, Pekerjaan.

## Field Assessment (di Page Akun)
pendapatan_bulanan, jumlah_tanggungan, kondisi_tempat_tinggal, kepemilikan_aset,
indikator_tambahan. Belum lengkap → tidak masuk ranking.

## Scoring (port identik dari `app/ai_model.py`)
- Bobot: pendapatan .35, tanggungan .20, kondisi .20, aset .15, indikator .10.
- PENDAPATAN_CEILING 5_000_000; TANGGUNGAN_CAP 7.
- Sub-skor kondisi {Rusak Berat:100, Rusak Sedang:60, Layak:10};
  aset {Rendah:100, Sedang:50, Tinggi:10};
  indikator {Disabilitas:100, Sakit Kronis:90, Lansia:80, Anak Putus Sekolah:70,
  Tidak Ada:10}.
- need_pendapatan: inverse clamp 0-100; need_tanggungan: linear clamp 0-100.
- Kategori: skor>75 "Sangat Layak"; ≥50 "Layak"; else "Kurang Layak".
- Faktor penjelasan: kontribusi = sub*bobot; persen = kontribusi/total*100; urut desc.

## Validasi (UAT)
- Nama: wajib, tidak boleh mengandung angka → "Nama tidak boleh ada angka."
- Pendapatan: wajib, 0 ≤ x ≤ 1_000_000_000; > batas → "Pendapatan melebihi batas maksimal."
- Tanggungan: bilangan bulat → "Jumlah tanggungan harus berupa bilangan bulat.";
  < 0 ditolak; > 20 → "Jumlah tanggungan tidak wajar (melebihi batas maksimal)."
- Input rupiah: JS format titik ribuan + numeric-only; server strip titik → parse.
- Tanggungan input: numeric-only.

## Riwayat / Periode
- Periode historis (Januari 2026, Maret 2026) di-seed di `database.sql` (tabel
  `penerima` denormalized: nama, skor, kategori).
- Periode berjalan dihitung live dari users+assessments (skor ≥ 50) saat admin
  buka halaman Riwayat. Tidak disimpan.

## Skema DB
- `users`(id, nik UNIQUE, nama_lengkap, email UNIQUE, password_hash, alamat,
  tempat_lahir, tanggal_lahir, jenis_kelamin, agama, status_perkawinan, pekerjaan,
  foto NULL, role ENUM('warga','admin'), created_at, updated_at)
- `assessments`(user_id PK FK, pendapatan_bulanan, jumlah_tanggungan,
  kondisi_tempat_tinggal, kepemilikan_aset, indikator_tambahan, skor, kategori,
  faktor_json, updated_at)
- `periode_bantuan`(id, label, tahun, bulan)
- `penerima`(id, periode_id FK, nama, skor, kategori)

## Struktur File (root = isi `htdocs/`)
config.php, db.php, lib.php (db+scoring+validation+auth+upload), partials.php,
index.php, register.php, login.php, logout.php, akun.php, status.php,
admin_dashboard.php, admin_riwayat.php, assets/css/app.css, assets/js/app.js,
uploads/.htaccess, database.sql, README.

## Keamanan
Prepared statements; CSRF token tiap POST; `htmlspecialchars` output;
`session_regenerate_id` saat login; guard `require_login`/`require_admin`; query
warga scoped ke `session user_id`; upload divalidasi (finfo+whitelist+size+random
name); `uploads/.htaccess` matikan PHP.

## Deploy InfinityFree
Edit `config.php` (host/db/user/pass dari panel) → import `database.sql` di
phpMyAdmin → upload semua file ke `htdocs/` → `uploads/` writable (755). Link host
ditaruh di README oleh user.
