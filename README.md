# Sistem AI Penentu Prioritas Penerima Bantuan (Skala Mikro)

Prototipe KPPL — Kelompok 1. Sistem berbasis AI yang membantu pengurus RT/RW
menentukan prioritas penerima bantuan sosial secara objektif dan transparan.

## Fitur Utama

| Use Case | Fitur | Endpoint |
|----------|-------|----------|
| UC-01 | Input Data Rumah Tangga (validasi E1, deteksi duplikat E2) | `POST /api/warga` |
| UC-02 | Melihat Hasil Klasifikasi (skor 0–100 + kategori) | `GET /api/hasil`, `GET /api/hasil/{id}` |
| UC-03 | Penjelasan Keputusan (kontribusi faktor dominan) | termasuk di detail |
| UC-08 | Riwayat Penerima Bantuan per periode | `GET /api/riwayat/periode`, `GET /api/riwayat?periode=` |

## Tech Stack

- **Backend:** FastAPI (Python)
- **Frontend:** HTML5 + Tailwind CSS (CDN) + Vanilla JS (Fetch API), via Jinja2
- **Database:** SQLite (file-based)
- **AI Engine:** scikit-learn (Random Forest) + scoring MCDM berbobot yang transparan

## Mesin AI

Skor prioritas (0–100) dihitung dengan pembobotan multi-kriteria (MCDM) yang
transparan sehingga setiap kontribusi faktor dapat dijelaskan (UC-03):

| Kriteria | Bobot |
|----------|-------|
| Pendapatan Bulanan | 0.35 |
| Jumlah Tanggungan | 0.20 |
| Kondisi Tempat Tinggal | 0.20 |
| Kepemilikan Aset | 0.15 |
| Indikator Sosial | 0.10 |

Kategori: **Sangat Layak** (skor > 75), **Layak** (50–75), **Kurang Layak** (< 50).

Model Random Forest dilatih pada dataset dummy (`data/dummy_dataset.csv`) yang
diberi label memakai skor MCDM sebagai ground-truth, lalu disimpan ke
`data/model_rf.pkl` sebagai cross-check klasifikasi.

## Struktur Proyek

```
app/
  __init__.py
  main.py            # entry FastAPI, halaman HTML, lifespan init DB
  database.py        # akses SQLite (warga, hasil_klasifikasi, riwayat_bantuan)
  ai_model.py        # scoring MCDM + Random Forest + generator dataset
  models/
    schemas.py       # Pydantic + validasi domain (UC-01)
  routes/
    api.py           # endpoint REST ketiga fitur
  templates/         # index, input, hasil, detail, riwayat (Jinja2 + Tailwind)
  static/
    js/app.js        # logika frontend (BansosApp)
    css/app.css
data/                # bansos.db, dummy_dataset.csv, model_rf.pkl (generated)
tests/               # pytest: validasi, scoring, API
```

## Cara Menjalankan

### 1. Siapkan environment & dependency

```bash
cd FinalProject_KPPL
python -m venv .venv
source .venv/bin/activate        # Windows: .venv\Scripts\activate
pip install -r requirements.txt
```

### 2. (Opsional) Latih ulang model & dataset dummy

```bash
python -m app.ai_model
# -> menghasilkan data/dummy_dataset.csv dan data/model_rf.pkl
```

Langkah ini opsional: scoring tetap berjalan tanpa file `.pkl` (Random Forest
hanya dipakai sebagai cross-check). DB dan seed riwayat dibuat otomatis saat
server start.

### 3. Jalankan backend

```bash
uvicorn app.main:app --reload
```

Buka `http://127.0.0.1:8000`:

- `/`         — Dashboard ringkasan
- `/input`    — Formulir input data rumah tangga (UC-01)
- `/hasil`    — Tabel hasil klasifikasi (UC-02)
- `/detail/{id}` — Detail skor + penjelasan faktor (UC-02/UC-03)
- `/riwayat`  — Riwayat penerima per periode (UC-08)

Dokumentasi API otomatis tersedia di `http://127.0.0.1:8000/docs`.

### 4. Menjalankan test

```bash
pytest
```

Cakupan test: validasi input (UC-01/E1/E2), logika scoring & kategori (UC-02),
dan integrasi endpoint API ketiga fitur.

## Deployment

- **Frontend statis:** halaman dapat di-host di GitHub Pages bila dipisah; pada
  prototipe ini frontend disajikan langsung oleh FastAPI (Jinja2) untuk
  kemudahan demo.
- **Backend:** dijalankan lokal via Uvicorn. CORS dibuka longgar untuk demo.

## Catatan

Sistem hanya memberi **rekomendasi**; keputusan akhir distribusi bantuan tetap
berada di tangan pengurus RT/RW (sesuai batasan fungsional DPPL §3.3.1).
