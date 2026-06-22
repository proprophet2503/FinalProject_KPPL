<?php
require_once __DIR__ . '/partials.php';

// Landing publik. Pengguna login tetap melihat halaman ini; navbar
// menyesuaikan peran lewat partials. CTA bawah diarahkan ke halaman peran.
$u = current_user();
$ctaHref = 'register.php';
$ctaText = 'Daftar sebagai Petugas';
if ($u) {
    if (is_admin()) {
        $ctaHref = 'admin_dashboard.php';
        $ctaText = 'Buka Dashboard';
    } elseif (($u['status'] ?? '') === 'approved') {
        $ctaHref = 'status.php';
        $ctaText = 'Data RT Saya';
    } else {
        $ctaHref = 'pending.php';
        $ctaText = 'Status Verifikasi';
    }
}

page_header('Beranda');
?>
<section class="hero">
    <span class="hero-badge">MCDM · Multi-Criteria Decision Making</span>
    <h1>Sistem Prioritas Penerima Bantuan Sosial</h1>
    <p class="lead">
        Petugas RT mendata rumah tangga di RW Bumi Marina Emas; sistem
        menghitung skor kelayakan secara transparan dan konsisten berbasis
        kriteria majemuk, lengkap dengan rincian faktor penilaiannya.
    </p>
    <div class="hero-actions">
        <a class="btn-primary lg" href="<?= e($ctaHref) ?>"><?= e($ctaText) ?></a>
        <?php if (!$u): ?>
            <a class="btn-secondary lg" href="login.php">Login</a>
        <?php endif; ?>
    </div>
</section>

<section class="features">
    <h2 class="section-title">Tiga Fitur Utama</h2>
    <div class="cards">
        <article class="card feature">
            <span class="feature-tag">UC-01</span>
            <h3>Input Data Rumah Tangga</h3>
            <p>
                Petugas mendata rumah tangga di kelurahannya: pendapatan
                bulanan, jumlah tanggungan, kondisi tempat tinggal, kepemilikan
                aset, dan indikator sosial — semua lewat pilihan dropdown.
            </p>
        </article>
        <article class="card feature">
            <span class="feature-tag">UC-02 &amp; UC-03</span>
            <h3>Hasil Klasifikasi &amp; Penjelasan</h3>
            <p>
                Skor kelayakan dihitung otomatis dan ditampilkan dengan
                kategori (Sangat Layak / Layak / Kurang Layak) serta rincian
                kontribusi tiap faktor dalam persen — transparan, bukan kotak hitam.
            </p>
        </article>
        <article class="card feature">
            <span class="feature-tag">UC-08</span>
            <h3>Riwayat Penerima Bantuan</h3>
            <p>
                Admin dapat menelusuri daftar penerima bantuan per periode,
                lengkap dengan skor dan kategori, sebagai rekam jejak penyaluran
                yang akuntabel.
            </p>
        </article>
    </div>
</section>

<?php if (!$u): ?>
<section class="cta-band">
    <h2>Mulai dalam tiga langkah</h2>
    <p>Daftar petugas &rarr; verifikasi admin &rarr; data rumah tangga &amp; skor prioritas.</p>
    <a class="btn-primary lg" href="register.php">Daftar sebagai Petugas</a>
</section>
<?php endif; ?>
<?php
page_footer();
