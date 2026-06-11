"""
Entry point FastAPI: inisialisasi DB, seed riwayat, mount static & template,
daftarkan API router, dan sajikan halaman HTML untuk pengurus RT.

Jalankan: uvicorn app.main:app --reload
"""

from __future__ import annotations

from contextlib import asynccontextmanager
from pathlib import Path

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from app import __version__, database as db
from app.routes.api import router as api_router

BASE_DIR = Path(__file__).resolve().parent
templates = Jinja2Templates(directory=str(BASE_DIR / "templates"))


@asynccontextmanager
async def lifespan(_: FastAPI):
    """Siapkan skema DB + seed riwayat tiruan sekali saat server naik."""
    db.init_db()
    db.seed_riwayat()
    yield


app = FastAPI(
    title="Sistem AI Penentu Prioritas Penerima Bantuan",
    description="Prototipe seleksi bantuan sosial skala mikro (RT/RW).",
    version=__version__,
    lifespan=lifespan,
)

# CORS longgar untuk demo (frontend statis bisa di GitHub Pages, backend lokal).
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

app.mount("/static", StaticFiles(directory=str(BASE_DIR / "static")), name="static")
app.include_router(api_router)


# --- Halaman HTML (server-rendered shell, data diisi via fetch API) ---------
@app.get("/")
def page_dashboard(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})


@app.get("/input")
def page_input(request: Request):
    return templates.TemplateResponse("input.html", {"request": request})


@app.get("/hasil")
def page_hasil(request: Request):
    return templates.TemplateResponse("hasil.html", {"request": request})


@app.get("/detail/{warga_id}")
def page_detail(request: Request, warga_id: int):
    return templates.TemplateResponse(
        "detail.html", {"request": request, "warga_id": warga_id}
    )


@app.get("/riwayat")
def page_riwayat(request: Request):
    return templates.TemplateResponse("riwayat.html", {"request": request})


@app.get("/health")
def health():
    return {"status": "ok", "version": __version__}
