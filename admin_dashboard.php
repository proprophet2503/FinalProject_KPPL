<?php
require_once __DIR__ . '/partials.php';

require_admin();

// Filter kelurahan (kosong = seluruh Sukolilo).
$filter = $_GET['kelurahan'] ?? '';
if ($filter !== '' && !in_array($filter, KELURAHAN, true)) {
    $filter = '';
}

// Detail satu record (opsional).
$detail = null;
if (isset($_GET['detail'])) {
    $detail = get_household((int) $_GET['detail']);
}

$rows = households_all($filter !== '' ? $filter : null);

$total    = count($rows);
$penerima = count(array_filter($rows, fn($r) => (float) $r['skor'] >= 50));
$cakupan  = $filter !== '' ? $filter : 'Seluruh Kec. ' . KECAMATAN;

page_header('Dashboard Admin');
?>
<section class="admin">
    <h1>Dashboard Admin</h1>
    <p class="muted">Pemantauan data rumah tangga Kecamatan <?= e(KECAMATAN) ?>.</p>

    <form method="get" action="admin_dashboard.php" class="period-picker">
        <label for="kelurahan">Kelurahan</label>
        <select id="kelurahan" name="kelurahan" onchange="this.form.submit()">
            <option value="">Semua Kelurahan</option>
            <?php foreach (KELURAHAN as $k): ?>
                <option value="<?= e($k) ?>"<?= $filter === $k ? ' selected' : '' ?>><?= e($k) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn-secondary">Tampilkan</button></noscript>
    </form>

    <div class="stats">
        <div class="stat"><span class="stat-num"><?= $total ?></span><span class="stat-lbl">Total Rumah Tangga</span></div>
        <div class="stat"><span class="stat-num"><?= $penerima ?></span><span class="stat-lbl">Layak Bantuan (≥50)</span></div>
        <div class="stat"><span class="stat-num" style="font-size:1.1rem"><?= e($cakupan) ?></span><span class="stat-lbl">Cakupan</span></div>
    </div>

    <?php if ($detail): ?>
        <div class="detail-panel">
            <div class="detail-head">
                <h2>Detail: <?= e($detail['nama_kk']) ?></h2>
                <a href="admin_dashboard.php<?= $filter !== '' ? '?kelurahan=' . urlencode($filter) : '' ?>" class="btn-link">Tutup</a>
            </div>
            <?php $faktor = json_decode($detail['faktor_json'] ?? '[]', true) ?: []; ?>
            <p>NIK: <?= e($detail['nik_kk']) ?> · <?= e($detail['kelurahan']) ?> ·
               Skor: <strong><?= e(number_format((float) $detail['skor'], 2, ',', '.')) ?></strong> ·
               <span class="badge <?= badge_cls($detail['kategori']) ?>"><?= e($detail['kategori']) ?></span></p>
            <table class="data-table">
                <tr><th>Alamat</th><td><?= e($detail['alamat']) ?></td></tr>
                <tr><th>Pendapatan</th><td><?= e(rupiah((float) $detail['pendapatan_bulanan'])) ?></td></tr>
                <tr><th>Tanggungan</th><td><?= e($detail['jumlah_tanggungan']) ?> orang</td></tr>
                <tr><th>Kondisi Rumah</th><td><?= e($detail['kondisi_tempat_tinggal']) ?></td></tr>
                <tr><th>Aset</th><td><?= e($detail['kepemilikan_aset']) ?></td></tr>
                <tr><th>Indikator</th><td><?= e($detail['indikator_tambahan']) ?></td></tr>
            </table>
            <?php if ($faktor): ?>
            <div class="factors">
                <?php foreach ($faktor as $f): ?>
                    <div class="factor">
                        <div class="factor-head"><span><?= e($f['label']) ?></span>
                            <strong><?= e(number_format((float) $f['kontribusi_persen'], 1, ',', '.')) ?>%</strong></div>
                        <div class="bar"><div class="bar-fill" style="width: <?= (float) $f['kontribusi_persen'] ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>Peringkat Rumah Tangga — <?= e($cakupan) ?></h2>
    <?php if (!$rows): ?>
        <p class="empty">Belum ada data pada cakupan ini.</p>
    <?php else: ?>
    <table class="rank-table">
        <thead>
            <tr><th>#</th><th>Nama KK</th><th>NIK</th><th>Kelurahan</th><th>Pendapatan</th><th>Skor</th><th>Kategori</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($r['nama_kk']) ?></td>
                <td><?= e($r['nik_kk']) ?></td>
                <td><?= e($r['kelurahan']) ?></td>
                <td><?= e(rupiah((float) $r['pendapatan_bulanan'])) ?></td>
                <td><?= e(number_format((float) $r['skor'], 2, ',', '.')) ?></td>
                <td><span class="badge <?= badge_cls($r['kategori']) ?>"><?= e($r['kategori']) ?></span></td>
                <td><a class="btn-link" href="admin_dashboard.php?detail=<?= (int) $r['id'] ?><?= $filter !== '' ? '&kelurahan=' . urlencode($filter) : '' ?>">Detail</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php
page_footer();
