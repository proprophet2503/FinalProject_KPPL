# Spesifikasi Use Case

## 5.1 UC - 01 Menginput Data Rumah Tangga

| Komponen | Deskripsi |
| :--- | :--- |
| **Kode Use Case** | UC - 01 |
| **Nama Use Case** | Menginput Data Rumah Tangga |
| **Aktor** | Petugas |
| **Deskripsi** | Petugas memasukkan data rumah tangga calon penerima bantuan ke dalam sistem, meliputi tingkat pendapatan, jumlah tanggungan, kondisi tempat tinggal, kepemilikan aset, dan indikator sosial ekonomi lainnya. |
| **Kondisi Awal** | Petugas telah login ke sistem. |
| **Kondisi Akhir** | Data rumah tangga tersimpan dalam sistem dan siap untuk diklasifikasi. |

### Alur Normal
| Aktor | Sistem |
| :--- | :--- |
| 1. Petugas membuka menu input data rumah tangga. | 2. Sistem menampilkan formulir input data rumah tangga. |
| 3. Petugas mengisi seluruh pertanyaan yang tersedia: nama kepala keluarga, tingkat pendapatan, jumlah tanggungan, kondisi tempat tinggal, kepemilikan aset, dan indikator sosial ekonomi lainnya. | 4. Sistem memvalidasi kelengkapan data. |
| 5. Petugas menekan tombol Simpan. | 6. Sistem menyimpan data dan menampilkan pesan berhasil. |

### Eksepsi
* **E1.** Jika ada pertanyaan wajib yang belum diisi, sistem menampilkan pesan peringatan dan menandai data yang belum terisi.
* **E2.** Jika data ada yang duplikat, sistem menampilkan pesan peringatan bahwa data sudah terdaftar.

---

## 5.2 UC - 02 Melihat Hasil Klasifikasi

| Komponen | Deskripsi |
| :--- | :--- |
| **Kode Use Case** | UC - 02 |
| **Nama Use Case** | Melihat Hasil Klasifikasi |
| **Aktor** | Petugas |
| **Deskripsi** | Petugas melihat hasil klasifikasi dan skor prioritas yang dihasilkan untuk setiap rumah tangga yang telah diinput, beserta kategori kelayakannya. |
| **Kondisi Awal** | Data rumah tangga sudah diinput ke sistem. |
| **Kondisi Akhir** | Petugas mengetahui hasil klasifikasi tiap rumah tangga. |

### Alur Normal
| Aktor | Sistem |
| :--- | :--- |
| 1. Petugas membuka menu hasil klasifikasi. | 2. Sistem menampilkan daftar rumah tangga beserta skor dan kategori kelayakannya. |
| 3. Petugas memilih salah satu rumah tangga untuk melihat detail. | 4. Sistem menampilkan detail skor prioritas (UC - 02.1) dan kategori kelayakan (UC.02.2). |

### Eksepsi
* **E1.** Jika belum ada data yang diinput, sistem menampilkan pesan bahwa belum ada data tersedia.
* **E2.** Jika proses klasifikasi masih berjalan, sistem menampilkan indikator loading.

### Relasi *Include*
#### 2.1 Melihat Skor Prioritas
| Aktor | Sistem |
| :--- | :--- |
| 2.1.1. Petugas membuka detail rumah tangga dari daftar hasil klasifikasi. | 2.1.2 Sistem menampilkan skor prioritas dalam rentang 0–100. |

#### 2.2 Melihat Kategori Kelayakan
| Aktor | Sistem |
| :--- | :--- |
| 2.2.1. Petugas membuka halaman detail rumah tangga. | 2.2.2. Sistem menampilkan label kategori kelayakan: Sangat Layak, Layak, atau Kurang Layak. |

---

## 5.3 UC - 03 Melihat Penjelasan Keputusan

| Komponen | Deskripsi |
| :--- | :--- |
| **Kode Use Case** | UC - 03 |
| **Nama Use Case** | Melihat Penjelasan Keputusan |
| **Aktor** | Admin |
| **Deskripsi** | Admin melihat penjelasan atas setiap hasil klasifikasi, mencakup faktor-faktor yang mempengaruhi beserta bobot kontribusinya terhadap skor akhir, sehingga keputusan dapat dipertanggungjawabkan secara transparan. |
| **Kondisi Awal** | Admin telah login. Hasil klasifikasi sudah tersedia. |
| **Kondisi Akhir** | Admin memahami faktor-faktor yang mempengaruhi skor prioritas rumah tangga. |

### Alur Normal
| Aktor | Sistem |
| :--- | :--- |
| 1. Admin membuka menu detail rumah tangga. | 2. Sistem menampilkan daftar faktor apa saja beserta persentasenya terhadap skor akhir. |

---

## 5.8 UC - 08 Melihat Riwayat Penerima Bantuan

| Komponen | Deskripsi |
| :--- | :--- |
| **Kode Use Case** | UC - 08 |
| **Nama Use Case** | Melihat Riwayat Penerima Bantuan |
| **Aktor** | Admin |
| **Deskripsi** | Admin melihat riwayat penerima bantuan dari periode sebelumnya sehingga dapat membandingkan perubahan kondisi warga dari waktu ke waktu. |
| **Kondisi Awal** | Admin telah login. Terdapat data riwayat penerima bantuan dari periode sebelumnya. |
| **Kondisi Akhir** | Admin mendapatkan informasi perbandingan kondisi warga antar periode. |

### Alur Normal
| Aktor | Sistem |
| :--- | :--- |
| 1. Admin membuka menu Riwayat Penerima Bantuan. | 2. Sistem menampilkan daftar periode bantuan yang tersedia. |
| 3. Admin memilih periode yang ingin dilihat. | 4. Sistem menampilkan daftar penerima beserta skor pada periode tersebut. |
