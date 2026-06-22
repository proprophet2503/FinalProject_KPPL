# SiPrioritas Bansos — PHP + MySQL (InfinityFree)

Sistem prioritas penerima bantuan sosial skala mikro untuk **Kecamatan Sukolilo**.
**Petugas** kelurahan mendata rumah tangga; sistem menghitung **skor prioritas
(MCDM transparan)** beserta kategori kelayakan dan rincian faktornya. **Admin**
memantau seluruh kecamatan, memverifikasi petugas, dan melihat riwayat penyaluran.

**Link hosting (live):** _<!-- isi URL InfinityFree Anda di sini -->_

---

## Stack
- **Backend:** PHP 8 native (PDO + prepared statements) — tanpa framework, tanpa Composer
- **Database:** MySQL (impor `database.sql` via phpMyAdmin)
- **Frontend:** HTML + CSS + JavaScript murni (HTML tertanam di `.php`)
- **Hosting:** InfinityFree (gratis)
- **Penilaian:** MCDM berbobot, identik dengan prototipe `legacy/app/ai_model.py`

---

## Peran & Akses

| Peran | Daftar? | Akses |
|-------|---------|-------|
| **Petugas** | Ya (register + login) | Memilih **1 kelurahan** saat daftar. Setelah **disetujui admin**: input & kelola data rumah tangga, lihat skor — **hanya di kelurahannya**. |
| **Admin** | Tidak (preprogrammed, login saja) | Seluruh Kec. Sukolilo. Verifikasi petugas, dashboard semua data (filter kelurahan), riwayat penyaluran. |

7 kelurahan Sukolilo (dropdown): Semolowaru, Nginden Jangkungan, Menur Pumpungan,
Klampis Ngasem, Gebang Putih, Keputih, Medokan Semampir.

### Akun default (password: `Admin#2026`)
- **Admin:** NIK `0000000000000000` · `admin@bansos.local`
- **Petugas contoh (Keputih, approved):** NIK `1111111111111111` · `petugas@bansos.local`

> Ganti password setelah deploy (update kolom `password_hash` via phpMyAdmin).

---

## Fitur Utama (Use Case)

| Use Case | Fitur | Halaman |
|----------|-------|---------|
| **UC-01** | Input Data Rumah Tangga (validasi + deteksi duplikat NIK) | `input.php` |
| **UC-02 / UC-03** | Hasil Klasifikasi & Penjelasan (skor, kategori, faktor %) | `status.php`, `detail.php` |
| **UC-04** | Rekomendasi/Peringkat penerima | `admin_dashboard.php` |
| **UC-05** | Kelola Data (edit + hapus) | `input.php`, `detail.php` |
| **UC-08** | Riwayat Penerima Bantuan per periode | `admin_riwayat.php` |
| — | Verifikasi Petugas (approve/reject) | `admin_verifikasi.php` |

---

## Workflow

### Petugas
1. **Daftar** (`register.php`): NIK, nama, email, password, **pilih kelurahan**.
2. Status awal **menunggu verifikasi** (`pending.php`).
3. Admin **menyetujui** → bisa masuk dashboard petugas.
   (Ditolak → layar "Ditolak".)
4. **Input data** rumah tangga (`input.php`): nama KK, NIK KK, alamat, pendapatan,
   tanggungan, kondisi rumah, aset, indikator sosial (semua dropdown/angka).
5. Sistem hitung **skor + kategori** otomatis.
6. **Lihat daftar** (`status.php`) — hanya kelurahannya; **detail** (`detail.php`)
   menampilkan faktor %; bisa **Edit/Hapus**.

### Admin
1. **Login** (`login.php`).
2. **Verifikasi petugas** (`admin_verifikasi.php`): Terima / Tolak / Nonaktifkan.
3. **Dashboard** (`admin_dashboard.php`): peringkat semua rumah tangga se-Sukolilo,
   **filter per kelurahan**, detail + penjelasan faktor.
4. **Riwayat** (`admin_riwayat.php`): penerima per periode (berjalan + historis),
   **filter kelurahan**.

---

## Struktur Proyek

