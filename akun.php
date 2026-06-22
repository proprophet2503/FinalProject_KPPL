<?php
require_once __DIR__ . '/partials.php';

$u = require_petugas_approved();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // --- Email ---------------------------------------------------------
    $email = trim($_POST['email'] ?? '');
    if ($email !== '' && $email !== $u['email']) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        } else {
            $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $u['id']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email sudah dipakai akun lain.';
            } else {
                db()->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $u['id']]);
            }
        }
    }

    // --- Password (opsional) ------------------------------------------
    $pass = (string) ($_POST['password'] ?? '');
    if ($pass !== '') {
        if (strlen($pass) < 8) {
            $errors['password'] = 'Password minimal 8 karakter.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
        }
    }

    if (!$errors) {
        flash_set('ok', 'Akun berhasil diperbarui.');
        redirect('akun.php');
    }
    flash_set('error', 'Periksa kembali isian yang ditandai.');
    $u = current_user();
}

page_header('Akun');
?>
<section class="account">
    <h1>Akun Saya</h1>
    <div class="account-grid">
        <aside class="profile-card">
            <div class="avatar placeholder"><?= e(strtoupper(substr($u['nama_lengkap'], 0, 1))) ?></div>
            <h2><?= e($u['nama_lengkap']) ?></h2>
            <dl class="ktp">
                <dt>NIK</dt><dd><?= e($u['nik']) ?></dd>
                <dt>Peran</dt><dd>Petugas</dd>
                <dt>Kelurahan</dt><dd><?= e($u['kelurahan']) ?></dd>
                <dt>Kecamatan</dt><dd><?= e(KECAMATAN) ?></dd>
            </dl>
        </aside>

        <form method="post" action="akun.php" class="account-form" novalidate>
            <?= csrf_field() ?>
            <fieldset>
                <legend>Ubah Kredensial</legend>
                <div class="form-group<?= isset($errors['email']) ? ' invalid' : '' ?>">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($u['email']) ?>">
                    <?php if (isset($errors['email'])): ?><small class="err"><?= e($errors['email']) ?></small><?php endif; ?>
                </div>
                <div class="form-group<?= isset($errors['password']) ? ' invalid' : '' ?>">
                    <label for="password">Password Baru (kosongkan bila tidak diubah)</label>
                    <input type="password" id="password" name="password">
                    <?php if (isset($errors['password'])): ?><small class="err"><?= e($errors['password']) ?></small><?php endif; ?>
                </div>
            </fieldset>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Simpan</button>
                <a href="status.php" class="btn-link">Ke Data Kelurahan</a>
            </div>
        </form>
    </div>
</section>
<?php
page_footer();
