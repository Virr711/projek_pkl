# **PRODUCT REQUIREMENT DOCUMENT (PRD)**
## **ALISA • Aplikasi Logistik Inspeksi & Servis Armada**

* **Instansi:** Balai Pengelolaan Jalan
* **Versi Dokumen:** v2.0 (Final Revision)
* **Tanggal:** Agustus 2026
* **Status Projek:** Perancangan Sistem & Dokumen Spesifikasi PKL

---

## **1. Ringkasan Produk (Executive Summary)**
**ALISA (Aplikasi Logistik Inspeksi & Servis Armada)** adalah sistem informasi manajemen inventarisasi unit, pemeliharaan berkala, pelacakan pajak & KIR, serta pelaporan logistik armada berbasis web yang dirancang khusus untuk lingkungan **Balai Pengelolaan Jalan**.

Sistem ini dibangun untuk mengatasi permasalahan utama di mana pencatatan dan pengingat jadwal servis rutin armada/peralatan operasional masih dilakukan secara manual, sehingga rentan mengalami keterlambatan servis atau kelalaian pemeliharaan. ALISA mengintegrasikan sistem notifikasi otomatis via **WhatsApp Gateway** yang memberikan peringatan sebelum *deadline* servis maupun jatuh tempo Pajak & KIR. Selain itu, ALISA menyediakan 11 opsi pilihan ruas jalan wilayah kerja Balai Pengelolaan Jalan secara terpilih (*dropdown/select*) guna menjaga konsistensi data tanpa perlu penginputan manual.

---

## **2. Analisis Pengguna & Matriks Hak Akses (User Roles)**
ALISA membagi hak akses ke dalam 4 peranan pengguna (*roles*) utama sebagai berikut:

| Role / Aktor | Deskripsi Tanggung Jawab | Hak Akses & Fitur Utama |
| :--- | :--- | :--- |
| **Admin** | Bertanggung jawab atas administrasi sistem, validasi data master, penyusunan jadwal servis, serta pengiriman notifikasi dan pengelolaan laporan. | • Login<br>• Mengelola Data Unit (Kendaraan, Pajak & KIR, Peralatan)<br>• Mengelola Jadwal Servis<br>• Kelola Data Ruas<br>• Mengirim Notifikasi (Manual/Trigger WhatsApp)<br>• Mengelola Laporan |
| **Teknisi** | Petugas lapangan yang menangani pemeriksaan fisik unit, pelaksanaan servis, pencatatan nota, pengunggahan dokumen Pajak/KIR, serta pembaruan status servis. | • Login<br>• Kelola Data Unit<br>• Kelola Data Ruas<br>• Kelola Jadwal Servis<br>• Unggah Bukti Pembayaran Pajak & KIR<br>• Menerima Notifikasi<br>• Melakukan Servis (Mengunggah nota servis, mengubah status servis, melihat history) |
| **Bendahara** | Bertanggung jawab mengelola dan memverifikasi pembayaran Pajak & KIR serta mengelola laporan terkait aspek pembiayaan tanpa penginputan nominal kaku (menggunakan data real-time). | • Login<br>• Mengelola Pembayaran Pajak & KIR (Real-time data, tanpa input nominal statis)<br>• Menerima Notifikasi Pembayaran<br>• Mengelola Laporan (Khusus Laporan Nota/Keuangan Pajak & Servis) |
| **Pimpinan** | Pihak manajerial yang melakukan pengawasan operasional armada, melihat perkembangan status servis, riwayat pemeliharaan, dan mengevaluasi seluruh laporan. | • Login<br>• Melihat Data Unit<br>• Melihat Data Ruas<br>• Melihat Status Servis & History Servis<br>• Melihat Laporan (Laporan Servis, Laporan Bukti Nota Servis & Pajak/KIR, Laporan Data Unit Peralatan & Kendaraan, Laporan Data Ruas) |

---

## **3. Kebutuhan Fungsional (Functional Requirements)**

### **3.1 Autentikasi & Keamanan Akun**
* **Login Multi-Role:** Sistem menyediakan halaman login tunggal yang mengarahkan pengguna ke *dashboard* sesuai perannya (Admin, Teknisi, Bendahara, Pimpinan).

### **3.2 Pengelolaan Data Master Unit & Ruas**
* **Mengelola Data Unit:**
  * **Kendaraan:** Pencatatan data fisik (Nomor Polisi, Jenis Armada, Merk/Tipe, Nomor Mesin/Rangka, Status Operasional).
  * **Pajak & KIR:** Pencatatan tanggal jatuh tempo STNK, Pajak Tahunan, dan Sertifikat Uji Berkala (KIR).
  * **Peralatan:** Pencatatan peralatan pendukung/alat berat kerja yang dialokasikan pada unit/wilayah.
