<?php
/**
 * Pustaka inti SiPrioritas Bansos: koneksi DB, scoring MCDM (port identik dari
 * legacy/app/ai_model.py), validasi (selaras UAT), autentikasi + verifikasi
 * petugas, dan persistensi data rumah tangga (per kelurahan).
 *
 * Semua halaman memuat file ini lebih dulu (via partials.php):
 *   require_once __DIR__ . '/lib.php';
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classifier.php';

// ===========================================================================
// SESSION
// ===========================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===========================================================================
// DATABASE (PDO singleton)
// ===========================================================================
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Koneksi database gagal. Periksa pengaturan di config.php.');
    }
    return $pdo;
}

// ===========================================================================
// HELPER UMUM
// ===========================================================================
/** Escape output HTML (cegah XSS). */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Redirect lalu hentikan eksekusi. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Set flash message (tampil sekali pada request berikutnya). */
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/** Ambil & kosongkan semua flash message. */
function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ===========================================================================
// CSRF
// ===========================================================================
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Validasi token CSRF dari POST; hentikan request bila tidak cocok. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('Permintaan tidak valid (CSRF).');
    }
}

// ===========================================================================
// WILAYAH (RW Bumi Marina Emas)
// Catatan: nama konstanta & kolom DB tetap (KECAMATAN/KELURAHAN) demi
// kompatibilitas; secara konsep KECAMATAN = RW, KELURAHAN = RT.
// ===========================================================================
const KECAMATAN = 'RW Bumi Marina Emas';
const KELURAHAN = [
    'RT 1',
    'RT 2',
    'RT 3',
    'RT 4',
    'RT 5',
];

// Status verifikasi akun petugas.
const STATUS_PENDING  = 'pending';
const STATUS_APPROVED = 'approved';
const STATUS_REJECTED = 'rejected';

