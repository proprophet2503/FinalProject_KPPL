"""Unit test logika scoring AI (UC-02): skor 0-100, kategori, penjelasan."""

from app.ai_model import (
    compute_priority,
    kategori_dari_skor,
    WEIGHTS,
)


def test_bobot_total_satu():
    # Bobot harus berjumlah 1.0 agar skor berskala 0-100.
    assert round(sum(WEIGHTS.values()), 6) == 1.0


def test_skor_dalam_rentang_0_100():
    warga = {
        "pendapatan_bulanan": 800000,
        "jumlah_tanggungan": 4,
        "kondisi_tempat_tinggal": "Rusak Berat",
        "kepemilikan_aset": "Rendah",
        "indikator_tambahan": "Disabilitas",
    }
    hasil = compute_priority(warga)
    assert 0.0 <= hasil["skor_prioritas"] <= 100.0


def test_kondisi_paling_miskin_skor_tinggi():
    miskin = {
        "pendapatan_bulanan": 0,
        "jumlah_tanggungan": 8,
        "kondisi_tempat_tinggal": "Rusak Berat",
        "kepemilikan_aset": "Rendah",
        "indikator_tambahan": "Disabilitas",
    }
    mampu = {
        "pendapatan_bulanan": 6000000,
        "jumlah_tanggungan": 0,
        "kondisi_tempat_tinggal": "Layak",
        "kepemilikan_aset": "Tinggi",
        "indikator_tambahan": "Tidak Ada",
    }
    assert compute_priority(miskin)["skor_prioritas"] > compute_priority(mampu)["skor_prioritas"]


def test_keluarga_paling_miskin_sangat_layak():
    miskin = {
        "pendapatan_bulanan": 0,
        "jumlah_tanggungan": 8,
        "kondisi_tempat_tinggal": "Rusak Berat",
        "kepemilikan_aset": "Rendah",
        "indikator_tambahan": "Disabilitas",
    }
    hasil = compute_priority(miskin)
    assert hasil["skor_prioritas"] > 75
    assert hasil["kategori_kelayakan"] == "Sangat Layak"


def test_keluarga_mampu_kurang_layak():
    mampu = {
        "pendapatan_bulanan": 6000000,
        "jumlah_tanggungan": 0,
        "kondisi_tempat_tinggal": "Layak",
        "kepemilikan_aset": "Tinggi",
        "indikator_tambahan": "Tidak Ada",
    }
    hasil = compute_priority(mampu)
    assert hasil["skor_prioritas"] < 50
    assert hasil["kategori_kelayakan"] == "Kurang Layak"


def test_ambang_kategori():
    assert kategori_dari_skor(90) == "Sangat Layak"
    assert kategori_dari_skor(75.01) == "Sangat Layak"
    assert kategori_dari_skor(75) == "Layak"
    assert kategori_dari_skor(50) == "Layak"
    assert kategori_dari_skor(49.99) == "Kurang Layak"
    assert kategori_dari_skor(0) == "Kurang Layak"


def test_faktor_penjelasan_lengkap_dan_terurut():
    warga = {
        "pendapatan_bulanan": 500000,
        "jumlah_tanggungan": 5,
        "kondisi_tempat_tinggal": "Rusak Berat",
        "kepemilikan_aset": "Rendah",
        "indikator_tambahan": "Sakit Kronis",
    }
    faktor = compute_priority(warga)["faktor_penjelasan"]
    assert len(faktor) == len(WEIGHTS)
    # Terurut menurun berdasarkan kontribusi.
    kontrib = [f["kontribusi"] for f in faktor]
    assert kontrib == sorted(kontrib, reverse=True)
    # Total persen kontribusi mendekati 100.
    total = sum(f["kontribusi_persen"] for f in faktor)
    assert 99.0 <= total <= 101.0
