# Dokumentasi — Versi InfinityFree (PHP + MySQL)

Versi produksi/terbaru dari **Sistem Prioritas Penerima Bantuan Sosial**. Berjalan
di hosting gratis InfinityFree. Inilah versi yang menjadi isi repositori untuk
di-deploy.

> Untuk prototipe Python (FastAPI) yang lama, lihat
> [DOKUMENTASI_LEGACY.md](DOKUMENTASI_LEGACY.md).

---

## 1. Ringkasan

| Aspek | Keterangan |
|---|---|
| **Backend** | PHP 8 *native* (PDO + *prepared statements*) — tanpa framework, tanpa Composer |
| **Frontend** | HTML + CSS + JavaScript murni (HTML ditanam di dalam `.php`) |
| **Database** | MySQL (impor `database.sql` via phpMyAdmin) |
| **Hosting** | InfinityFree (gratis), berkas ke `htdocs/` |
| **Model skor** | MCDM berbobot transparan (port 1:1 dari `legacy/app/ai_model.py`) |
| **Peran** | Warga (daftar mandiri) & Admin (akun *preprogrammed*) |

Tiga fitur utama (use case): **UC-01** Input Data Rumah Tangga, **UC-02 & UC-03**
Hasil Klasifikasi & Penjelasan, **UC-08** Riwayat Penerima Bantuan. Pendukung:
**UC-04** peringkat semua warga.

---

## 2. Struktur Berkas

```
FinalProject_KPPL/                 (root repo = root deploy)
├── index.php              Beranda publik (landing: hero, 3 fitur, CTA)
├── register.php           Pendaftaran warga (data KTP) — UC-01
├── login.php / logout.php Autentikasi (NIK + Email + Password)
├── akun.php               Edit email, data rumah tangga, foto — UC-01/UC-05
├── status.php             Skor + kategori + faktor % milik sendiri — UC-02/UC-03
├── admin_dashboard.php    Peringkat & status semua warga — UC-04
├── admin_riwayat.php      Riwayat penerima per periode — UC-08
├── lib.php                INTI: PDO, scoring MCDM, validasi, auth, upload
├── partials.php           Header / navbar / flash / footer bersama
├── config.php             Konfigurasi DB & konstanta (EDIT saat deploy)
├── assets/
│   ├── css/app.css        Seluruh gaya tampilan
│   └── js/app.js          Format rupiah + input angka
├── uploads/               Foto profil (eksekusi PHP dimatikan via .htaccess)
├── database.sql           Skema + seed admin + riwayat (impor ke phpMyAdmin)
├── docs/                  Dokumentasi (TIDAK diunggah ke htdocs)
└── legacy/                Prototipe Python lama (TIDAK diunggah ke htdocs)
```

**Diunggah ke `htdocs/`:** semua di root KECUALI `docs/`, `legacy/`, `.git/`, dan
`database.sql` (yang terakhir hanya diimpor lewat phpMyAdmin).

---

## 3. Arsitektur

Satu file PHP = satu halaman. Tidak ada *router* terpusat; URL langsung ke berkas
(`/login.php`, `/status.php`). Logika bersama dipusatkan:

```
Browser → <halaman>.php → require partials.php → require lib.php
                                                    ├─ db()                koneksi PDO (singleton)
                                                    ├─ auth (login/role)   sesi + bcrypt
                                                    ├─ validate_*()        validasi input
                                                    ├─ compute_priority()  skor MCDM
                                                    └─ save/get_assessment DB
```

- `partials.php` → `page_header()` / `page_footer()`: kerangka HTML + navbar sesuai peran.
- `lib.php` → semua fungsi domain. Memudahkan perawatan (ubah aturan di satu tempat).
- HTML dirender server-side oleh PHP lalu dikirim ke browser sebagai HTML jadi.

---

## 4. Basis Data (`database.sql`)

| Tabel | Fungsi |
|---|---|
| `users` | Akun (warga/admin) + data KTP. `nik` & `email` unik. `role` ENUM. |
| `assessments` | Data rumah tangga + hasil skor (1 baris per warga). `faktor_json` simpan kontribusi %. |
| `periode_bantuan` | Periode distribusi (mis. "Januari 2026"). |
| `penerima` | Daftar penerima historis per periode. |

