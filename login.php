<?php
require_once __DIR__ . '/partials.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin_dashboard.php' : 'status.php');
}

$old = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST;
    $nik   = trim($_POST['nik'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = (string) ($_POST['password'] ?? '');

    if ($nik === '' || $email === '' || $pass === '') {
        $error = 'NIK, email, dan password wajib diisi.';
    } else {
        $u = attempt_login($nik, $email, $pass);
        if ($u) {
            flash_set('ok', 'Selamat datang, ' . $u['nama_lengkap'] . '.');
            if ($u['role'] === 'admin') {
                redirect('admin_dashboard.php');
            }
            // Petugas: hanya yang sudah disetujui masuk dashboard.
            redirect(($u['status'] ?? '') === 'approved' ? 'status.php' : 'pending.php');
        }
        $error = 'NIK, email, atau password salah.';
    }
}

page_header('Login');
?>
<section class="form-wrap narrow">
    <h1>Login</h1>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" class="form-stack" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="nik">NIK</label>
            <input type="text" id="nik" name="nik" value="<?= e($old['nik'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password">
        </div>
        <button type="submit" class="btn-primary">Masuk</button>
        <a href="register.php" class="btn-link">Belum punya akun? Daftar</a>
    </form>
</section>
<?php
page_footer();
