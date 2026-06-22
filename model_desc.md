# Dokumentasi Model — Klasifikasi Kelayakan (Decision Tree → Rule-Based)

Dokumen ini menjelaskan pelatihan model **Decision Tree** dan **ekstraksinya**
menjadi aturan `if/else` yang dipakai sistem untuk menentukan **kategori
kelayakan** (Sangat Layak / Layak / Kurang Layak).

> **Pembagian peran penilaian:**
> - **Skor prioritas (0–100), peringkat, dan penjelasan faktor (%)** → tetap memakai **MCDM** berbobot (`lib.php` → `compute_priority`).
> - **Kategori kelayakan** → ditentukan **Decision Tree** yang diekstrak ke aturan (`classifier.php`).

---

## 1. Dataset
- **Berkas:** `legacy/data/dummy_dataset.csv` (600 baris).
- **Fitur (X):** `pendapatan_bulanan` (numerik), `jumlah_tanggungan` (numerik),
  `kondisi_tempat_tinggal`, `kepemilikan_aset`, `indikator_tambahan` (kategorikal).
- **Target (y):** `kategori_kelayakan` (3 kelas).
- Catatan: label pada dataset awalnya diturunkan dari skor MCDM, sehingga pohon
  belajar mereplikasi pola ambang MCDM melalui fitur — bukan sumber kebenaran
  eksternal.

## 2. Pra-pemrosesan (encoding ordinal)
Kategorikal dikodekan ordinal (kode besar = makin membutuhkan). Encoding ini
**identik** di Python (`train_tree.py`) dan PHP (`classifier.php`):

| Fitur | Encoding |
|-------|----------|
| `kondisi_tempat_tinggal` | Layak=0, Rusak Sedang=1, Rusak Berat=2 |
| `kepemilikan_aset` | Tinggi=0, Sedang=1, Rendah=2 |
| `indikator_tambahan` | Tidak Ada=0, Anak Putus Sekolah=1, Lansia=2, Sakit Kronis=3, Disabilitas=4 |

## 3. Konfigurasi Pelatihan
- Algoritme: `DecisionTreeClassifier` (scikit-learn 1.6).
- Hyperparameter: `max_depth=5`, `min_samples_leaf=10`, `random_state=42`.
- Split: 75% train / 25% test, `stratify=y`.
- Skrip: `legacy/data/train_tree.py` (jalankan `python3 legacy/data/train_tree.py`).

## 4. Hasil Evaluasi
- Pohon: **depth 5**, **21 leaf**.
- Akurasi: **train 0.86 · test 0.82 · seluruh data 0.85**.

Classification report (test, n=150):

| Kelas | precision | recall | f1 | support |
|-------|-----------|--------|----|---------|
| Kurang Layak | 0.83 | 0.88 | 0.85 | 73 |
| Layak | 0.79 | 0.80 | 0.79 | 65 |
| Sangat Layak | 1.00 | 0.58 | 0.74 | 12 |
| **accuracy** | | | **0.82** | 150 |

> Kelas "Sangat Layak" recall rendah (data minoritas) — pohon dangkal cenderung
> menggabungkannya ke "Layak". Dapat diperbaiki dengan menambah data atau
> `class_weight='balanced'` bila diperlukan.

## 5. Pohon Hasil Pelatihan (export_text)
```
|--- pendapatan_bulanan <= 3412803.50
|   |--- kondisi_code <= 1.50
|   |   |--- jumlah_tanggungan <= 5.50
|   |   |   |--- pendapatan_bulanan <= 2159501.50
|   |   |   |   |--- kondisi_code <= 0.50 → Kurang Layak
|   |   |   |   |--- kondisi_code >  0.50 → Layak
|   |   |   |--- pendapatan_bulanan >  2159501.50
|   |   |   |   |--- aset_code <= 1.50 → Kurang Layak
|   |   |   |   |--- aset_code >  1.50 → Layak
|   |   |--- jumlah_tanggungan >  5.50 → Layak (semua cabang)
|   |--- kondisi_code >  1.50
|   |   |--- pendapatan_bulanan <= 1486906.50
|   |   |   |--- aset_code <= 0.50 → Layak
|   |   |   |--- aset_code >  0.50 → Sangat Layak
|   |   |--- pendapatan_bulanan >  1486906.50 → Layak
|--- pendapatan_bulanan >  3412803.50
|   |--- jumlah_tanggungan <= 5.50 → Kurang Layak (semua cabang)
|   |--- jumlah_tanggungan >  5.50
|   |   |--- kondisi_code <= 1.50
|   |   |   |--- aset_code <= 1.50 → Kurang Layak
|   |   |   |--- aset_code >  1.50 → Layak
|   |   |--- kondisi_code >  1.50 → Layak
```

**Interpretasi singkat:** pendapatan adalah pemisah utama (akar). Pendapatan
rendah (≤ ~3,41 jt) cenderung Layak/Sangat Layak terutama bila kondisi rumah
buruk; pendapatan tinggi dengan tanggungan sedikit hampir selalu Kurang Layak.
Pola ini konsisten dengan bobot MCDM (pendapatan 0.35, kondisi & tanggungan 0.20).

## 6. Ekstraksi ke Rule-Based (PHP)
`train_tree.py` menelusuri `clf.tree_` secara rekursif dan meng-*generate* fungsi
PHP `classify_kategori_tree(array $f)` — setiap simpul internal menjadi `if/else`
pada ambang `<=`, setiap daun menjadi `return '<kelas>'`. Hasilnya disalin ke
**`classifier.php`** (1:1, tanpa edit manual).

Alur di aplikasi:
```
data rumah tangga (5 field)
   → tree_features()         // map kategori → kode ordinal
   → classify_kategori_tree()// aturan if/else hasil ekstraksi
   → kategori kelayakan
```
Integrasi: `lib.php` memuat `classifier.php`; `compute_priority()` memakai
`kategori_decision_tree($w)` untuk kategori (skor & faktor tetap MCDM).

## 7. Verifikasi (data seed)
Tiga record seed menghasilkan kategori yang konsisten antara MCDM lama dan pohon:

| Pendapatan | Tanggungan | Kondisi | Aset | Indikator | Kategori (DT) |
|-----------:|-----------:|---------|------|-----------|---------------|
| 800.000 | 4 | Rusak Sedang | Rendah | Anak Putus Sekolah | Layak |
| 1.500.000 | 2 | Layak | Sedang | Tidak Ada | Kurang Layak |
| 300.000 | 6 | Rusak Berat | Rendah | Disabilitas | Sangat Layak |

## 8. Regenerasi Model
Bila dataset atau hyperparameter berubah:
1. `python3 legacy/data/train_tree.py`
2. Salin blok `=== PHP ===` ke `classifier.php` (fungsi `classify_kategori_tree`).
3. Perbarui metrik & pohon di dokumen ini.
4. Pastikan encoding di `classifier.php` tetap sama dengan `train_tree.py`.

## 9. Keterbatasan
- Pohon mereplikasi pola MCDM pada data dummy; bukan model dari data lapangan riil.
- Kategori (DT) dapat **sesekali berbeda** dari ambang skor MCDM (>75 / 50–75 / <50),
  karena keduanya mekanisme berbeda. Penentuan "penerima" pada riwayat tetap
  memakai **skor ≥ 50** (bukan kategori), sehingga konsisten untuk pemeringkatan.
- Untuk akurasi lebih tinggi: tambah data, naikkan `max_depth`, atau seimbangkan kelas.
