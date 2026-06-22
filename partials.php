<?php
/** Komponen layout bersama: header, navbar, flash, footer. */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

function page_header(string $title): void
{
    $u = current_user();
    $admin = ($u['role'] ?? '') === 'admin';
    $petugas = ($u['role'] ?? '') === 'petugas';
    $approved = ($u['status'] ?? '') === 'approved';
    ?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">
        <span class="brand-mark">SP</span>
        <span class="brand-text"><?= e(APP_NAME) ?></span>
    </a>
    <nav class="nav">
        <?php if ($u): ?>
            <?php if ($admin): ?>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_verifikasi.php">Verifikasi</a>
                <a href="admin_riwayat.php">Riwayat</a>
            <?php elseif ($petugas && $approved): ?>
                <a href="status.php">Data Kelurahan</a>
                <a href="input.php">Input Data</a>
                <a href="akun.php">Akun</a>
            <?php endif; ?>
            <span class="nav-user"><?= e($u['nama_lengkap']) ?><?php
                if ($admin) { echo ' (Admin)'; }
                elseif ($petugas) { echo ' · ' . e($u['kelurahan'] ?? '-'); }
            ?></span>
            <a class="btn-link" href="logout.php">Keluar</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a class="btn-primary" href="register.php">Daftar</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?php
    foreach (flash_take() as $f) {
        $cls = $f['type'] === 'error' ? 'flash-error' : 'flash-ok';
        echo '<div class="flash ' . $cls . '">' . e($f['msg']) . '</div>';
    }
}

function page_footer(): void
{
    ?>
</main>
<footer class="footer">
    <p>&copy; 2026 <?= e(APP_NAME) ?> — Sistem Prioritas Penerima Bantuan Sosial.</p>
</footer>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}
