/*
 * BansosApp — logika frontend (Vanilla JS + Fetch API).
 * Setiap halaman memanggil init-nya sendiri dari blok <script>.
 * Semua respons API berformat: { success, data, error }.
 */
const BansosApp = (() => {
  const API = "/api";

  const rupiah = (n) =>
    "Rp " + Number(n || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });

  // Warna badge per kategori kelayakan.
  const badge = (kategori) => {
    const map = {
      "Sangat Layak": "bg-emerald-100 text-emerald-700",
      Layak: "bg-amber-100 text-amber-700",
      "Kurang Layak": "bg-slate-200 text-slate-600",
    };
    return map[kategori] || "bg-slate-200 text-slate-600";
  };

  const show = (el) => el && el.classList.remove("hidden");
  const hide = (el) => el && el.classList.add("hidden");
  const esc = (s) =>
    String(s).replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])
    );

  async function apiGet(path) {
    const res = await fetch(API + path);
    const json = await res.json();
    return { ok: res.ok, ...json };
  }

  async function apiPost(path, body) {
    const res = await fetch(API + path, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    const json = await res.json();
    return { ok: res.ok, status: res.status, ...json };
  }

  // ------------------------------------------------------------------ //
  // Dashboard (index.html)
  // ------------------------------------------------------------------ //
  async function initDashboard() {
    const setStat = (k, v) => {
      const el = document.querySelector(`[data-stat="${k}"]`);
      if (el) el.textContent = v;
    };
    const r = await apiGet("/hasil");
    const rows = r.data || [];
    setStat("total", rows.length);
    setStat("sangat", rows.filter((x) => x.kategori_kelayakan === "Sangat Layak").length);
    setStat("max", rows.length ? Math.max(...rows.map((x) => x.skor_prioritas)) : 0);
  }

  // ------------------------------------------------------------------ //
  // Form input (input.html) — UC-01
  // ------------------------------------------------------------------ //
  function initForm() {
    const form = document.getElementById("form-warga");
    const alertBox = document.getElementById("alert");
    const spinner = document.getElementById("spinner");
    const btn = document.getElementById("btn-simpan");
    if (!form) return;

    const clearErrors = () => {
      document.querySelectorAll(".err").forEach((p) => {
        p.textContent = "";
        hide(p);
      });
      hide(alertBox);
    };

    const setAlert = (msg, ok) => {
      alertBox.textContent = msg;
      alertBox.className =
        "mb-4 rounded-lg px-4 py-3 text-sm " +
        (ok ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
            : "bg-red-50 text-red-700 border border-red-200");
      show(alertBox);
    };

    const setFieldError = (field, msg) => {
      const p = document.querySelector(`[data-err="${field}"]`);
      if (p) {
        p.textContent = msg;
        show(p);
      }
    };

    // Validasi sisi klien sederhana (mendahului validasi server).
    const validateClient = (payload) => {
      let ok = true;
      if (!payload.nama_kepala_keluarga.trim()) {
        setFieldError("nama_kepala_keluarga", "Nama wajib diisi.");
        ok = false;
      }
      if (payload.pendapatan_bulanan === "" || Number(payload.pendapatan_bulanan) < 0) {
        setFieldError("pendapatan_bulanan", "Pendapatan wajib diisi (>= 0).");
        ok = false;
      }
      if (payload.jumlah_tanggungan === "" || Number(payload.jumlah_tanggungan) < 0) {
        setFieldError("jumlah_tanggungan", "Jumlah tanggungan wajib diisi (>= 0).");
        ok = false;
      }
      ["kondisi_tempat_tinggal", "kepemilikan_aset", "indikator_tambahan"].forEach((f) => {
        if (!payload[f]) {
          setFieldError(f, "Wajib dipilih.");
          ok = false;
        }
      });
      return ok;
    };

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearErrors();
      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());

      if (!validateClient(payload)) {
        setAlert("Periksa kembali isian yang ditandai.", false);
        return;
      }

      payload.pendapatan_bulanan = Number(payload.pendapatan_bulanan);
      payload.jumlah_tanggungan = Number(payload.jumlah_tanggungan);

      btn.disabled = true;
      show(spinner);
      try {
        const r = await apiPost("/warga", payload);
        if (r.ok && r.success) {
          setAlert(
            `Berhasil disimpan. Skor: ${r.data.skor_prioritas} (${r.data.kategori_kelayakan}).`,
            true
          );
          form.reset();
          setTimeout(() => (window.location.href = `/detail/${r.data.id}`), 1200);
        } else {
          if (r.field) setFieldError(r.field, r.error);
          setAlert(r.error || "Gagal menyimpan data.", false);
        }
      } catch (err) {
        setAlert("Tidak dapat terhubung ke server.", false);
      } finally {
        btn.disabled = false;
        hide(spinner);
      }
    });
  }

  // ------------------------------------------------------------------ //
  // Daftar hasil (hasil.html) — UC-02
  // ------------------------------------------------------------------ //
  async function initHasil() {
    const loading = document.getElementById("loading");
    const empty = document.getElementById("empty");
    const wrap = document.getElementById("tabel-wrap");
    const tbody = document.getElementById("tbody");

    try {
      const r = await apiGet("/hasil");
      const rows = r.data || [];
      hide(loading);
      if (!rows.length) {
        show(empty);
        return;
      }
      tbody.innerHTML = rows
        .map(
          (x, i) => `
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 text-slate-400">${i + 1}</td>
          <td class="px-4 py-3 font-medium text-slate-800">${esc(x.nama_kepala_keluarga)}</td>
          <td class="px-4 py-3 text-right font-semibold">${x.skor_prioritas}</td>
          <td class="px-4 py-3">
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold ${badge(x.kategori_kelayakan)}">
              ${esc(x.kategori_kelayakan)}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="/detail/${x.id}" class="text-brand-700 hover:underline text-sm">Detail</a>
          </td>
        </tr>`
        )
        .join("");
      show(wrap);
    } catch (err) {
      hide(loading);
      empty.textContent = "Gagal memuat data dari server.";
      show(empty);
    }
  }

  // ------------------------------------------------------------------ //
  // Detail + penjelasan (detail.html) — UC-02.x / UC-03
  // ------------------------------------------------------------------ //
  async function initDetail() {
    const root = document.querySelector("[data-warga-id]");
    const id = root && root.getAttribute("data-warga-id");
    const loading = document.getElementById("loading");
    const notfound = document.getElementById("notfound");
    const detail = document.getElementById("detail");

    const r = await apiGet(`/hasil/${id}`);
    hide(loading);
    if (!r.ok || !r.success || !r.data) {
      show(notfound);
      return;
    }
    const d = r.data;
    const set = (f, v) => {
      const el = document.querySelector(`[data-field="${f}"]`);
      if (el) el.textContent = v;
    };
    set("nama", d.nama_kepala_keluarga);
    set("skor", d.skor_prioritas);
    const katEl = document.querySelector('[data-field="kategori"]');
    katEl.textContent = d.kategori_kelayakan;
    katEl.className =
      "mt-1 inline-block px-3 py-1 rounded-full text-sm font-semibold " +
      badge(d.kategori_kelayakan);

    // Data mentah ringkas.
    const mentah = [
      ["Pendapatan Bulanan", rupiah(d.pendapatan_bulanan)],
      ["Jumlah Tanggungan", d.jumlah_tanggungan + " orang"],
      ["Kondisi Tempat Tinggal", d.kondisi_tempat_tinggal],
      ["Kepemilikan Aset", d.kepemilikan_aset],
      ["Indikator Sosial", d.indikator_tambahan],
    ];
    document.getElementById("data-mentah").innerHTML = mentah
      .map(
        ([k, v]) => `
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs uppercase tracking-wide text-slate-400">${k}</p>
        <p class="font-medium text-slate-800 mt-0.5">${esc(v)}</p>
      </div>`
      )
      .join("");

    // Faktor penjelasan (bar kontribusi).
    const faktor = Array.isArray(d.faktor_penjelasan) ? d.faktor_penjelasan : [];
    document.getElementById("faktor").innerHTML = faktor
      .map(
        (f) => `
      <li>
        <div class="flex justify-between text-sm mb-1">
          <span class="font-medium text-slate-700">${esc(f.label)}
            <span class="text-slate-400">(${esc(f.nilai)})</span>
          </span>
          <span class="font-semibold text-slate-600">${f.kontribusi_persen}%</span>
        </div>
        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full bg-brand-600 rounded-full" style="width:${f.kontribusi_persen}%"></div>
        </div>
      </li>`
      )
      .join("");

    show(detail);
  }

  // ------------------------------------------------------------------ //
  // Riwayat (riwayat.html) — UC-08
  // ------------------------------------------------------------------ //
  async function initRiwayat() {
    const select = document.getElementById("periode");
    const empty = document.getElementById("empty");
    const wrap = document.getElementById("tabel-wrap");
    const tbody = document.getElementById("tbody");

    const renderRows = (rows) => {
      hide(empty);
      hide(wrap);
      if (!rows.length) {
        show(empty);
        return;
      }
      tbody.innerHTML = rows
        .map(
          (x, i) => `
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 text-slate-400">${i + 1}</td>
          <td class="px-4 py-3 font-medium text-slate-800">${esc(x.nama_kepala_keluarga)}</td>
          <td class="px-4 py-3 text-right font-semibold">${x.skor_prioritas}</td>
        </tr>`
        )
        .join("");
      show(wrap);
    };

    const loadPeriode = async (periode) => {
      if (!periode) {
        hide(wrap);
        hide(empty);
        return;
      }
      const r = await apiGet("/riwayat?periode=" + encodeURIComponent(periode));
      renderRows(r.data || []);
    };

    const rp = await apiGet("/riwayat/periode");
    const periodes = rp.data || [];
    select.innerHTML =
      '<option value="">— Pilih Periode —</option>' +
      periodes.map((p) => `<option value="${esc(p)}">${esc(p)}</option>`).join("");

    select.addEventListener("change", (e) => loadPeriode(e.target.value));

    // Auto-load periode pertama agar tabel tidak kosong saat dibuka.
    if (periodes.length) {
      select.value = periodes[0];
      loadPeriode(periodes[0]);
    }
  }

  return { initDashboard, initForm, initHasil, initDetail, initRiwayat };
})();