* **Data Ruas (Pilihan Statis / Dropdown 11 Ruas Jalan Balai Pengelolaan Jalan):**  
  Penginputan data ruas tidak dilakukan secara ketik manual melainkan memilih 1 dari 11 daftar ruas resmi Balai Pengelolaan Jalan:
  1. Jatinegara - Slawi
  2. Slawi - Jatibarang
  3. Jatibarang - Ketanggungan
  4. Ketanggungan - Kersana - Bantarsari
  5. Kersana - Bandungsari
  6. Bandungsari - Salem
  7. Bandungsari - Penanggapan
  8. Bumiayu - Salem
  9. Sirampog - Bumiayu
  10. Morongso - Tuwel - Sirampog
  11. Salem - Batas Cilacap

### **3.3 Manajemen Servis, Jadwal & Pengingat WhatsApp**
* **Kelola Jadwal Servis:** Admin dan Teknisi menyusun jadwal pemeliharaan rutin berdasar interval waktu (bulan) atau kilometer armada.
* **Notifikasi WhatsApp Gateway:**
  * Sistem mengirimkan pesan pengingat otomatis via WhatsApp ke nomor HP Teknisi/Admin/Pimpinan beberapa hari sebelum tanggal *deadline* servis rutin atau jatuh tempo Pajak & KIR.
  * Admin dapat mengirimkan *trigger* notifikasi secara manual jika diperlukan.
* **Melakukan Servis & History:**
  * **Mengunggah Nota Servis:** Teknisi mengunggah foto/file bukti transaksi nota perbaikan.
  * **Mengubah Status Servis:** Teknisi memperbarui status pekerjaan (misal: *Scheduled* $ightarrow$ *On Progress* $ightarrow$ *Completed*).
  * **Melihat History:** Seluruh rekam jejak servis tercatat secara permanen dan dapat ditinjau oleh Teknisi maupun Pimpinan.

### **3.4 Pengelolaan Pembayaran Pajak & KIR (Aturan Khusus Bendahara)**
* **Pengelolaan Pembayaran Real-Time:**
  * Bendahara dan Teknisi mengelola pencatatan dan verifikasi pembayaran Pajak & KIR.
  * **Aturan Nominal:** Sistem tidak merekam/menuntut penginputan nominal angka statis karena nilai Pajak/KIR bersifat fleksibel dan menggunakan data *real-time* saat transaksi/pembayaran dilakukan di lapangan.
  * Bendahara menerima notifikasi pembayaran masuk dan dapat mengesahkan dokumen/bukti transaksi Pajak & KIR.

### **3.5 Modul Laporan (Reporting System)**
Sistem menyediakan modul kelola & lihat laporan dalam format cetak/ekspor (PDF/Excel) yang terdiri dari:
1. **Laporan Servis:** Rekapitulasi pengerjaan servis, tanggal eksekusi, serta riwayat kerusakan unit.
2. **Laporan Bukti Nota Servis dan Pajak/KIR:** Rincian dokumen/bukti fisik pembayaran nota servis serta kelengkapan dokumen Pajak/KIR.
3. **Laporan Data Unit Peralatan dan Kendaraan:** Daftar seluruh ketersediaan, spesifikasi, dan kondisi fisik aset kendaraan maupun peralatan.
4. **Laporan Data Ruas:** Distribusi penempatan unit armada dan peralatan berdasarkan 11 ruas jalan wilayah Balai Pengelolaan Jalan.

---

## **4. Kebutuhan Non-Fungsional (Non-Functional Requirements)**
* **Integrasi WhatsApp API:** Menggunakan library/gateway WhatsApp API (seperti Fonnte, Wablas, atau sejenisnya) untuk pengiriman pesan otomatis berjadwal (*Cron Job*).
* **Keamanan Data:** Password terenkripsi menggunakan *Bcrypt*. Hak akses halaman dibatasi dengan *Role-Based Access Control* (RBAC).
* **Respon Sistem:** Pemrosesan pengiriman pesan WA berjalan di *background process* agar tidak menghambat kinerja aplikasi web. *Loading time* $< 2$ detik.
* **Aksesibilitas Interface:** Antarmuka responsif dan ramah pengguna (terutama kemudahan Teknisi mengunggah foto nota lewat HP/Tablet di lokasi bengkel/lapangan).

---

## **5. Ringkasan Alur Kerja Sistem (Workflow Summary)**
```text
[Admin] ──> Input Data Unit & Pilih 1 dari 11 Ruas ──> Buat Jadwal Servis
                                                              │
                                                              ▼
[WA Gateway] <── Notifikasi Peringatan H-X Deadline Servis ◄──┘
     │
     ├─> [Teknisi] ──> Lakukan Servis ──> Upload Nota & Ubah Status Completed
     │                                           │
     ├─> [Bendahara] ──> Kelola & Verifikasi ◄───┘
     │                  Dokumen Pajak/KIR (Real-time)
     │
     └─> [Pimpinan] ──> Monitoring Status, History & Cetak 4 Jenis Laporan
```
