"""Unit test validasi input data rumah tangga (UC-01, Eksepsi E1)."""

import pytest

from app.models.schemas import ValidationError, validate_warga_payload


def _valid_payload() -> dict:
    return {
        "nama_kepala_keluarga": "Budi Santoso",
        "pendapatan_bulanan": 1500000,
        "jumlah_tanggungan": 3,
        "kondisi_tempat_tinggal": "Rusak Sedang",
        "kepemilikan_aset": "Rendah",
        "indikator_tambahan": "Lansia",
    }


def test_payload_valid_dinormalisasi():
    hasil = validate_warga_payload(_valid_payload())
    assert hasil["nama_kepala_keluarga"] == "Budi Santoso"
    assert hasil["pendapatan_bulanan"] == 1500000.0
    assert hasil["jumlah_tanggungan"] == 3


def test_nama_kosong_ditolak():
    data = _valid_payload()
    data["nama_kepala_keluarga"] = "   "
    with pytest.raises(ValidationError) as exc:
        validate_warga_payload(data)
    assert exc.value.field == "nama_kepala_keluarga"


def test_pendapatan_kosong_ditolak():
    data = _valid_payload()
    data["pendapatan_bulanan"] = ""
    with pytest.raises(ValidationError) as exc:
        validate_warga_payload(data)
    assert exc.value.field == "pendapatan_bulanan"


def test_pendapatan_negatif_ditolak():
    data = _valid_payload()
    data["pendapatan_bulanan"] = -100
    with pytest.raises(ValidationError):
        validate_warga_payload(data)


def test_pendapatan_non_numerik_ditolak():
    data = _valid_payload()
    data["pendapatan_bulanan"] = "abc"
    with pytest.raises(ValidationError):
        validate_warga_payload(data)


def test_tanggungan_negatif_ditolak():
    data = _valid_payload()
    data["jumlah_tanggungan"] = -1
    with pytest.raises(ValidationError) as exc:
        validate_warga_payload(data)
    assert exc.value.field == "jumlah_tanggungan"


def test_kondisi_tidak_sah_ditolak():
    data = _valid_payload()
    data["kondisi_tempat_tinggal"] = "Mewah"
    with pytest.raises(ValidationError) as exc:
        validate_warga_payload(data)
    assert exc.value.field == "kondisi_tempat_tinggal"


def test_aset_tidak_sah_ditolak():
    data = _valid_payload()
    data["kepemilikan_aset"] = "Banyak"
    with pytest.raises(ValidationError):
        validate_warga_payload(data)


def test_indikator_tidak_sah_ditolak():
    data = _valid_payload()
    data["indikator_tambahan"] = "Random"
    with pytest.raises(ValidationError):
        validate_warga_payload(data)
