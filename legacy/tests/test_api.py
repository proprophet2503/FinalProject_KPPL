"""Integration test endpoint API ketiga fitur (UC-01, UC-02, UC-08)."""

import importlib

import pytest
from fastapi.testclient import TestClient


@pytest.fixture()
def client(tmp_path, monkeypatch):
    """TestClient dengan DB SQLite sementara per-test (isolasi penuh)."""
    from app import database as db

    monkeypatch.setattr(db, "DB_PATH", tmp_path / "test.db")

    # main meng-import db; lifespan akan init_db + seed_riwayat pakai path baru.
    from app import main as main_module

    importlib.reload(main_module)
    with TestClient(main_module.app) as c:
        yield c


def _payload(nama="Budi Santoso"):
    return {
        "nama_kepala_keluarga": nama,
        "pendapatan_bulanan": 800000,
        "jumlah_tanggungan": 4,
        "kondisi_tempat_tinggal": "Rusak Berat",
        "kepemilikan_aset": "Rendah",
        "indikator_tambahan": "Disabilitas",
    }


# --- UC-01 ----------------------------------------------------------------- #
def test_create_warga_sukses(client):
    r = client.post("/api/warga", json=_payload())
    assert r.status_code == 201
    body = r.json()
    assert body["success"] is True
    assert 0 <= body["data"]["skor_prioritas"] <= 100
    assert body["data"]["kategori_kelayakan"] in {"Sangat Layak", "Layak", "Kurang Layak"}


def test_create_warga_data_kosong_e1(client):
    bad = _payload()
    bad["nama_kepala_keluarga"] = ""
    r = client.post("/api/warga", json=bad)
    assert r.status_code == 422
    assert r.json()["success"] is False


def test_create_warga_duplikat_e2(client):
    client.post("/api/warga", json=_payload("Siti Aminah"))
    r = client.post("/api/warga", json=_payload("siti aminah"))  # case-insensitive
    assert r.status_code == 409
    assert "terdaftar" in r.json()["error"].lower()


# --- UC-02 ----------------------------------------------------------------- #
def test_list_hasil_kosong(client):
    r = client.get("/api/hasil")
    assert r.status_code == 200
    assert r.json()["data"] == []


def test_list_dan_detail_hasil(client):
    created = client.post("/api/warga", json=_payload("Agus Salim")).json()
    wid = created["data"]["id"]

    lst = client.get("/api/hasil").json()
    assert len(lst["data"]) == 1

    det = client.get(f"/api/hasil/{wid}").json()
    assert det["data"]["nama_kepala_keluarga"] == "Agus Salim"
    assert isinstance(det["data"]["faktor_penjelasan"], list)
    assert len(det["data"]["faktor_penjelasan"]) == 5


def test_detail_tidak_ditemukan(client):
    r = client.get("/api/hasil/999")
    assert r.status_code == 404


# --- UC-08 ----------------------------------------------------------------- #
def test_riwayat_periode_terisi_seed(client):
    r = client.get("/api/riwayat/periode")
    assert r.status_code == 200
    periodes = r.json()["data"]
    assert "Januari 2026" in periodes
    assert "Maret 2026" in periodes


def test_riwayat_by_periode(client):
    r = client.get("/api/riwayat", params={"periode": "Januari 2026"})
    assert r.status_code == 200
    rows = r.json()["data"]
    assert len(rows) >= 1
    # Terurut skor menurun.
    skor = [x["skor_prioritas"] for x in rows]
    assert skor == sorted(skor, reverse=True)
