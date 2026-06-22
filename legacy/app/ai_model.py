"""
Mesin AI penilai prioritas penerima bantuan.

Pendekatan: MCDM (Multi-Criteria Decision Making) berbobot yang transparan
untuk menghasilkan skor 0-100 + penjelasan per-fitur (UC-02/UC-03), DITAMBAH
model Random Forest scikit-learn (UC requirement) yang dilatih pada dataset
dummy ber-label sama sehingga kategori model dan kategori ambang batas konsisten.

Skor lebih TINGGI = kebutuhan lebih besar = prioritas lebih tinggi.

Kategori (sesuai DPPL UC-02 & CODING INSTRUCTIONS):
- skor > 75            -> "Sangat Layak"
- 50 <= skor <= 75     -> "Layak"
- skor < 50            -> "Kurang Layak"

Jalankan `python -m app.ai_model` untuk membuat ulang dataset CSV + model .pkl.
"""

from __future__ import annotations

import json
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
DATA_DIR = BASE_DIR / "data"
DATASET_PATH = DATA_DIR / "dummy_dataset.csv"
MODEL_PATH = DATA_DIR / "model_rf.pkl"

# --- Domain nilai kategorikal (validasi dipakai juga oleh schemas) -----------
KONDISI_RUMAH = ("Rusak Berat", "Rusak Sedang", "Layak")
KEPEMILIKAN_ASET = ("Rendah", "Sedang", "Tinggi")
INDIKATOR_TAMBAHAN = (
    "Disabilitas",
    "Sakit Kronis",
    "Lansia",
    "Anak Putus Sekolah",
    "Tidak Ada",
)

# --- Parameter normalisasi pendapatan & tanggungan (skala mikro RT) ----------
PENDAPATAN_CEILING = 5_000_000.0  # >= ini dianggap tidak butuh (need = 0)
TANGGUNGAN_CAP = 7  # >= ini dianggap kebutuhan maksimum (need = 100)

# --- Bobot kriteria, total = 1.0 --------------------------------------------
WEIGHTS = {
    "pendapatan_bulanan": 0.35,
    "jumlah_tanggungan": 0.20,
    "kondisi_tempat_tinggal": 0.20,
    "kepemilikan_aset": 0.15,
    "indikator_tambahan": 0.10,
}

# --- Peta sub-skor kebutuhan (0-100) untuk fitur ordinal/kategorikal ---------
_KONDISI_SCORE = {"Rusak Berat": 100.0, "Rusak Sedang": 60.0, "Layak": 10.0}
_ASET_SCORE = {"Rendah": 100.0, "Sedang": 50.0, "Tinggi": 10.0}
_INDIKATOR_SCORE = {
    "Disabilitas": 100.0,
    "Sakit Kronis": 90.0,
    "Lansia": 80.0,
    "Anak Putus Sekolah": 70.0,
    "Tidak Ada": 10.0,
}

# Label fitur ramah-pengguna untuk penjelasan UI (UC-03).
_FITUR_LABEL = {
    "pendapatan_bulanan": "Pendapatan Bulanan",
    "jumlah_tanggungan": "Jumlah Tanggungan",
    "kondisi_tempat_tinggal": "Kondisi Tempat Tinggal",
    "kepemilikan_aset": "Kepemilikan Aset",
    "indikator_tambahan": "Indikator Sosial",
}


def _need_pendapatan(pendapatan: float) -> float:
    """Sub-skor kebutuhan dari pendapatan (inverse, di-clamp ke 0-100)."""
    if pendapatan <= 0:
        return 100.0
    if pendapatan >= PENDAPATAN_CEILING:
        return 0.0
    return round((1.0 - pendapatan / PENDAPATAN_CEILING) * 100.0, 2)


def _need_tanggungan(jumlah: int) -> float:
    """Sub-skor kebutuhan dari jumlah tanggungan (di-clamp ke 0-100)."""
    if jumlah <= 0:
        return 0.0
    if jumlah >= TANGGUNGAN_CAP:
        return 100.0
    return round((jumlah / TANGGUNGAN_CAP) * 100.0, 2)


def _subscores(warga: dict) -> dict[str, float]:
    """Hitung sub-skor 0-100 tiap fitur. Nilai kategorikal tak dikenal -> 0."""
    return {
        "pendapatan_bulanan": _need_pendapatan(float(warga["pendapatan_bulanan"])),
        "jumlah_tanggungan": _need_tanggungan(int(warga["jumlah_tanggungan"])),
        "kondisi_tempat_tinggal": _KONDISI_SCORE.get(
            warga["kondisi_tempat_tinggal"], 0.0
        ),
        "kepemilikan_aset": _ASET_SCORE.get(warga["kepemilikan_aset"], 0.0),
        "indikator_tambahan": _INDIKATOR_SCORE.get(warga["indikator_tambahan"], 0.0),
    }


def kategori_dari_skor(skor: float) -> str:
    """Petakan skor numerik ke kategori kelayakan sesuai ambang DPPL."""
    if skor > 75:
        return "Sangat Layak"
    if skor >= 50:
        return "Layak"
    return "Kurang Layak"