Relasi: `users 1—1 assessments`, `periode_bantuan 1—* penerima`. Skrip bersifat
*drop-safe* (boleh diimpor ulang). Seed: 1 akun admin + 2 periode + penerima contoh.

### Akun admin default
- **NIK:** `0000000000000000`
- **Email:** `admin@bansos.local`
- **Password:** `Admin#2026`

> Ganti password admin setelah deploy (lihat §7).

---

## 5. Mekanisme Skor (MCDM)

Skor 0–100, makin tinggi makin prioritas.

| Kriteria | Bobot |
|---|---|
| Pendapatan Bulanan | 0.35 |
| Jumlah Tanggungan | 0.20 |
| Kondisi Tempat Tinggal | 0.20 |
| Kepemilikan Aset | 0.15 |
| Indikator Sosial | 0.10 |

Normalisasi: pendapatan ≤0→100, ≥Rp5.000.000→0, selainnya `(1−p/5.000.000)×100`;
tanggungan ≤0→0, ≥7→100, selainnya `(t/7)×100`; kondisi tempat tinggal Rusak
Berat 100 / Rusak Sedang 60 / Layak 10; aset Rendah 100 / Sedang 50 / Tinggi 10;
indikator Disabilitas 100 / Sakit Kronis 90 / Lansia 80 / Anak Putus Sekolah 70 /
Tidak Ada 10. Kategori: **>75 Sangat Layak**, **50–75 Layak**, **<50 Kurang Layak**.

---

## 6. Validasi Input (selaras UAT)

| Field | Aturan |
|---|---|
| NIK | wajib 16 digit angka, unik |
| Nama lengkap | tidak boleh mengandung angka |
| Email | format valid, unik |
| Password | minimal 8 karakter |
| Pendapatan | wajib, angka saja (format rupiah otomatis), maks Rp 1.000.000.000 |
| Jumlah tanggungan | bilangan bulat, maks 20 |

JavaScript hanya membantu format/pembatasan di sisi klien; **validasi otoritatif
tetap di server** (`validate_register`, `validate_assessment`).

---

## 7. Cara Deploy di InfinityFree

1. **Buat akun & hosting** di InfinityFree → buka **Control Panel**.
2. **Buat database MySQL** (*MySQL Databases*). Catat: nama DB, user, password, host.
3. **Impor skema:** phpMyAdmin → pilih DB → tab **Import** → unggah `database.sql` → **Go**.
4. **Edit `config.php`** → isi `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
5. **Upload berkas** isi root repo ke `htdocs/` KECUALI `docs/`, `legacy/`, `.git/`,
   `database.sql`. Pastikan `assets/` dan `uploads/` ikut.
6. Pastikan `uploads/` dapat ditulis (permission `755`).
7. Buka domain. Selesai.

### Jalankan lokal (XAMPP/Laragon)
1. Buat DB `bansos` di phpMyAdmin lokal, impor `database.sql`.
2. Biarkan `config.php` default (`localhost`, user `root`, password kosong).
3. Taruh berkas di `htdocs/`, buka `http://localhost/`.

### Ganti password admin
Update kolom `password_hash` di tabel `users` via phpMyAdmin dengan hash bcrypt
baru, atau ubah seed di `database.sql` sebelum impor.

---

## 8. Keamanan

- Password: `password_hash()`/`password_verify()` (bcrypt).
- Query: *prepared statements* (anti SQL injection).
- Form POST: token CSRF; output di-*escape* (anti XSS).
- `session_regenerate_id()` setelah login.
- Warga hanya dapat melihat datanya sendiri (query dibatasi `user_id` sesi).
- `uploads/`: eksekusi PHP dimatikan via `.htaccess`; upload divalidasi MIME
  (`finfo`), tipe (jpg/png), ukuran (≤2MB), nama acak.

---

## 9. Catatan tentang "tidak ada file `.html`"

Markup HTML **ada**, tertanam di `.php`. Tidak dibuat file `.html` terpisah karena
halaman bersifat dinamis (cek sesi, query MySQL, hitung skor) yang menuntut kode
berjalan di server lebih dulu. File `.html` statis tidak dapat melakukannya.
Alternatif `.html` murni hanya layak bila backend dilepas (port klien penuh +
`localStorage`, seperti demo legacy) atau dibangun API PHP terpisah yang dipanggil
via `fetch()` — keduanya menambah kompleksitas tanpa manfaat untuk skala RT ini.