// ===========================================================================
// AUTENTIKASI & PERAN
// ===========================================================================
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function is_petugas(): bool
{
    return ($_SESSION['role'] ?? '') === 'petugas';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash_set('error', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (($u['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini khusus admin.');
    }
    return $u;
}

/**
 * Halaman khusus petugas yang sudah DISETUJUI admin.
 * Petugas pending/rejected diarahkan ke halaman status verifikasi.
 */
function require_petugas_approved(): array
{
    $u = require_login();
    if (($u['role'] ?? '') !== 'petugas') {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini khusus petugas.');
    }
    if (($u['status'] ?? '') !== STATUS_APPROVED) {
        redirect('pending.php');
    }
    return $u;
}

/**
 * Login: NIK + Email + Password ketiganya wajib cocok pada satu akun.
 * Tidak memblokir status di sini — status (pending/rejected) ditangani per halaman.
 */
function attempt_login(string $nik, string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE nik = ? AND email = ? LIMIT 1');
    $stmt->execute([$nik, $email]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return null;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $u['id'];
    $_SESSION['role']    = $u['role'];
    return $u;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ===========================================================================
// DOMAIN KATEGORIKAL (dropdown)
// ===========================================================================
const KONDISI_RUMAH      = ['Rusak Berat', 'Rusak Sedang', 'Layak'];
const KEPEMILIKAN_ASET   = ['Rendah', 'Sedang', 'Tinggi'];
const INDIKATOR_TAMBAHAN = ['Disabilitas', 'Sakit Kronis', 'Lansia', 'Anak Putus Sekolah', 'Tidak Ada'];

// ===========================================================================
// SCORING MCDM (port identik dari legacy/app/ai_model.py)
// ===========================================================================
const PENDAPATAN_CEILING = 5000000.0;
const TANGGUNGAN_CAP     = 7;

const WEIGHTS = [
    'pendapatan_bulanan'     => 0.35,
    'jumlah_tanggungan'      => 0.20,
    'kondisi_tempat_tinggal' => 0.20,
    'kepemilikan_aset'       => 0.15,
    'indikator_tambahan'     => 0.10,
];

const KONDISI_SCORE   = ['Rusak Berat' => 100.0, 'Rusak Sedang' => 60.0, 'Layak' => 10.0];
const ASET_SCORE      = ['Rendah' => 100.0, 'Sedang' => 50.0, 'Tinggi' => 10.0];
const INDIKATOR_SCORE = [
    'Disabilitas'        => 100.0,
    'Sakit Kronis'       => 90.0,
    'Lansia'             => 80.0,
    'Anak Putus Sekolah' => 70.0,
    'Tidak Ada'          => 10.0,
];
const FITUR_LABEL = [
    'pendapatan_bulanan'     => 'Pendapatan Bulanan',
    'jumlah_tanggungan'      => 'Jumlah Tanggungan',
    'kondisi_tempat_tinggal' => 'Kondisi Tempat Tinggal',
    'kepemilikan_aset'       => 'Kepemilikan Aset',
    'indikator_tambahan'     => 'Indikator Sosial',
];

function need_pendapatan(float $p): float
{
    if ($p <= 0) {
        return 100.0;
    }
    if ($p >= PENDAPATAN_CEILING) {
        return 0.0;
    }
    return round((1.0 - $p / PENDAPATAN_CEILING) * 100.0, 2);
}

function need_tanggungan(int $j): float
{
    if ($j <= 0) {
        return 0.0;
    }
    if ($j >= TANGGUNGAN_CAP) {
        return 100.0;
    }
    return round(($j / TANGGUNGAN_CAP) * 100.0, 2);
}

function kategori_dari_skor(float $skor): string
{
    if ($skor > 75) {
        return 'Sangat Layak';
    }
    if ($skor >= 50) {
        return 'Layak';
    }
    return 'Kurang Layak';
}

/** Hitung skor prioritas + kategori + faktor penjelasan dari 5 kriteria. */
function compute_priority(array $w): array
{
    $subs = [
        'pendapatan_bulanan'     => need_pendapatan((float) $w['pendapatan_bulanan']),
        'jumlah_tanggungan'      => need_tanggungan((int) $w['jumlah_tanggungan']),
        'kondisi_tempat_tinggal' => KONDISI_SCORE[$w['kondisi_tempat_tinggal']] ?? 0.0,
        'kepemilikan_aset'       => ASET_SCORE[$w['kepemilikan_aset']] ?? 0.0,
        'indikator_tambahan'     => INDIKATOR_SCORE[$w['indikator_tambahan']] ?? 0.0,
    ];

    $kontribusi = [];
    foreach (WEIGHTS as $f => $bobot) {
        $kontribusi[$f] = round($subs[$f] * $bobot, 4);
    }
    $skor  = round(array_sum($kontribusi), 2);
    $total = array_sum($kontribusi) ?: 1.0;

    $faktor = [];
    foreach (WEIGHTS as $f => $bobot) {
        $faktor[] = [
            'fitur'             => $f,
            'label'             => FITUR_LABEL[$f],
            'nilai'             => $w[$f],
            'kontribusi'        => $kontribusi[$f],
            'kontribusi_persen' => round($kontribusi[$f] / $total * 100.0, 1),
        ];
    }
    usort($faktor, fn($a, $b) => $b['kontribusi'] <=> $a['kontribusi']);

    return [
        'skor_prioritas'     => $skor,
        // Kategori: hasil ekstraksi Decision Tree (classifier.php). Skor & faktor
        // tetap MCDM. kategori_dari_skor() dipertahankan sebagai pembanding/fallback.
        'kategori_kelayakan' => kategori_decision_tree($w),
        'faktor_penjelasan'  => $faktor,
    ];
}

// ===========================================================================
// VALIDASI (selaras UAT)
// ===========================================================================
/** Buang titik ribuan & spasi dari input rupiah. Kembalikan string angka. */
function parse_rupiah(string $raw): string
{
    return preg_replace('/[.\s]/', '', trim($raw));
}

/**
 * Validasi register PETUGAS (identitas petugas + kelurahan tugas).
 * Mengembalikan array error [field => pesan]; kosong bila valid.
 */
function validate_petugas_register(array $in): array
{
    $err = [];

    $nik = trim($in['nik'] ?? '');
    if ($nik === '') {
        $err['nik'] = 'NIK wajib diisi.';
    } elseif (!preg_match('/^\d{16}$/', $nik)) {
        $err['nik'] = 'NIK harus 16 digit angka.';
    }

    $nama = trim($in['nama_lengkap'] ?? '');
    if ($nama === '') {
        $err['nama_lengkap'] = 'Nama lengkap wajib diisi.';
    } elseif (preg_match('/\d/', $nama)) {
        $err['nama_lengkap'] = 'Nama tidak boleh ada angka.';
    }

    $email = trim($in['email'] ?? '');
    if ($email === '') {
        $err['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Format email tidak valid.';
    }

    $pass = (string) ($in['password'] ?? '');
    if (strlen($pass) < 8) {
        $err['password'] = 'Password minimal 8 karakter.';
    }

    if (!in_array($in['kelurahan'] ?? '', KELURAHAN, true)) {
        $err['kelurahan'] = 'RT wajib dipilih dari daftar.';
    }

    return $err;
}

/**
 * Validasi data rumah tangga (record yang diinput petugas).
 * Mengembalikan [errors[], clean[]].
 */
function validate_household(array $in): array
{
    $err = [];
    $clean = [];

    $nama = trim($in['nama_kk'] ?? '');
    if ($nama === '') {
        $err['nama_kk'] = 'Nama kepala keluarga wajib diisi.';
    } elseif (preg_match('/\d/', $nama)) {
        $err['nama_kk'] = 'Nama tidak boleh ada angka.';
    } else {
        $clean['nama_kk'] = $nama;
    }

    $nik = trim($in['nik_kk'] ?? '');
    if ($nik === '') {
        $err['nik_kk'] = 'NIK kepala keluarga wajib diisi.';
    } elseif (!preg_match('/^\d{16}$/', $nik)) {
        $err['nik_kk'] = 'NIK harus 16 digit angka.';
    } else {
        $clean['nik_kk'] = $nik;
    }

    $alamat = trim($in['alamat'] ?? '');
    if ($alamat === '') {
        $err['alamat'] = 'Alamat wajib diisi.';
    } else {
        $clean['alamat'] = $alamat;
    }

    // Pendapatan: wajib, hanya angka, 0..MAX_PENDAPATAN.
    $pRaw = parse_rupiah((string) ($in['pendapatan_bulanan'] ?? ''));
    if ($pRaw === '') {
        $err['pendapatan_bulanan'] = 'Pendapatan bulanan wajib diisi.';
    } elseif (!ctype_digit($pRaw)) {
        $err['pendapatan_bulanan'] = 'Pendapatan hanya boleh berupa angka.';
    } else {
        $p = (float) $pRaw;
        if ($p > MAX_PENDAPATAN) {
            $err['pendapatan_bulanan'] = 'Pendapatan melebihi batas maksimal.';
        } else {
            $clean['pendapatan_bulanan'] = $p;
        }
    }

    // Tanggungan: wajib, bilangan bulat, 0..MAX_TANGGUNGAN.
    $tRaw = trim((string) ($in['jumlah_tanggungan'] ?? ''));
    if ($tRaw === '') {
        $err['jumlah_tanggungan'] = 'Jumlah tanggungan wajib diisi.';
    } elseif (!ctype_digit($tRaw)) {
        $err['jumlah_tanggungan'] = 'Jumlah tanggungan harus berupa bilangan bulat.';
    } else {
        $t = (int) $tRaw;
        if ($t > MAX_TANGGUNGAN) {
            $err['jumlah_tanggungan'] = 'Jumlah tanggungan tidak wajar (melebihi batas maksimal).';
        } else {
            $clean['jumlah_tanggungan'] = $t;
        }
    }

    if (!in_array($in['kondisi_tempat_tinggal'] ?? '', KONDISI_RUMAH, true)) {
        $err['kondisi_tempat_tinggal'] = 'Kondisi tempat tinggal tidak valid.';
    } else {
        $clean['kondisi_tempat_tinggal'] = $in['kondisi_tempat_tinggal'];
    }
    if (!in_array($in['kepemilikan_aset'] ?? '', KEPEMILIKAN_ASET, true)) {
        $err['kepemilikan_aset'] = 'Kepemilikan aset tidak valid.';
    } else {
        $clean['kepemilikan_aset'] = $in['kepemilikan_aset'];
    }
    if (!in_array($in['indikator_tambahan'] ?? '', INDIKATOR_TAMBAHAN, true)) {
        $err['indikator_tambahan'] = 'Indikator sosial tidak valid.';
    } else {
        $clean['indikator_tambahan'] = $in['indikator_tambahan'];
    }

    return [$err, $clean];
}

// ===========================================================================
// PERSISTENSI DATA RUMAH TANGGA (households)
// ===========================================================================
/** Apakah NIK KK sudah dipakai record lain (untuk deteksi duplikat E2)? */
function nik_kk_exists(string $nikKk, ?int $exceptId = null): bool
{
    if ($exceptId) {
        $stmt = db()->prepare('SELECT 1 FROM households WHERE nik_kk = ? AND id <> ? LIMIT 1');
        $stmt->execute([$nikKk, $exceptId]);
    } else {
        $stmt = db()->prepare('SELECT 1 FROM households WHERE nik_kk = ? LIMIT 1');
        $stmt->execute([$nikKk]);
    }
    return (bool) $stmt->fetch();
}

/** Tambah record baru. Mengembalikan id record. */
function insert_household(int $petugasId, string $kelurahan, array $clean): int
{
    $hasil  = compute_priority($clean);
    $faktor = json_encode($hasil['faktor_penjelasan'], JSON_UNESCAPED_UNICODE);

    $sql = 'INSERT INTO households
              (petugas_id, kelurahan, nama_kk, nik_kk, alamat,
               pendapatan_bulanan, jumlah_tanggungan, kondisi_tempat_tinggal,
               kepemilikan_aset, indikator_tambahan, skor, kategori, faktor_json,
               created_at, updated_at)
            VALUES (:pid, :kel, :nama, :nik, :alamat, :pend, :tang, :kondisi,
                    :aset, :indikator, :skor, :kategori, :faktor, NOW(), NOW())';
    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':pid'       => $petugasId,
        ':kel'       => $kelurahan,
        ':nama'      => $clean['nama_kk'],
        ':nik'       => $clean['nik_kk'],
        ':alamat'    => $clean['alamat'],
        ':pend'      => $clean['pendapatan_bulanan'],
        ':tang'      => $clean['jumlah_tanggungan'],
        ':kondisi'   => $clean['kondisi_tempat_tinggal'],
        ':aset'      => $clean['kepemilikan_aset'],
        ':indikator' => $clean['indikator_tambahan'],
        ':skor'      => $hasil['skor_prioritas'],
        ':kategori'  => $hasil['kategori_kelayakan'],
        ':faktor'    => $faktor,
    ]);
    return (int) db()->lastInsertId();
}

/** Perbarui record yang sudah ada (hitung ulang skor). */
function update_household(int $id, array $clean): void
{
    $hasil  = compute_priority($clean);
    $faktor = json_encode($hasil['faktor_penjelasan'], JSON_UNESCAPED_UNICODE);

    $sql = 'UPDATE households SET
               nama_kk = :nama, nik_kk = :nik, alamat = :alamat,
               pendapatan_bulanan = :pend, jumlah_tanggungan = :tang,
               kondisi_tempat_tinggal = :kondisi, kepemilikan_aset = :aset,
               indikator_tambahan = :indikator, skor = :skor, kategori = :kategori,
               faktor_json = :faktor, updated_at = NOW()
            WHERE id = :id';
    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':nama'      => $clean['nama_kk'],
        ':nik'       => $clean['nik_kk'],
        ':alamat'    => $clean['alamat'],
        ':pend'      => $clean['pendapatan_bulanan'],
        ':tang'      => $clean['jumlah_tanggungan'],
        ':kondisi'   => $clean['kondisi_tempat_tinggal'],
        ':aset'      => $clean['kepemilikan_aset'],
        ':indikator' => $clean['indikator_tambahan'],
        ':skor'      => $hasil['skor_prioritas'],
        ':kategori'  => $hasil['kategori_kelayakan'],
        ':faktor'    => $faktor,
        ':id'        => $id,
    ]);
}

function delete_household(int $id): void
{
    db()->prepare('DELETE FROM households WHERE id = ?')->execute([$id]);
}

/** Ambil satu record. */
function get_household(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM households WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

/** Daftar record di satu kelurahan (urut skor desc). */
function households_by_kelurahan(string $kelurahan): array
{
    $stmt = db()->prepare(
        'SELECT * FROM households WHERE kelurahan = ? ORDER BY skor DESC, nama_kk ASC'
    );
    $stmt->execute([$kelurahan]);
    return $stmt->fetchAll();
}

/** Daftar record untuk admin; filter kelurahan opsional. */
function households_all(?string $kelurahan = null): array
{
    if ($kelurahan !== null && $kelurahan !== '' && in_array($kelurahan, KELURAHAN, true)) {
        $stmt = db()->prepare(
            'SELECT * FROM households WHERE kelurahan = ? ORDER BY skor DESC, nama_kk ASC'
        );
        $stmt->execute([$kelurahan]);
        return $stmt->fetchAll();
    }
    return db()->query(
        'SELECT * FROM households ORDER BY skor DESC, nama_kk ASC'
    )->fetchAll();
}

// ===========================================================================
// VERIFIKASI PETUGAS (admin)
// ===========================================================================
/** Daftar petugas berdasarkan status (pending/approved/rejected). */
function petugas_by_status(string $status): array
{
    $stmt = db()->prepare(
        'SELECT id, nik, nama_lengkap, email, kelurahan, status, created_at
         FROM users WHERE role = "petugas" AND status = ? ORDER BY created_at ASC'
    );
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

/** Ubah status verifikasi seorang petugas. */
function set_petugas_status(int $id, string $status): void
{
    if (!in_array($status, [STATUS_PENDING, STATUS_APPROVED, STATUS_REJECTED], true)) {
        return;
    }
    db()->prepare('UPDATE users SET status = ? WHERE id = ? AND role = "petugas"')
        ->execute([$status, $id]);
}

// ===========================================================================
// TAMPILAN
// ===========================================================================
/** Format angka ke rupiah dengan titik ribuan. */
function rupiah(float|int $n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

/** Kelas badge berdasarkan kategori kelayakan. */
function badge_cls(?string $kat): string
{
    return $kat === 'Sangat Layak' ? 'badge-high'
        : ($kat === 'Layak' ? 'badge-mid' : 'badge-low');
}
