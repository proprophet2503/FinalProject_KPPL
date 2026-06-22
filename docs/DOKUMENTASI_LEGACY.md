# Dokumentasi — Versi Legacy (FastAPI + Python)

Prototipe **awal** Sistem Prioritas Penerima Bantuan. Berbasis Python/FastAPI,
diarsipkan di folder `legacy/`. **Tidak** di-deploy ke InfinityFree (PHP-incompatible)
dan **tidak** disertakan saat upload ke `htdocs/`. Disimpan sebagai rujukan logika
dan riwayat pengembangan.

> Versi produksi terbaru: [DOKUMENTASI_INFINITYFREE.md](DOKUMENTASI_INFINITYFREE.md).
> Panduan menjalankan asli: [../legacy/README_python.md](../legacy/README_python.md).

---

## 1. Ringkasan

| Aspek | Keterangan |
|---|---|
| **Backend** | FastAPI (Python) — REST API + render Jinja2 |
| **Frontend** | HTML5 + Tailwind CSS (CDN) + Vanilla JS (Fetch API), template Jinja2 |
| **Database** | SQLite (file `data/bansos.db`, dibuat otomatis saat start) |
| **AI Engine** | scikit-learn Random Forest (*cross-check*) + scoring MCDM transparan |
| **Status** | Diarsipkan — digantikan versi PHP/MySQL untuk InfinityFree |

Mencakup use case yang sama: **UC-01**, **UC-02/UC-03**, **UC-08**.

---

## 2. Struktur (`legacy/`)

```
legacy/
├── app/
│   ├── main.py            Entry FastAPI; halaman HTML; lifespan init DB
│   ├── database.py        Akses SQLite (warga, hasil_klasifikasi, riwayat_bantuan)
│   ├── ai_model.py        Scoring MCDM + Random Forest + generator dataset
│   ├── models/schemas.py  Pydantic + validasi domain (UC-01)
│   ├── routes/api.py      Endpoint REST ketiga fitur
│   ├── templates/         index, input, hasil, detail, riwayat (Jinja2 + Tailwind)
│   └── static/            js/app.js (BansosApp), css/app.css
├── data/                  bansos.db, dummy_dataset.csv, model_rf.pkl (generated)
├── tests/                 pytest: validasi, scoring, API
├── requirements.txt
├── pytest.ini
└── README_python.md       Panduan menjalankan asli
```

---

## 3. Endpoint & Halaman

| Use Case | Halaman (Jinja2) | Endpoint REST |
|---|---|---|
| Dashboard | `/` | — |
| UC-01 Input Data | `/input` | `POST /api/warga` (validasi E1, deteksi duplikat E2) |
| UC-02 Hasil Klasifikasi | `/hasil` | `GET /api/hasil`, `GET /api/hasil/{id}` |
| UC-03 Penjelasan | `/detail/{id}` | termasuk di detail (kontribusi faktor) |
| UC-08 Riwayat | `/riwayat` | `GET /api/riwayat/periode`, `GET /api/riwayat?periode=` |

Dokumentasi API otomatis: `http://127.0.0.1:8000/docs` (Swagger UI bawaan FastAPI).

---

## 4. Mesin Skor

Logika MCDM **identik** dengan versi PHP (bobot 0.35 / 0.20 / 0.20 / 0.15 / 0.10;
kategori >75 / 50–75 / <50). Versi PHP terbaru adalah port 1:1 dari `ai_model.py`
ini. Random Forest (scikit-learn) dilatih pada `data/dummy_dataset.csv` yang
dilabeli skor MCDM sebagai *ground-truth*, disimpan ke `data/model_rf.pkl`, dan
dipakai hanya sebagai *cross-check* — scoring tetap berjalan tanpa file `.pkl`.

---

## 5. Cara Menjalankan

```bash
cd FinalProject_KPPL/legacy
python -m venv .venv
source .venv/bin/activate          # Windows: .venv\Scripts\activate
pip install -r requirements.txt

# (opsional) latih ulang model & dataset dummy
python -m app.ai_model             # -> data/dummy_dataset.csv, data/model_rf.pkl

# jalankan backend
uvicorn app.main:app --reload      # buka http://127.0.0.1:8000

# test
pytest
```

> Catatan: perintah `app.main:app` mengasumsikan dijalankan dari dalam `legacy/`
> (paket `app` berada di sana setelah restrukturisasi). Path data (`data/...`)
> relatif terhadap *working directory* saat server dijalankan.

Cakupan test: validasi input (UC-01/E1/E2), logika scoring & kategori (UC-02),
integrasi endpoint API ketiga fitur.

---

## 6. Mengapa Diganti ke PHP/MySQL?

| Kebutuhan | Kendala FastAPI di InfinityFree | Solusi versi terbaru |
|---|---|---|
| Hosting gratis | InfinityFree hanya mendukung PHP, bukan Python/ASGI | PHP 8 native |
| Tanpa shell/Composer | FastAPI butuh `pip install` + proses Uvicorn | Tanpa dependency eksternal |
| Persistensi multi-user | SQLite file kurang ideal di shared hosting | MySQL (phpMyAdmin) |

Logika domain (MCDM, validasi, alur use case) dipertahankan identik; hanya
lapisan teknologi yang berubah. Prototipe ini tetap berguna untuk pengujian lokal
dan sebagai acuan parity skor.