def compute_priority(warga: dict) -> dict:
    """Inferensi utama: skor 0-100, kategori, dan faktor penjelasan.

    Mengembalikan dict:
      {
        "skor_prioritas": float,
        "kategori_kelayakan": str,
        "faktor_penjelasan": [
           {"fitur": str, "label": str, "nilai": <raw>,
            "kontribusi": float, "kontribusi_persen": float}, ...
        ]  # terurut kontribusi menurun
      }
    """
    subs = _subscores(warga)
    kontribusi = {f: round(subs[f] * WEIGHTS[f], 4) for f in WEIGHTS}
    skor = round(sum(kontribusi.values()), 2)  # bobot total 1.0 -> skala 0-100
    total_kontrib = sum(kontribusi.values()) or 1.0

    faktor = [
        {
            "fitur": f,
            "label": _FITUR_LABEL[f],
            "nilai": warga[f],
            "kontribusi": kontribusi[f],
            "kontribusi_persen": round(kontribusi[f] / total_kontrib * 100.0, 1),
        }
        for f in WEIGHTS
    ]
    faktor.sort(key=lambda x: x["kontribusi"], reverse=True)

    return {
        "skor_prioritas": skor,
        "kategori_kelayakan": kategori_dari_skor(skor),
        "faktor_penjelasan": faktor,
    }


def faktor_to_json(faktor: list[dict]) -> str:
    """Serialisasi daftar faktor ke JSON string untuk kolom DB."""
    return json.dumps(faktor, ensure_ascii=False)


# ============================================================================
# Bagian Random Forest (memenuhi kebutuhan scikit-learn) — opsional saat infer.
# ============================================================================

def _encode_row(warga: dict) -> list[float]:
    """Encode satu warga ke vektor fitur numerik untuk model RF."""
    return [
        float(warga["pendapatan_bulanan"]),
        float(warga["jumlah_tanggungan"]),
        float(KONDISI_RUMAH.index(warga["kondisi_tempat_tinggal"])),
        float(KEPEMILIKAN_ASET.index(warga["kepemilikan_aset"])),
        float(INDIKATOR_TAMBAHAN.index(warga["indikator_tambahan"])),
    ]


def generate_dataset(n: int = 600, seed: int = 42) -> list[dict]:
    """Buat dataset dummy ber-label memakai skor MCDM sebagai ground-truth.

    Disimpan ke CSV bila pandas tersedia; selalu mengembalikan list of dict.
    """
    import random

    rng = random.Random(seed)
    rows: list[dict] = []
    for _ in range(n):
        warga = {
            "pendapatan_bulanan": float(rng.randint(0, 7_000_000)),
            "jumlah_tanggungan": rng.randint(0, 8),
            "kondisi_tempat_tinggal": rng.choice(KONDISI_RUMAH),
            "kepemilikan_aset": rng.choice(KEPEMILIKAN_ASET),
            "indikator_tambahan": rng.choice(INDIKATOR_TAMBAHAN),
        }
        hasil = compute_priority(warga)
        warga["skor_prioritas"] = hasil["skor_prioritas"]
        warga["kategori_kelayakan"] = hasil["kategori_kelayakan"]
        rows.append(warga)

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    try:
        import pandas as pd

        pd.DataFrame(rows).to_csv(DATASET_PATH, index=False)
    except ImportError:
        pass
    return rows


def train_and_save(n: int = 600, seed: int = 42) -> float:
    """Latih RandomForest pada dataset dummy, simpan .pkl, kembalikan akurasi.

    Memerlukan scikit-learn + joblib. Dipanggil via `python -m app.ai_model`.
    """
    from sklearn.ensemble import RandomForestClassifier
    from sklearn.model_selection import train_test_split
    import joblib

    rows = generate_dataset(n=n, seed=seed)
    X = [_encode_row(r) for r in rows]
    y = [r["kategori_kelayakan"] for r in rows]

    X_tr, X_te, y_tr, y_te = train_test_split(
        X, y, test_size=0.2, random_state=seed, stratify=y
    )
    clf = RandomForestClassifier(n_estimators=120, random_state=seed)
    clf.fit(X_tr, y_tr)
    acc = float(clf.score(X_te, y_te))

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    joblib.dump(clf, MODEL_PATH)
    return acc


_MODEL_CACHE = None


def classify_with_model(warga: dict) -> str | None:
    """Prediksi kategori via model RF tersimpan. None bila model tak ada.

    Dipakai sebagai cross-check; output utama tetap dari compute_priority.
    """
    global _MODEL_CACHE
    if _MODEL_CACHE is None:
        if not MODEL_PATH.exists():
            return None
        try:
            import joblib

            _MODEL_CACHE = joblib.load(MODEL_PATH)
        except Exception:
            return None
    return str(_MODEL_CACHE.predict([_encode_row(warga)])[0])


if __name__ == "__main__":
    acc = train_and_save()
    print(f"Dataset -> {DATASET_PATH}")
    print(f"Model   -> {MODEL_PATH}")
    print(f"Akurasi RandomForest (test split): {acc:.3f}")
