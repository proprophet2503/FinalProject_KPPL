<?php
require_once __DIR__ . '/partials.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin_dashboard.php' : 'status.php');
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST;
    $errors = validate_petugas_register($_POST);

    // Cek keunikan NIK & email bila format sudah valid.
    if (!isset($errors['nik'])) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE nik = ? LIMIT 1');
        $stmt->execute([trim($_POST['nik'])]);
        if ($stmt->fetch()) {
            $errors['nik'] = 'NIK sudah terdaftar.';
        }
    }
    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([trim($_POST['email'])]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO users (nik, nama_lengkap, email, password_hash, role, kelurahan, status)
             VALUES (?,?,?,?, "petugas", ?, "pending")'
        );
        $stmt->execute([
            trim($_POST['nik']),
            trim($_POST['nama_lengkap']),
            trim($_POST['email']),
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            $_POST['kelurahan'],
        ]);
        $id = (int) db()->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['role'] = 'petugas';
        flash_set('ok', 'Pendaftaran terkirim. Menunggu verifikasi admin.');
        redirect('pending.php');
    }
}

/** Render satu field text dengan label, nilai lama, dan error. */
function field(string $name, string $label, array $old, array $errors, string $type = 'text'): void
{
    $val = e($old[$name] ?? '');
    $err = $errors[$name] ?? '';
    $cls = $err ? 'form-group invalid' : 'form-group';
    echo "<div class=\"$cls\">";
    echo "<label for=\"$name\">" . e($label) . "</label>";
    echo "<input type=\"$type\" id=\"$name\" name=\"$name\" value=\"$val\">";
    if ($err) {
        echo "<small class=\"err\" data-err=\"$name\">" . e($err) . "</small>";
    }
    echo "</div>";
}

page_header('Daftar Petugas');
?>
<section class="form-wrap">
    <h1>Pendaftaran Petugas</h1>
    <p class="muted">
        Daftar sebagai petugas pendataan di Kecamatan <?= e(KECAMATAN) ?>.
        Akun aktif setelah diverifikasi admin.
    </p>
    <form method="post" action="register.php" novalidate class="form-grid" id="formRegister">
        <?= csrf_field() ?>
        <?php
        field('nik', 'NIK (16 digit)', $old, $errors);
        field('nama_lengkap', 'Nama Lengkap', $old, $errors);
        field('email', 'Email', $old, $errors, 'email');
        field('password', 'Password (min. 8 karakter)', $old, $errors, 'password');
        ?>
        <div class="form-group<?= isset($errors['kelurahan']) ? ' invalid' : '' ?>">
            <label for="kelurahan">Kelurahan (Kec. <?= e(KECAMATAN) ?>)</label>
            <select id="kelurahan" name="kelurahan">
                <option value="">-- pilih kelurahan --</option>
                <?php foreach (KELURAHAN as $k): ?>
                    <option value="<?= e($k) ?>"<?= ($old['kelurahan'] ?? '') === $k ? ' selected' : '' ?>><?= e($k) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['kelurahan'])): ?>
                <small class="err" data-err="kelurahan"><?= e($errors['kelurahan']) ?></small>
            <?php endif; ?>
        </div>
        <div class="form-actions span-2">
            <button type="submit" class="btn-primary">Daftar</button>
            <a href="login.php" class="btn-link">Sudah punya akun? Login</a>
        </div>
    </form>
</section>
<?php
page_footer();
