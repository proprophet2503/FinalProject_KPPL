"""
Skema Pydantic + fungsi validasi murni untuk data rumah tangga (UC-01).

`validate_warga_payload` adalah validasi tingkat-domain yang dapat diuji
terpisah dari FastAPI (dipakai unit test). Pydantic menangani validasi tipe
& enum di lapisan HTTP; fungsi ini menambah aturan bisnis (tidak kosong,
nilai non-negatif, kategori sah).
"""

from __future__ import annotations

from pydantic import BaseModel, Field

from app.ai_model import KEPEMILIKAN_ASET, INDIKATOR_TAMBAHAN, KONDISI_RUMAH

# Batas wajar input (mencegah data tidak masuk akal — UAT TC.007/TC.008).
MAX_PENDAPATAN = 1_000_000_000.0  # 1 miliar Rp/bulan: di atas ini = tidak wajar
MAX_TANGGUNGAN = 20  # >20 tanggungan dianggap tidak wajar untuk satu rumah tangga


class WargaInput(BaseModel):
    """Payload input data rumah tangga dari formulir UC-01."""

    nama_kepala_keluarga: str = Field(..., min_length=1, max_length=120)
    pendapatan_bulanan: float = Field(..., ge=0, le=MAX_PENDAPATAN)
    jumlah_tanggungan: int = Field(..., ge=0, le=MAX_TANGGUNGAN)
    kondisi_tempat_tinggal: str
    kepemilikan_aset: str
    indikator_tambahan: str


class ValidationError(Exception):
    """Eksepsi domain untuk kegagalan validasi (dipetakan ke HTTP 422/409)."""

    def __init__(self, pesan: str, field: str | None = None):
        super().__init__(pesan)
        self.pesan = pesan
        self.field = field


def validate_warga_payload(data: dict) -> dict:
    """Validasi & normalisasi payload warga. Raise ValidationError bila gagal.

    Aturan (Eksepsi E1 UC-01 = data wajib kosong/invalid):
    - nama tidak boleh kosong/whitespace
    - pendapatan >= 0 dan numerik
    - jumlah_tanggungan integer >= 0
    - kondisi/aset/indikator harus salah satu nilai domain yang sah

    Mengembalikan dict ter-normalisasi siap simpan.
    """
    if data is None:
        raise ValidationError("Payload kosong.")

    nama = str(data.get("nama_kepala_keluarga", "")).strip()
    if not nama:
        raise ValidationError(
            "Nama kepala keluarga wajib diisi.", field="nama_kepala_keluarga"
        )
    if any(ch.isdigit() for ch in nama):  # UAT TC.004
        raise ValidationError(
            "Nama tidak boleh ada angka.", field="nama_kepala_keluarga"
        )

    pendapatan_raw = data.get("pendapatan_bulanan", None)
    if pendapatan_raw is None or pendapatan_raw == "":
        raise ValidationError(
            "Pendapatan bulanan wajib diisi.", field="pendapatan_bulanan"
        )
    try:
        pendapatan = float(pendapatan_raw)
    except (TypeError, ValueError):
        raise ValidationError(
            "Pendapatan bulanan harus berupa angka.", field="pendapatan_bulanan"
        )
    if pendapatan < 0:
        raise ValidationError(
            "Pendapatan bulanan tidak boleh negatif.", field="pendapatan_bulanan"
        )

    tanggungan_raw = data.get("jumlah_tanggungan", None)
    if tanggungan_raw is None or tanggungan_raw == "":
        raise ValidationError(
            "Jumlah tanggungan wajib diisi.", field="jumlah_tanggungan"
        )
    try:
        tanggungan = int(tanggungan_raw)
    except (TypeError, ValueError):
        raise ValidationError(
            "Jumlah tanggungan harus berupa bilangan bulat.",
            field="jumlah_tanggungan",
        )
    if tanggungan < 0:
        raise ValidationError(
            "Jumlah tanggungan tidak boleh negatif.", field="jumlah_tanggungan"
        )

    kondisi = str(data.get("kondisi_tempat_tinggal", "")).strip()
    if kondisi not in KONDISI_RUMAH:
        raise ValidationError(
            f"Kondisi tempat tinggal harus salah satu dari: {', '.join(KONDISI_RUMAH)}.",
            field="kondisi_tempat_tinggal",
        )

    aset = str(data.get("kepemilikan_aset", "")).strip()
    if aset not in KEPEMILIKAN_ASET:
        raise ValidationError(
            f"Kepemilikan aset harus salah satu dari: {', '.join(KEPEMILIKAN_ASET)}.",
            field="kepemilikan_aset",
        )

    indikator = str(data.get("indikator_tambahan", "")).strip()
    if indikator not in INDIKATOR_TAMBAHAN:
        raise ValidationError(
            f"Indikator sosial harus salah satu dari: {', '.join(INDIKATOR_TAMBAHAN)}.",
            field="indikator_tambahan",
        )

    return {
        "nama_kepala_keluarga": nama,
        "pendapatan_bulanan": pendapatan,
        "jumlah_tanggungan": tanggungan,
        "kondisi_tempat_tinggal": kondisi,
        "kepemilikan_aset": aset,
        "indikator_tambahan": indikator,
    }
