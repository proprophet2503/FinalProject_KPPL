<?php
/**
 * Klasifikasi kategori kelayakan berbasis ATURAN HASIL EKSTRAKSI DECISION TREE.
 *
 * Pohon dilatih offline (scikit-learn) pada legacy/data/dummy_dataset.csv lalu
 * di-ekstrak 1:1 menjadi if/else di sini (lihat legacy/data/train_tree.py dan
 * model_desc.md). Akurasi: ~85% (semua data), ~82% (test split).
 *
 * Catatan: SKOR prioritas 0–100, peringkat, dan penjelasan faktor tetap memakai
 * MCDM (lihat lib.php). Decision tree HANYA menentukan kategori kelayakan.
 */

declare(strict_types=1);

// Encoding ordinal — WAJIB sama dengan train_tree.py (kode besar = makin butuh).
const TREE_KONDISI_CODE = ['Layak' => 0, 'Rusak Sedang' => 1, 'Rusak Berat' => 2];
const TREE_ASET_CODE    = ['Tinggi' => 0, 'Sedang' => 1, 'Rendah' => 2];
const TREE_INDIKATOR_CODE = [
    'Tidak Ada'          => 0,
    'Anak Putus Sekolah' => 1,
    'Lansia'             => 2,
    'Sakit Kronis'       => 3,
    'Disabilitas'        => 4,
];

/**
 * Bangun vektor fitur numerik dari data rumah tangga mentah ($w berisi 5 field
 * assessment yang sama dengan compute_priority).
 */
function tree_features(array $w): array
{
    return [
        'pendapatan_bulanan' => (float) $w['pendapatan_bulanan'],
        'jumlah_tanggungan'  => (int) $w['jumlah_tanggungan'],
        'kondisi_code'       => TREE_KONDISI_CODE[$w['kondisi_tempat_tinggal']] ?? 0,
        'aset_code'          => TREE_ASET_CODE[$w['kepemilikan_aset']] ?? 0,
        'indikator_code'     => TREE_INDIKATOR_CODE[$w['indikator_tambahan']] ?? 0,
    ];
}

/** Wrapper: terima data mentah, kembalikan kategori dari decision tree. */
function kategori_decision_tree(array $w): string
{
    return classify_kategori_tree(tree_features($w));
}

/**
 * Aturan hasil ekstraksi pohon keputusan (auto-generated, jangan edit manual;
 * regenerasi via train_tree.py bila dataset/pohon berubah).
 */
function classify_kategori_tree(array $f): string
{
    if ($f['pendapatan_bulanan'] <= 3412803.5000) {
        if ($f['kondisi_code'] <= 1.5000) {
            if ($f['jumlah_tanggungan'] <= 5.5000) {
                if ($f['pendapatan_bulanan'] <= 2159501.5000) {
                    if ($f['kondisi_code'] <= 0.5000) {
                        return 'Kurang Layak';
                    } else {
                        return 'Layak';
                    }
                } else {
                    if ($f['aset_code'] <= 1.5000) {
                        return 'Kurang Layak';
                    } else {
                        return 'Layak';
                    }
                }
            } else {
                if ($f['pendapatan_bulanan'] <= 525486.5000) {
                    return 'Layak';
                } else {
                    if ($f['aset_code'] <= 0.5000) {
                        return 'Layak';
                    } else {
                        return 'Layak';
                    }
                }
            }
        } else {
            if ($f['pendapatan_bulanan'] <= 1486906.5000) {
                if ($f['aset_code'] <= 0.5000) {
                    return 'Layak';
                } else {
                    if ($f['pendapatan_bulanan'] <= 833800.5000) {
                        return 'Sangat Layak';
                    } else {
                        return 'Sangat Layak';
                    }
                }
            } else {
                if ($f['aset_code'] <= 1.5000) {
                    return 'Layak';
                } else {
                    return 'Layak';
                }
            }
        }
    } else {
        if ($f['jumlah_tanggungan'] <= 5.5000) {
            if ($f['kondisi_code'] <= 1.5000) {
                return 'Kurang Layak';
            } else {
                if ($f['jumlah_tanggungan'] <= 3.5000) {
                    if ($f['indikator_code'] <= 2.5000) {
                        return 'Kurang Layak';
                    } else {
                        return 'Kurang Layak';
                    }
                } else {
                    return 'Kurang Layak';
                }
            }
        } else {
            if ($f['kondisi_code'] <= 1.5000) {
                if ($f['aset_code'] <= 1.5000) {
                    if ($f['pendapatan_bulanan'] <= 4560391.0000) {
                        return 'Kurang Layak';
                    } else {
                        return 'Kurang Layak';
                    }
                } else {
                    return 'Layak';
                }
            } else {
                if ($f['pendapatan_bulanan'] <= 4896441.5000) {
                    return 'Layak';
                } else {
                    return 'Layak';
                }
            }
        }
    }
}
