<?php
require_once __DIR__ . '/partials.php';

require_admin();

const BULAN_ID = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei',
    6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober',
    11 => 'November', 12 => 'Desember',
];

// Filter kelurahan (kosong = seluruh Sukolilo).
$filter = $_GET['kelurahan'] ?? '';
if ($filter !== '' && !in_array($filter, KELURAHAN, true)) {
    $filter = '';
}

$historis = db()->query('SELECT id, label FROM periode_bantuan ORDER BY tahun DESC, bulan DESC')->fetchAll();

$curLabel = BULAN_ID[(int) date('n')] . ' ' . date('Y');
$sel = $_GET['periode'] ?? 'current';

$judul = '';
$penerima = [];

if ($sel === 'current') {
    $judul = $curLabel . ' (berjalan)';
    if ($filter !== '') {
        $stmt = db()->prepare(
            'SELECT nama_kk AS nama, kelurahan, skor, kategori FROM households
             WHERE skor >= 50 AND kelurahan = ? ORDER BY skor DESC, nama_kk ASC'
        );
        $stmt->execute([$filter]);
        $penerima = $stmt->fetchAll();
    } else {
        $penerima = db()->query(
            'SELECT nama_kk AS nama, kelurahan, skor, kategori FROM households
             WHERE skor >= 50 ORDER BY skor DESC, nama_kk ASC'
        )->fetchAll();
    }
} else {
    $pid = (int) $sel;
    $stmt = db()->prepare('SELECT label FROM periode_bantuan WHERE id = ? LIMIT 1');
    $stmt->execute([$pid]);
    $p = $stmt->fetch();
    if ($p) {
        $judul = $p['label'];
        if ($filter !== '') {
            $stmt = db()->prepare(
                'SELECT nama, kelurahan, skor, kategori FROM penerima
                 WHERE periode_id = ? AND kelurahan = ? ORDER BY skor DESC, nama ASC'
            );
            $stmt->execute([$pid, $filter]);
        } else {
            $stmt = db()->prepare(
                'SELECT nama, kelurahan, skor, kategori FROM penerima
                 WHERE periode_id = ? ORDER BY skor DESC, nama ASC'
            );
            $stmt->execute([$pid]);
        }
        $penerima = $stmt->fetchAll();
    }
}

$cakupan = $filter !== '' ? $filter : 'Seluruh ' . KECAMATAN;

page_header('Riwayat Bantuan');
?>
<section class="riwayat">
    <h1>Riwayat Penyaluran Bantuan</h1>

    <form method="get" action="admin_riwayat.php" class="period-picker">
        <label for="periode">Periode</label>
        <select id="periode" name="periode" onchange="this.form.submit()">
            <option value="current"<?= $sel === 'current' ? ' selected' : '' ?>><?= e($curLabel) ?> (berjalan)</option>
            <?php foreach ($historis as $p): ?>
                <option value="<?= (int) $p['id'] ?>"<?= $sel === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="kelurahan">RT</label>
        <select id="kelurahan" name="kelurahan" onchange="this.form.submit()">
            <option value="">Semua RT</option>
            <?php foreach (KELURAHAN as $k): ?>
                <option value="<?= e($k) ?>"<?= $filter === $k ? ' selected' : '' ?>><?= e($k) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn-secondary">Tampilkan</button></noscript>
    </form>

    <h2>Penerima — <?= e($judul) ?> · <?= e($cakupan) ?></h2>
    <?php if (!$penerima): ?>
        <p class="empty">Tidak ada data penerima untuk periode ini.</p>
    <?php else: ?>
    <table class="rank-table">
        <thead><tr><th>#</th><th>Nama</th><th>RT</th><th>Skor</th><th>Kategori</th></tr></thead>
        <tbody>
            <?php foreach ($penerima as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($r['nama']) ?></td>
                <td><?= e($r['kelurahan']) ?></td>
                <td><?= e(number_format((float) $r['skor'], 2, ',', '.')) ?></td>
                <td><span class="badge <?= badge_cls($r['kategori']) ?>"><?= e($r['kategori']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted">Penerima = rumah tangga dengan skor ≥ 50 (Layak / Sangat Layak).</p>
    <?php endif; ?>
</section>
<?php
page_footer();
