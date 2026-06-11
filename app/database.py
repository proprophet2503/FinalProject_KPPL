"""
Lapisan akses database SQLite.

Tabel (sesuai DPPL & CODING INSTRUCTIONS):
- warga              : data rumah tangga calon penerima (UC-01)
- hasil_klasifikasi  : output skor & kategori dari model AI (UC-02)
- riwayat_bantuan    : riwayat penerima per periode (UC-08)

Catatan tanggal: kolom `created_at` memakai format ISO-8601 UTC
(contoh: "2026-06-12T08:30:00"). Periode riwayat memakai format
teks "Bulan Tahun" (contoh: "Januari 2026").
"""

from __future__ import annotations

import sqlite3
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterator

# File DB di-root proyek agar mudah ditemukan untuk demo skala mikro.
BASE_DIR = Path(__file__).resolve().parent.parent
DB_PATH = BASE_DIR / "data" / "bansos.db"


def _utc_now_iso() -> str:
    """Timestamp ISO-8601 tanpa microsecond, deterministik untuk demo."""
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


@contextmanager
def get_connection() -> Iterator[sqlite3.Connection]:
    """Context manager koneksi SQLite dengan row_factory dict-like.

    Selalu menutup koneksi; commit hanya dilakukan pemanggil jika perlu.
    Foreign key di-enforce eksplisit (SQLite mematikannya secara default).
    """
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    try:
        yield conn
    finally:
        conn.close()


def init_db() -> None:
    """Buat seluruh tabel bila belum ada. Idempoten."""
    with get_connection() as conn:
        conn.executescript(
            """
            CREATE TABLE IF NOT EXISTS warga (
                id                     INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_kepala_keluarga   TEXT    NOT NULL,
                pendapatan_bulanan     REAL    NOT NULL,
                jumlah_tanggungan      INTEGER NOT NULL,
                kondisi_tempat_tinggal TEXT    NOT NULL,
                kepemilikan_aset       TEXT    NOT NULL,
                indikator_tambahan     TEXT    NOT NULL,
                created_at             TIMESTAMP NOT NULL
            );

            CREATE TABLE IF NOT EXISTS hasil_klasifikasi (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                warga_id           INTEGER NOT NULL,
                skor_prioritas     REAL    NOT NULL,
                kategori_kelayakan TEXT    NOT NULL,
                faktor_penjelasan  TEXT    NOT NULL,
                FOREIGN KEY (warga_id) REFERENCES warga (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS riwayat_bantuan (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                periode              TEXT NOT NULL,
                nama_kepala_keluarga TEXT NOT NULL,
                skor_prioritas       REAL NOT NULL
            );
            """
        )
        conn.commit()


def seed_riwayat(force: bool = False) -> int:
    """Isi data riwayat tiruan untuk UC-08 bila tabel kosong.

    Mengembalikan jumlah baris yang ditambahkan. `force=True` menghapus
    isi lama lalu mengisi ulang (dipakai test/dev).
    """
    mock = [
        ("Januari 2026", "Budi Santoso", 82.4),
        ("Januari 2026", "Siti Aminah", 78.1),
        ("Januari 2026", "Joko Susilo", 64.7),
        ("Januari 2026", "Rina Marlina", 55.3),
        ("Maret 2026", "Budi Santoso", 80.9),
        ("Maret 2026", "Agus Salim", 76.5),
        ("Maret 2026", "Siti Aminah", 71.2),
        ("Maret 2026", "Dewi Lestari", 48.0),
    ]
    with get_connection() as conn:
        if force:
            conn.execute("DELETE FROM riwayat_bantuan")
        existing = conn.execute("SELECT COUNT(*) FROM riwayat_bantuan").fetchone()[0]
        if existing and not force:
            return 0
        conn.executemany(
            "INSERT INTO riwayat_bantuan (periode, nama_kepala_keluarga, skor_prioritas) "
            "VALUES (?, ?, ?)",
            mock,
        )
        conn.commit()
        return len(mock)


