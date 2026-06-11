"""
Endpoint REST API untuk ketiga fitur utama prototipe.

Fitur 1 (UC-01): POST /api/warga          -> input + auto-klasifikasi
Fitur 2 (UC-02): GET  /api/hasil          -> daftar hasil klasifikasi
                 GET  /api/hasil/{id}      -> detail + faktor penjelasan
Fitur 3 (UC-08): GET  /api/riwayat/periode -> daftar periode
                 GET  /api/riwayat?periode= -> riwayat penerima per periode

Format respons konsisten: {"success": bool, "data": ..., "error": str|None}.
"""

from __future__ import annotations

import json

from fastapi import APIRouter, Query
from fastapi.responses import JSONResponse

from app import database as db
from app.ai_model import classify_with_model, compute_priority, faktor_to_json
from app.models.schemas import ValidationError, validate_warga_payload

router = APIRouter(prefix="/api", tags=["api"])


def _ok(data, status: int = 200) -> JSONResponse:
    return JSONResponse({"success": True, "data": data, "error": None}, status_code=status)


def _err(pesan: str, status: int, field: str | None = None) -> JSONResponse:
    payload = {"success": False, "data": None, "error": pesan}
    if field:
        payload["field"] = field
    return JSONResponse(payload, status_code=status)


# --------------------------------------------------------------------------- #
# Fitur 1 — Input Data Rumah Tangga (UC-01)
# --------------------------------------------------------------------------- #
@router.post("/warga")
async def create_warga(payload: dict) -> JSONResponse:
    """Validasi (E1), cek duplikasi (E2), simpan, lalu klasifikasi AI."""
    try:
        bersih = validate_warga_payload(payload)
    except ValidationError as exc:  # Eksepsi E1
        return _err(exc.pesan, status=422, field=exc.field)

    if db.is_duplicate_warga(bersih["nama_kepala_keluarga"]):  # Eksepsi E2
        return _err("Data sudah terdaftar.", status=409, field="nama_kepala_keluarga")

    warga_id = db.insert_warga(bersih)

    hasil = compute_priority(bersih)
    db.insert_hasil_klasifikasi(
        warga_id=warga_id,
        skor=hasil["skor_prioritas"],
        kategori=hasil["kategori_kelayakan"],
        faktor_json=faktor_to_json(hasil["faktor_penjelasan"]),
    )

    return _ok(
        {
            "id": warga_id,
            "nama_kepala_keluarga": bersih["nama_kepala_keluarga"],
            "skor_prioritas": hasil["skor_prioritas"],
            "kategori_kelayakan": hasil["kategori_kelayakan"],
            "prediksi_model_rf": classify_with_model(bersih),
        },
        status=201,
    )


# --------------------------------------------------------------------------- #
# Fitur 2 — Melihat Hasil Klasifikasi (UC-02)
# --------------------------------------------------------------------------- #
@router.get("/hasil")
async def list_hasil() -> JSONResponse:
    """Daftar seluruh warga ter-klasifikasi (skor tertinggi dulu)."""
    rows = db.get_all_hasil()
    return _ok(rows)  # list kosong -> UI tampilkan "Belum ada data tersedia"


@router.get("/hasil/{warga_id}")
async def detail_hasil(warga_id: int) -> JSONResponse:
    """Detail satu warga + faktor penjelasan (UC-02 detail / UC-03)."""
    row = db.get_hasil_by_warga(warga_id)
    if row is None:
        return _err("Data warga tidak ditemukan.", status=404)
    try:
        row["faktor_penjelasan"] = json.loads(row["faktor_penjelasan"])
    except (TypeError, json.JSONDecodeError):
        row["faktor_penjelasan"] = []
    return _ok(row)


# --------------------------------------------------------------------------- #
# Fitur 3 — Melihat Riwayat Penerima Bantuan (UC-08)
# --------------------------------------------------------------------------- #
@router.get("/riwayat/periode")
async def list_periode() -> JSONResponse:
    """Daftar periode bantuan untuk dropdown filter."""
    return _ok(db.get_periode_list())


@router.get("/riwayat")
async def riwayat_by_periode(
    periode: str = Query(..., min_length=1)
) -> JSONResponse:
    """Riwayat penerima pada periode terpilih."""
    data = db.get_riwayat_by_periode(periode)
    return _ok(data)  # list kosong -> UI tampilkan pesan tidak ada data
