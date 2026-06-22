"""
Latih Decision Tree pada dummy_dataset.csv lalu ekstrak menjadi aturan if/else
(PHP) untuk klasifikasi kategori kelayakan. Dijalankan offline:

    python3 legacy/data/train_tree.py

Output: metrik akurasi, aturan teks (export_text), dan kode PHP fungsi
classify_kategori_tree() yang siap ditempel ke classifier.php.
"""

import csv
import sys

try:
    from sklearn.tree import DecisionTreeClassifier, export_text, _tree
    from sklearn.model_selection import train_test_split
    from sklearn.metrics import accuracy_score, classification_report
except ImportError:
    sys.exit("sklearn tidak tersedia. pip install scikit-learn")

CSV = __file__.rsplit("/", 1)[0] + "/dummy_dataset.csv"

# Encoding ordinal (kode makin besar = makin membutuhkan / makin prioritas).
KONDISI = {"Layak": 0, "Rusak Sedang": 1, "Rusak Berat": 2}
ASET = {"Tinggi": 0, "Sedang": 1, "Rendah": 2}
INDIKATOR = {
    "Tidak Ada": 0,
    "Anak Putus Sekolah": 1,
    "Lansia": 2,
    "Sakit Kronis": 3,
    "Disabilitas": 4,
}
FEATURES = ["pendapatan_bulanan", "jumlah_tanggungan", "kondisi_code", "aset_code", "indikator_code"]

X, y = [], []
with open(CSV, newline="", encoding="utf-8") as fh:
    for row in csv.DictReader(fh):
        X.append([
            float(row["pendapatan_bulanan"]),
            int(row["jumlah_tanggungan"]),
            KONDISI[row["kondisi_tempat_tinggal"]],
            ASET[row["kepemilikan_aset"]],
            INDIKATOR[row["indikator_tambahan"]],
        ])
        y.append(row["kategori_kelayakan"])

Xtr, Xte, ytr, yte = train_test_split(X, y, test_size=0.25, random_state=42, stratify=y)

clf = DecisionTreeClassifier(max_depth=5, min_samples_leaf=10, random_state=42)
clf.fit(Xtr, ytr)

acc_tr = accuracy_score(ytr, clf.predict(Xtr))
acc_te = accuracy_score(yte, clf.predict(Xte))
acc_all = accuracy_score(y, clf.predict(X))

print("=== METRIK ===")
print(f"n={len(X)}  depth={clf.get_depth()}  leaves={clf.get_n_leaves()}")
print(f"akurasi train={acc_tr:.4f}  test={acc_te:.4f}  semua={acc_all:.4f}")
print()
print("=== CLASSIFICATION REPORT (test) ===")
print(classification_report(yte, clf.predict(Xte)))
print("=== EXPORT_TEXT ===")
print(export_text(clf, feature_names=FEATURES))

# ---- Emit PHP if/else dari struktur pohon ----
t = clf.tree_
classes = clf.classes_


def emit(node, depth):
    pad = "    " * depth
    if t.feature[node] == _tree.TREE_UNDEFINED:
        cls = classes[t.value[node][0].argmax()]
        return f"{pad}return '{cls}';\n"
    feat = FEATURES[t.feature[node]]
    thr = t.threshold[node]
    s = f"{pad}if ($f['{feat}'] <= {thr:.4f}) {{\n"
    s += emit(t.children_left[node], depth + 1)
    s += f"{pad}}} else {{\n"
    s += emit(t.children_right[node], depth + 1)
    s += f"{pad}}}\n"
    return s


print("=== PHP ===")
print("function classify_kategori_tree(array $f): string\n{")
print(emit(0, 1), end="")
print("}")
