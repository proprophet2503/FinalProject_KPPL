<?php
require_once __DIR__ . '/partials.php';

$u = require_petugas_approved();
$kel = (string) $u['kelurahan'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$r = $id ? get_household($id) : null;
if (!$r || $r['kelurahan'] !== $kel) {
    http_response_code(404);
    exit('Data tidak ditemukan di kelurahan Anda.');
}

// Hapus record (UC-05.2).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    delete_household($id);
    flash_set('ok', 'Data rumah tangga dihapus.');
    redirect('status.php');
}

$faktor = json_decode($r['faktor_json'] ?? '[]', true) ?: [];

page_header('Detail Rumah Tangga');
?>
<section class="detail">
    <a href="status.php" class="btn-link">&larr; Kembali ke daftar</a>
    <h1><?= e($r['nama_kk']) ?></h1>

    <div class="score-card">
        <div class="score-num"><?= e(number_format((float) $r['skor'], 2, ',', '.')) ?></div>
        <div class="score-meta">
            <span class="badge <?= badge_cls($r['kategori']) ?>"><?= e($r['kategori']) ?></span>
            <p class="muted">Skor prioritas 0–100 (MCDM). Makin tinggi = makin prioritas.</p>
        </div>
    </div>

    <h2>Rincian Data</h2>
    <table class="data-table">
        <tr><th>NIK Kepala Keluarga</th><td><?= e($r['nik_kk']) ?></td></tr>
        <tr><th>Kelurahan</th><td><?= e($r['kelurahan']) ?>, Kec. <?= e(KECAMATAN) ?></td></tr>
        <tr><th>Alamat</th><td><?= e($r['alamat']) ?></td></tr>
        <tr><th>Pendapatan Bulanan</th><td><?= e(rupiah((float) $r['pendapatan_bulanan'])) ?></td></tr>
        <tr><th>Jumlah Tanggungan</th><td><?= e($r['jumlah_tanggungan']) ?> orang</td></tr>
        <tr><th>Kondisi Tempat Tinggal</th><td><?= e($r['kondisi_tempat_tinggal']) ?></td></tr>
        <tr><th>Kepemilikan Aset</th><td><?= e($r['kepemilikan_aset']) ?></td></tr>
        <tr><th>Indikator Sosial</th><td><?= e($r['indikator_tambahan']) ?></td></tr>
    </table>

    <h2>Penjelasan Keputusan</h2>
    <?php if (!$faktor): ?>
        <p class="muted">Rincian faktor tersedia setelah data dihitung ulang (Edit lalu Simpan).</p>
    <?php else: ?>
    <p class="muted">Kontribusi tiap faktor terhadap skor akhir (urut terbesar).</p>
    <div class="factors">
        <?php foreach ($faktor as $f): ?>
            <div class="factor">
                <div class="factor-head">
                    <span><?= e($f['label']) ?> <small class="muted">(<?= e((string) $f['nilai']) ?>)</small></span>
                    <strong><?= e(number_format((float) $f['kontribusi_persen'], 1, ',', '.')) ?>%</strong>
                </div>
                <div class="bar"><div class="bar-fill" style="width: <?= (float) $f['kontribusi_persen'] ?>%"></div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <a href="input.php?id=<?= (int) $r['id'] ?>" class="btn-primary">Edit Data</a>
        <form method="post" action="detail.php?id=<?= (int) $r['id'] ?>"
              onsubmit="return confirm('Hapus data ini? Tindakan tidak dapat dibatalkan.');" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <button type="submit" class="btn-danger">Hapus</button>
        </form>
    </div>
    <p class="updated muted">Terakhir diperbarui: <?= e($r['updated_at']) ?></p>
</section>
<?php
page_footer();
