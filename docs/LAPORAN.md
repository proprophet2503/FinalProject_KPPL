---
title: "Laporan Proyek — SiPrioritas Bansos"
subtitle: "Sistem Prioritas Penerima Bantuan Sosial (Skala Mikro, Kec. Sukolilo)"
author:
  - "Jeremy Mattathias Mboe (5054241012)"
  - "Shabrina Sarayati (5054241009)"
  - "Dean Arkeanu Andharu (5054231020)"
date: "KPPL RKA (N) — Institut Teknologi Sepuluh Nopember, 2026"
---

# 1. Pendahuluan

SiPrioritas Bansos adalah aplikasi web yang membantu pengurus tingkat kecamatan
menentukan prioritas penerima bantuan sosial secara objektif dan transparan.
Sistem menggantikan proses manual yang subjektif dengan penilaian berbasis
kriteria majemuk (MCDM): setiap rumah tangga memperoleh skor prioritas 0–100,
kategori kelayakan, dan rincian kontribusi tiap faktor.

Lingkup studi kasus: **Kecamatan Sukolilo, Surabaya**, mencakup tujuh kelurahan
(Semolowaru, Nginden Jangkungan, Menur Pumpungan, Klampis Ngasem, Gebang Putih,
Keputih, Medokan Semampir).

# 2. Teknologi

| Aspek | Keterangan |
|-------|------------|
| Backend | PHP 8 native (PDO + prepared statements), tanpa framework/Composer |
| Database | MySQL (impor `database.sql` via phpMyAdmin) |
| Frontend | HTML + CSS + JavaScript murni (HTML tertanam di berkas `.php`) |
| Hosting | InfinityFree (gratis) |
| Penilaian | MCDM berbobot transparan (identik dengan prototipe Python `legacy/`) |

# 3. Peran Pengguna

Sistem memiliki dua peran.

- **Petugas** — dapat melakukan **register** dan **login**. Saat mendaftar,
  petugas memilih satu **kelurahan** tempatnya bertugas (kecamatan tetap:
  Sukolilo). Akun petugas baru berstatus *menunggu verifikasi* hingga disetujui
  admin. Setelah disetujui, petugas dapat menginput dan mengelola data rumah
  tangga serta melihat skor — **terbatas pada kelurahannya saja**.
- **Admin** — akun *preprogrammed* (hanya login). Memiliki akses satu kecamatan
  penuh: memverifikasi petugas, memantau seluruh data (dengan filter kelurahan),
  dan melihat riwayat penyaluran.

# 4. Fitur Utama (Use Case)

| Kode | Fitur | Aktor | Halaman |
|------|-------|-------|---------|
| UC-01 | Input Data Rumah Tangga (validasi + deteksi duplikat NIK) | Petugas | `input.php` |
| UC-02 | Melihat Hasil Klasifikasi (skor + kategori) | Petugas/Admin | `status.php`, `detail.php` |
| UC-03 | Melihat Penjelasan Keputusan (faktor %) | Admin/Petugas | `detail.php`, `admin_dashboard.php` |
| UC-04 | Melihat Rekomendasi/Peringkat | Admin | `admin_dashboard.php` |
| UC-05 | Mengelola Data (edit + hapus) | Petugas | `input.php`, `detail.php` |
| UC-08 | Melihat Riwayat Penerima Bantuan | Admin | `admin_riwayat.php` |
| — | Verifikasi Petugas (terima/tolak) | Admin | `admin_verifikasi.php` |

# 5. Alur Kerja (Workflow)

## 5.1 Petugas

1. Mendaftar (NIK, nama, email, password, pilih kelurahan).
2. Sistem menetapkan status **pending**; petugas melihat layar "Verifikasi dalam
   proses".
3. Admin menyetujui → petugas masuk dashboard. Jika ditolak → layar "Ditolak".
4. Petugas menginput data rumah tangga: nama kepala keluarga, NIK KK, alamat,
   pendapatan bulanan, jumlah tanggungan, kondisi tempat tinggal, kepemilikan
   aset, indikator sosial. Semua pilihan berupa **dropdown**; angka tidak dapat
   diisi huruf.
5. Sistem menghitung skor dan kategori secara otomatis.
6. Petugas melihat daftar rumah tangga di kelurahannya, membuka detail (skor +
   faktor), serta dapat memperbarui atau menghapus data.

## 5.2 Admin

1. Login dengan akun admin.
2. Membuka **Verifikasi Petugas**: menerima, menolak, atau menonaktifkan petugas.
3. Membuka **Dashboard**: peringkat seluruh rumah tangga se-Sukolilo dengan
   **filter kelurahan**, lengkap dengan detail dan penjelasan faktor.
4. Membuka **Riwayat**: penerima bantuan per periode (berjalan dan historis),
   juga dapat **difilter per kelurahan**.

# 6. Arsitektur & Struktur Berkas