```
FinalProject_KPPL/            (root repo = root deploy)
├── index.php                Beranda publik (landing + CTA)
├── register.php             Daftar petugas (+ pilih kelurahan)
├── login.php / logout.php   Autentikasi (NIK + Email + Password)
├── pending.php              Layar status verifikasi petugas
├── status.php               Daftar data rumah tangga kelurahan (petugas)
├── input.php                Tambah / edit data rumah tangga (petugas)
├── detail.php               Detail skor + faktor + hapus (petugas)
├── akun.php                 Ubah email/password (petugas)
├── admin_dashboard.php      Peringkat + filter kelurahan (admin)
├── admin_verifikasi.php     Verifikasi petugas (admin)
├── admin_riwayat.php        Riwayat penerima per periode (admin)
├── lib.php                  INTI: PDO, MCDM, validasi, auth, CRUD
├── partials.php             Header / navbar / flash / footer
├── config.php               Konfigurasi DB (EDIT saat deploy)
├── assets/css/app.css  assets/js/app.js
├── database.sql             Skema + seed (impor ke phpMyAdmin)
├── docs/                    Dokumentasi (tidak diunggah ke htdocs)
└── legacy/                  Prototipe Python lama (tidak diunggah)
```

---

## Basis Data

| Tabel | Isi |
|-------|-----|
| `users` | Petugas & admin. `nik`/`email` unik, `role`, `kelurahan`, `status` (pending/approved/rejected). |
| `households` | Data rumah tangga: `petugas_id`, `kelurahan`, `nama_kk`, `nik_kk` (unik), `alamat`, 5 kriteria, `skor`, `kategori`, `faktor_json`. |
| `periode_bantuan` | Periode penyaluran (mis. "Januari 2026"). |
| `penerima` | Penerima historis per periode (+ `kelurahan`). |

---

## Mekanisme Skor (MCDM)

Skor 0–100, makin tinggi makin prioritas.

| Kriteria | Bobot |
|----------|-------|
| Pendapatan Bulanan | 0.35 |
| Jumlah Tanggungan | 0.20 |
| Kondisi Tempat Tinggal | 0.20 |
| Kepemilikan Aset | 0.15 |
| Indikator Sosial | 0.10 |

Normalisasi: pendapatan ≤0→100, ≥Rp5.000.000→0, selainnya `(1−p/5.000.000)×100`;
tanggungan ≤0→0, ≥7→100, selainnya `(t/7)×100`; kondisi Rusak Berat 100 / Rusak
Sedang 60 / Layak 10; aset Rendah 100 / Sedang 50 / Tinggi 10; indikator
Disabilitas 100 / Sakit Kronis 90 / Lansia 80 / Anak Putus Sekolah 70 / Tidak Ada 10.
**Kategori:** >75 Sangat Layak · 50–75 Layak · <50 Kurang Layak.

---

## Validasi Input

| Field | Aturan |
|-------|--------|
| NIK (petugas & KK) | 16 digit angka; unik |
| Nama | tidak boleh mengandung angka |
| Email | format valid; unik |
| Password | minimal 8 karakter |
| Pendapatan | angka saja (format rupiah otomatis), maks Rp 1.000.000.000 |
| Jumlah tanggungan | bilangan bulat, maks 20 |
| Kondisi/Aset/Indikator/Kelurahan | wajib dipilih dari **dropdown** |

JS hanya bantu format di klien; validasi otoritatif di server.

---

## Deploy ke InfinityFree

1. **Buat database MySQL** di Control Panel → catat host, nama DB, user, password.
2. **phpMyAdmin** → pilih DB → tab **Import** → unggah `database.sql` → **Go**.
3. **Edit `config.php`** → isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
4. **Upload** semua `*.php` + folder `assets/` ke `htdocs/`.
   **Jangan** upload: `database.sql`, `docs/`, `legacy/`, `.git/`.
5. Buka domain.

### Jalankan lokal (XAMPP/Laragon)
Buat DB `bansos`, impor `database.sql`, biarkan `config.php` default
(`localhost`/`root`/kosong), taruh berkas di `htdocs/`, buka `http://localhost/`.

---

## Keamanan
- Password di-hash `password_hash()` (bcrypt); login `password_verify()`.
- Prepared statements (anti SQL injection); output di-escape (anti XSS).
- Token CSRF pada semua form POST; `session_regenerate_id()` setelah login.
- Scoping: petugas hanya akses data kelurahannya; akun aktif setelah diverifikasi admin.

---

## Catatan
Sistem hanya memberi **rekomendasi**; keputusan akhir distribusi tetap pada
pengurus/pihak berwenang. Prototipe Python (FastAPI) lama diarsipkan di `legacy/`
(lihat `docs/DOKUMENTASI_LEGACY.md`).
