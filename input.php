<?php
require_once __DIR__ . '/partials.php';

$u = require_petugas_approved();
$kel = (string) $u['kelurahan'];

// Mode edit bila ada ?id= dan record berada di kelurahan petugas.
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$record = null;
if ($editId) {
    $record = get_household($editId);
    if (!$record || $record['kelurahan'] !== $kel) {
        http_response_code(404);
        exit('Data tidak ditemukan di kelurahan Anda.');
    }
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST;
    [$errors, $clean] = validate_household($_POST);

    // Deteksi duplikat NIK KK (E2).
    if (!isset($errors['nik_kk']) && nik_kk_exists($clean['nik_kk'], $editId ?: null)) {
        $errors['nik_kk'] = 'NIK kepala keluarga sudah terdaftar.';
    }

    if (!$errors) {
        if ($editId) {
            update_household($editId, $clean);
            flash_set('ok', 'Data berhasil diperbarui.');
            redirect('detail.php?id=' . $editId);
        }
        $newId = insert_household((int) $u['id'], $kel, $clean);
        flash_set('ok', 'Data berhasil disimpan & dinilai.');
        redirect('detail.php?id=' . $newId);
    }
    flash_set('error', 'Periksa kembali isian yang ditandai.');
}

/** Nilai lama: POST dulu, lalu record tersimpan. */
function val(string $k, array $old, ?array $rec): string
{
    if (isset($old[$k]) && $old[$k] !== '') {
        return (string) $old[$k];
    }
    return (string) ($rec[$k] ?? '');
}

$title = $editId ? 'Edit Data Rumah Tangga' : 'Input Data Rumah Tangga';
page_header($title);

$selects = [
    'kondisi_tempat_tinggal' => ['Kondisi Tempat Tinggal', KONDISI_RUMAH],
    'kepemilikan_aset'       => ['Kepemilikan Aset', KEPEMILIKAN_ASET],
    'indikator_tambahan'     => ['Indikator Sosial', INDIKATOR_TAMBAHAN],
];
$action = 'input.php' . ($editId ? '?id=' . $editId : '');
?>
<section class="form-wrap">
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($kel) ?>, <?= e(KECAMATAN) ?>. Semua isian wajib.</p>
    <form method="post" action="<?= e($action) ?>" novalidate class="form-grid" id="formInput">
        <?= csrf_field() ?>

        <div class="form-group<?= isset($errors['nama_kk']) ? ' invalid' : '' ?>">
            <label for="nama_kk">Nama Kepala Keluarga</label>
            <input type="text" id="nama_kk" name="nama_kk" value="<?= e(val('nama_kk', $old, $record)) ?>">
            <?php if (isset($errors['nama_kk'])): ?><small class="err" data-err="nama_kk"><?= e($errors['nama_kk']) ?></small><?php endif; ?>
        </div>

        <div class="form-group<?= isset($errors['nik_kk']) ? ' invalid' : '' ?>">
            <label for="nik_kk">NIK Kepala Keluarga (16 digit)</label>
            <input type="text" id="nik_kk" name="nik_kk" inputmode="numeric" data-int
                   value="<?= e(val('nik_kk', $old, $record)) ?>">
            <?php if (isset($errors['nik_kk'])): ?><small class="err" data-err="nik_kk"><?= e($errors['nik_kk']) ?></small><?php endif; ?>
        </div>

        <div class="form-group span-2<?= isset($errors['alamat']) ? ' invalid' : '' ?>">
            <label for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat" rows="2"><?= e(val('alamat', $old, $record)) ?></textarea>
            <?php if (isset($errors['alamat'])): ?><small class="err" data-err="alamat"><?= e($errors['alamat']) ?></small><?php endif; ?>
        </div>

        <div class="form-group<?= isset($errors['pendapatan_bulanan']) ? ' invalid' : '' ?>">
            <label for="pendapatan_bulanan">Pendapatan Bulanan (Rp)</label>
            <input type="text" id="pendapatan_bulanan" name="pendapatan_bulanan" inputmode="numeric" data-rupiah
                   value="<?= e(val('pendapatan_bulanan', $old, $record)) ?>">
            <?php if (isset($errors['pendapatan_bulanan'])): ?><small class="err" data-err="pendapatan_bulanan"><?= e($errors['pendapatan_bulanan']) ?></small><?php endif; ?>
        </div>

        <div class="form-group<?= isset($errors['jumlah_tanggungan']) ? ' invalid' : '' ?>">
            <label for="jumlah_tanggungan">Jumlah Tanggungan</label>
            <input type="text" id="jumlah_tanggungan" name="jumlah_tanggungan" inputmode="numeric" data-int
                   value="<?= e(val('jumlah_tanggungan', $old, $record)) ?>">
            <?php if (isset($errors['jumlah_tanggungan'])): ?><small class="err" data-err="jumlah_tanggungan"><?= e($errors['jumlah_tanggungan']) ?></small><?php endif; ?>
        </div>

        <?php foreach ($selects as $name => [$label, $opts]):
            $cur = val($name, $old, $record);
            $inv = isset($errors[$name]) ? ' invalid' : '';
        ?>
        <div class="form-group<?= $inv ?>">
            <label for="<?= $name ?>"><?= e($label) ?></label>
            <select id="<?= $name ?>" name="<?= $name ?>">
                <option value="">-- pilih --</option>
                <?php foreach ($opts as $o): ?>
                    <option value="<?= e($o) ?>"<?= $o === $cur ? ' selected' : '' ?>><?= e($o) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors[$name])): ?><small class="err" data-err="<?= $name ?>"><?= e($errors[$name]) ?></small><?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="form-actions span-2">
            <button type="submit" class="btn-primary"><?= $editId ? 'Simpan Perubahan' : 'Simpan & Hitung Skor' ?></button>
            <a href="<?= $editId ? 'detail.php?id=' . $editId : 'status.php' ?>" class="btn-link">Batal</a>
        </div>
    </form>
</section>
<?php
page_footer();