def get_periode_list() -> list[str]:
    """Daftar periode unik untuk dropdown filter UC-08."""
    with get_connection() as conn:
        rows = conn.execute(
            "SELECT DISTINCT periode FROM riwayat_bantuan"
        ).fetchall()
    return [r["periode"] for r in rows]


def get_riwayat_by_periode(periode: str) -> list[dict]:
    """Tarik riwayat penerima pada satu periode, skor tertinggi dulu."""
    with get_connection() as conn:
        rows = conn.execute(
            "SELECT nama_kepala_keluarga, skor_prioritas FROM riwayat_bantuan "
            "WHERE periode = ? ORDER BY skor_prioritas DESC",
            (periode,),
        ).fetchall()
    return [dict(r) for r in rows]


def is_duplicate_warga(nama: str) -> bool:
    """Deteksi duplikasi nama KK (case-insensitive) untuk Eksepsi E2 UC-01."""
    with get_connection() as conn:
        row = conn.execute(
            "SELECT 1 FROM warga WHERE LOWER(TRIM(nama_kepala_keluarga)) = LOWER(TRIM(?)) LIMIT 1",
            (nama,),
        ).fetchone()
    return row is not None


def insert_warga(data: dict) -> int:
    """Simpan satu rumah tangga, kembalikan id baru. Set created_at otomatis."""
    with get_connection() as conn:
        cur = conn.execute(
            """
            INSERT INTO warga (
                nama_kepala_keluarga, pendapatan_bulanan, jumlah_tanggungan,
                kondisi_tempat_tinggal, kepemilikan_aset, indikator_tambahan,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
            """,
            (
                data["nama_kepala_keluarga"].strip(),
                float(data["pendapatan_bulanan"]),
                int(data["jumlah_tanggungan"]),
                data["kondisi_tempat_tinggal"],
                data["kepemilikan_aset"],
                data["indikator_tambahan"],
                _utc_now_iso(),
            ),
        )
        conn.commit()
        return int(cur.lastrowid)


def insert_hasil_klasifikasi(
    warga_id: int, skor: float, kategori: str, faktor_json: str
) -> int:
    """Simpan hasil inferensi AI untuk satu warga."""
    with get_connection() as conn:
        cur = conn.execute(
            "INSERT INTO hasil_klasifikasi (warga_id, skor_prioritas, kategori_kelayakan, "
            "faktor_penjelasan) VALUES (?, ?, ?, ?)",
            (warga_id, skor, kategori, faktor_json),
        )
        conn.commit()
        return int(cur.lastrowid)


def get_all_hasil() -> list[dict]:
    """Join warga + hasil untuk tabel dashboard UC-02, skor tertinggi dulu."""
    with get_connection() as conn:
        rows = conn.execute(
            """
            SELECT w.id, w.nama_kepala_keluarga, w.pendapatan_bulanan,
                   w.jumlah_tanggungan, w.kondisi_tempat_tinggal,
                   w.kepemilikan_aset, w.indikator_tambahan, w.created_at,
                   h.skor_prioritas, h.kategori_kelayakan, h.faktor_penjelasan
            FROM warga w
            JOIN hasil_klasifikasi h ON h.warga_id = w.id
            ORDER BY h.skor_prioritas DESC
            """
        ).fetchall()
    return [dict(r) for r in rows]


def get_hasil_by_warga(warga_id: int) -> dict | None:
    """Detail satu warga + hasil klasifikasinya (UC-02 detail / UC-03)."""
    with get_connection() as conn:
        row = conn.execute(
            """
            SELECT w.id, w.nama_kepala_keluarga, w.pendapatan_bulanan,
                   w.jumlah_tanggungan, w.kondisi_tempat_tinggal,
                   w.kepemilikan_aset, w.indikator_tambahan, w.created_at,
                   h.skor_prioritas, h.kategori_kelayakan, h.faktor_penjelasan
            FROM warga w
            JOIN hasil_klasifikasi h ON h.warga_id = w.id
            WHERE w.id = ?
            """,
            (warga_id,),
        ).fetchone()
    return dict(row) if row else None