Setiap berkas PHP mewakili satu halaman; tidak ada router terpusat. Logika
bersama dipusatkan di `lib.php` (koneksi PDO, autentikasi, verifikasi, scoring
MCDM, validasi, CRUD data rumah tangga) dan `partials.php` (kerangka HTML +
navbar sesuai peran).

| Berkas | Fungsi |
|--------|--------|
| `index.php` | Beranda publik (landing + CTA) |
| `register.php` / `login.php` / `logout.php` | Autentikasi |
| `pending.php` | Layar status verifikasi petugas |
| `status.php` | Daftar data rumah tangga kelurahan (petugas) |
| `input.php` | Tambah/edit data rumah tangga |
| `detail.php` | Detail skor + faktor + hapus |
| `akun.php` | Ubah email/password petugas |
| `admin_dashboard.php` | Peringkat + filter kelurahan |
| `admin_verifikasi.php` | Verifikasi petugas |
| `admin_riwayat.php` | Riwayat penerima per periode |
| `lib.php`, `partials.php`, `config.php` | Inti, layout, konfigurasi |
| `assets/`, `database.sql` | Aset statis, skema + seed |

# 7. Basis Data

| Tabel | Isi |
|-------|-----|
| `users` | Akun petugas & admin. `nik`/`email` unik, `role`, `kelurahan`, `status` (pending/approved/rejected). |
| `households` | Data rumah tangga: `petugas_id`, `kelurahan`, `nama_kk`, `nik_kk` (unik), `alamat`, lima kriteria, `skor`, `kategori`, `faktor_json`. |
| `periode_bantuan` | Periode penyaluran (mis. "Januari 2026"). |
| `penerima` | Penerima historis per periode (dengan `kelurahan`). |

Relasi: `users 1—* households`, `periode_bantuan 1—* penerima`.

# 8. Mekanisme Penilaian (MCDM)

Tiap kriteria dinormalisasi ke skala 0–100, dikalikan bobot, lalu dijumlahkan
menjadi skor akhir 0–100.

| Kriteria | Bobot |
|----------|-------|
| Pendapatan Bulanan | 0.35 |
| Jumlah Tanggungan | 0.20 |
| Kondisi Tempat Tinggal | 0.20 |
| Kepemilikan Aset | 0.15 |
| Indikator Sosial | 0.10 |

Normalisasi: pendapatan ≤0 → 100, ≥ Rp 5.000.000 → 0, selainnya
(1 − pendapatan/5.000.000) × 100; tanggungan ≤0 → 0, ≥7 → 100, selainnya
(tanggungan/7) × 100; kondisi tempat tinggal Rusak Berat 100 / Rusak Sedang 60 /
Layak 10; kepemilikan aset Rendah 100 / Sedang 50 / Tinggi 10; indikator sosial
Disabilitas 100 / Sakit Kronis 90 / Lansia 80 / Anak Putus Sekolah 70 / Tidak
Ada 10.

Kategori: skor > 75 **Sangat Layak**, 50–75 **Layak**, < 50 **Kurang Layak**.

# 9. Validasi Input

| Field | Aturan |
|-------|--------|
| NIK (petugas & KK) | 16 digit angka, unik |
| Nama | tidak boleh mengandung angka |
| Email | format valid, unik |
| Password | minimal 8 karakter |
| Pendapatan | hanya angka (format rupiah otomatis), maksimal Rp 1.000.000.000 |
| Jumlah tanggungan | bilangan bulat, maksimal 20 |
| Kondisi / Aset / Indikator / Kelurahan | wajib dipilih dari dropdown |

Validasi sisi klien (JavaScript) hanya membantu format; validasi otoritatif
dilakukan di server.

# 10. Keamanan

- Kata sandi di-hash dengan `password_hash()` (bcrypt), diverifikasi
  `password_verify()`.
- Seluruh query memakai prepared statements (anti SQL injection).
- Token CSRF pada setiap form POST; output di-escape (anti XSS).
- `session_regenerate_id()` setelah login.
- Pembatasan akses: petugas hanya melihat data kelurahannya; akun aktif setelah
  diverifikasi admin.

# 11. Deployment (InfinityFree)

1. Buat database MySQL di Control Panel; catat host, nama DB, user, password.
2. Impor `database.sql` melalui phpMyAdmin (tab Import).
3. Edit `config.php` (kredensial DB).
4. Unggah seluruh berkas `.php` dan folder `assets/` ke `htdocs/`. Jangan unggah
   `database.sql`, `docs/`, `legacy/`, `.git/`.
5. Buka domain.

**Aplikasi live:** <https://bantuansosiallokal.great-site.net>

Akun default (password `Admin#2026`): admin `0000000000000000` /
`admin@bansos.local`; petugas contoh `1111111111111111` / `petugas@bansos.local`
(kelurahan Keputih, sudah disetujui).

# 12. Catatan

Sistem hanya menghasilkan **rekomendasi**; keputusan akhir distribusi bantuan
tetap berada pada pengurus atau pihak berwenang setempat. Prototipe Python
(FastAPI + SQLite) terdahulu diarsipkan di folder `legacy/` sebagai acuan logika.
