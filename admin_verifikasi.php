<?php
require_once __DIR__ . '/partials.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id  = (int) ($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    if ($id && in_array($aksi, ['approve', 'reject'], true)) {
        set_petugas_status($id, $aksi === 'approve' ? STATUS_APPROVED : STATUS_REJECTED);
        flash_set('ok', 'Status petugas diperbarui.');
    }
    redirect('admin_verifikasi.php');
}

$pending  = petugas_by_status(STATUS_PENDING);
$approved = petugas_by_status(STATUS_APPROVED);
$rejected = petugas_by_status(STATUS_REJECTED);

/** Render baris aksi approve/reject. */
function aksi_form(int $id, string $aksi, string $label, string $cls): string
{
    $f  = '<form method="post" action="admin_verifikasi.php" style="display:inline">';
    $f .= csrf_field();
    $f .= '<input type="hidden" name="id" value="' . $id . '">';
    $f .= '<input type="hidden" name="aksi" value="' . e($aksi) . '">';
    $f .= '<button type="submit" class="' . e($cls) . '">' . e($label) . '</button>';
    $f .= '</form>';
    return $f;
}

page_header('Verifikasi Petugas');
?>
<section class="admin">
    <h1>Verifikasi Petugas</h1>
    <p class="muted">Setujui atau tolak pendaftaran petugas di Kecamatan <?= e(KECAMATAN) ?>.</p>

    <h2>Menunggu Verifikasi (<?= count($pending) ?>)</h2>
    <?php if (!$pending): ?>
        <p class="empty">Tidak ada pendaftaran yang menunggu.</p>
    <?php else: ?>
    <table class="rank-table">
        <thead><tr><th>Nama</th><th>NIK</th><th>Email</th><th>Kelurahan</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($pending as $p): ?>
            <tr>
                <td><?= e($p['nama_lengkap']) ?></td>
                <td><?= e($p['nik']) ?></td>
                <td><?= e($p['email']) ?></td>
                <td><?= e($p['kelurahan']) ?></td>
                <td>
                    <?= aksi_form((int) $p['id'], 'approve', 'Terima', 'btn-primary') ?>
                    <?= aksi_form((int) $p['id'], 'reject', 'Tolak', 'btn-danger') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2>Petugas Aktif (<?= count($approved) ?>)</h2>
    <?php if (!$approved): ?>
        <p class="empty">Belum ada petugas aktif.</p>
    <?php else: ?>
    <table class="rank-table">
        <thead><tr><th>Nama</th><th>NIK</th><th>Kelurahan</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($approved as $p): ?>
            <tr>
                <td><?= e($p['nama_lengkap']) ?></td>
                <td><?= e($p['nik']) ?></td>
                <td><?= e($p['kelurahan']) ?></td>
                <td><?= aksi_form((int) $p['id'], 'reject', 'Nonaktifkan', 'btn-danger') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($rejected): ?>
    <h2>Ditolak / Nonaktif (<?= count($rejected) ?>)</h2>
    <table class="rank-table">
        <thead><tr><th>Nama</th><th>NIK</th><th>Kelurahan</th><th>Aksi</th></tr></thead>
        <tbody>
            <?php foreach ($rejected as $p): ?>
            <tr>
                <td><?= e($p['nama_lengkap']) ?></td>
                <td><?= e($p['nik']) ?></td>
                <td><?= e($p['kelurahan']) ?></td>
                <td><?= aksi_form((int) $p['id'], 'approve', 'Aktifkan', 'btn-primary') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php
page_footer();
