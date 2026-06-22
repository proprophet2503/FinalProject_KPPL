<?php
require_once __DIR__ . '/partials.php';

$u = require_petugas_approved();
$kel = (string) $u['kelurahan'];
$rows = households_by_kelurahan($kel);

$total    = count($rows);
$layak    = count(array_filter($rows, fn($r) => (float) $r['skor'] >= 50));

page_header('Data RT');
?>
<section class="petugas">
    <div class="page-head">
        <div>
            <h1>Data Rumah Tangga — <?= e($kel) ?></h1>
            <p class="muted"><?= e(KECAMATAN) ?>. Skor prioritas dihitung otomatis (MCDM).</p>
        </div>
        <a href="input.php" class="btn-primary">+ Input Data</a>
    </div>

    <div class="stats">
        <div class="stat"><span class="stat-num"><?= $total ?></span><span class="stat-lbl">Total Rumah Tangga</span></div>
        <div class="stat"><span class="stat-num"><?= $layak ?></span><span class="stat-lbl">Layak Bantuan (≥50)</span></div>
    </div>

    <?php if (!$rows): ?>
        <div class="empty">
            <p>Belum ada data rumah tangga di kelurahan ini.</p>
            <a href="input.php" class="btn-primary">Input Data Pertama</a>
        </div>
    <?php else: ?>
    <table class="rank-table">
        <thead>
            <tr><th>#</th><th>Nama KK</th><th>NIK</th><th>Pendapatan</th><th>Tanggungan</th><th>Skor</th><th>Kategori</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($r['nama_kk']) ?></td>
                <td><?= e($r['nik_kk']) ?></td>
                <td><?= e(rupiah((float) $r['pendapatan_bulanan'])) ?></td>
                <td><?= e($r['jumlah_tanggungan']) ?> orang</td>
                <td><?= e(number_format((float) $r['skor'], 2, ',', '.')) ?></td>
                <td><span class="badge <?= badge_cls($r['kategori']) ?>"><?= e($r['kategori']) ?></span></td>
                <td><a class="btn-link" href="detail.php?id=<?= (int) $r['id'] ?>">Detail</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php
page_footer();
