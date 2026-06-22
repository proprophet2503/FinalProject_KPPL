<?php
require_once __DIR__ . '/partials.php';

$u = require_login();

// Admin tidak punya status verifikasi.
if (($u['role'] ?? '') === 'admin') {
    redirect('admin_dashboard.php');
}
// Petugas yang sudah disetujui langsung ke dashboard.
if (($u['status'] ?? '') === 'approved') {
    redirect('status.php');
}

$rejected = ($u['status'] ?? '') === 'rejected';

page_header('Status Verifikasi');
?>
<section class="verify-state">
    <div class="verify-card <?= $rejected ? 'is-rejected' : 'is-pending' ?>">
        <?php if ($rejected): ?>
            <div class="verify-icon">✕</div>
            <h1>Pendaftaran Ditolak</h1>
            <p class="muted">
                Maaf, akun petugas Anda untuk kelurahan
                <strong><?= e($u['kelurahan'] ?? '-') ?></strong> ditolak admin.
                Hubungi admin kecamatan untuk informasi lebih lanjut.
            </p>
        <?php else: ?>
            <div class="verify-icon">⏳</div>
            <h1>Verifikasi Dalam Proses</h1>
            <p class="muted">
                Akun petugas Anda untuk kelurahan
                <strong><?= e($u['kelurahan'] ?? '-') ?></strong> sedang menunggu
                persetujuan admin. Silakan cek kembali nanti.
            </p>
        <?php endif; ?>
        <a class="btn-secondary" href="logout.php">Keluar</a>
    </div>
</section>
<?php
page_footer();
