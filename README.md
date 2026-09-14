# ALISA - Aplikasi Logistik Inspeksi & Servis Armada
**Balai Pengelolaan Jalan Wilayah Tegal - POLITEKNIK PURBAYA**

ALISA (Aplikasi Logistik Inspeksi & Servis Armada) adalah sistem informasi berbasis web yang dirancang khusus untuk pengelolaan, pengawasan penjadwalan servis berkala, pajak STNK, uji KIR, serta log pemakaian jam operasional peralatan & kendaraan operasional pada Balai Pengelolaan Jalan Wilayah Tegal.

---

## 👥 Anggota Kelompok (Tim Pengembang PKL)

**Program Studi D3 Teknik Informatika - POLITEKNIK PURBAYA**
- **Zidni Nur Fahma** (NIM: 2402028)
- **Muhammad Rizqon Zaidan** (NIM: 2402051)
- **Virgiawan Listanto** (NIM: 2402083)

---

## 🚀 Fitur Utama Sistem

1. **Pengelolaan 70 Unit Armada & Peralatan**: Roda 6 (Crane, Dump Truck, Towing), Roda 4 (Terios, Gran Max, Pick Up), Roda 3 (Viar), Roda 2, serta Peralatan & Alat Berat (BOMAG, Wheel Loader, Excavator, Genset, Stamper, Cutter).
2. **Matriks Checklist Servis 14 Item**: Pemeliharaan berkala terjadwal untuk seluruh unit armada.
3. **Pengawasan Pajak STNK & Uji KIR**: Matriks jatuh tempo bulanan & riwayat perubahan plat nopol 5-tahunan.
4. **Local Unlimited WhatsApp Gateway Server (Port 3000)**: Notifikasi pengingat otomatis H-30 (mingguan) & H-7 (harian) tanpa biaya API berlangganan.
5. **Multi-Target Penerima WhatsApp**: Notifikasi otomatis dikirimkan ke banyak penerima sekaligus (Pimpinan, Bendahara, Teknisi, Operasional).
6. **Upload & Verifikasi Nota Teknisi**: Teknisi dapat mengunggah bukti nota servis di lapangan untuk diverifikasi oleh Admin.
7. **Privasi & Keamanan Hak Akses (4 Actors)**: Admin, Teknisi, Pimpinan (*view-only* & sensor biaya), Bendahara.

---

## 💻 Panduan Instalasi & Cara Menjalankan Aplikasi

### 1. Persiapan Environment & Folder Project
1. Pastikan Anda telah menginstall **XAMPP** (dengan PHP 7.4+ atau PHP 8.x) dan **Node.js** (LTS version).
2. Salin/copy folder project `bengkel_bpj` ke dalam direktori `C:\xampp\htdocs\bengkel_bpj`.
3. Buka **XAMPP Control Panel**, lalu jalankan service **Apache** dan **MySQL** (klik tombol *Start*).

---

### 2. Inisialisasi Database (Tanpa Import SQL Manual)
Aplikasi ALISA dilengkapi dengan fitur **Auto Database Initializer & Reset Tool**, sehingga Anda **tidak perlu mengimport file database secara manual** di phpMyAdmin.

1. Buka web browser (Chrome/Edge/Firefox).
2. Akses URL berikut:
   ```text
   http://localhost/bengkel_bpj/reset_db.php
   ```
3. Sistem akan secara otomatis:
   - Membuat database `db_bengkel_bpj`.
   - Menginisialisasi seluruh skema tabel (70 Unit Armada, 14 Checklist Servis, Pajak STNK/KIR, Multi-Penerima WA).
   - Membackup & memulihkan transaksi asli jika database pernah di-reset sebelumnya.
4. Tampilan akan menunjukkan pesan **`DATABASE BERHASIL DI RESET`**. Klik tombol **`Kembali ke Beranda ALISA`**.

---

### 3. Menjalankan Local WhatsApp Gateway Server (Port 3000)
1. Buka folder `C:\xampp\htdocs\bengkel_bpj`.
2. Klik ganda (double-click) pada file **`start_wa_gateway.bat`**.
3. Jendela Command Prompt (CMD) akan terbuka dan secara otomatis mengunduh *dependencies* (jika belum ada) serta menjalankan server pada **Port 3000**.
4. Biarkan jendela CMD tetap terbuka selama aplikasi digunakan untuk pengiriman notifikasi otomatis.

---

### 4. Tautkan Perangkat & Cara Ganti Nomor WhatsApp Pengirim
1. Akses halaman Pengaturan WhatsApp di browser:
   ```text
   http://localhost/bengkel_bpj/notifikasi_wa.php
   ```
2. **Menautkan HP Pengirim (Pertama Kali)**:
   - Jika status server menunjukkan `🟡 MENUNGGU SCAN QR CODE`, kotak QR Code akan tampil di halaman web (serta di jendela CMD).
   - Buka aplikasi WhatsApp pada HP pengirim $\rightarrow$ Opsi / Perangkat Tertaut $\rightarrow$ **Tautkan Perangkat** $\rightarrow$ Scan QR Code tersebut.
   - Setelah berhasil, status akan berubah menjadi `🟢 TERHUBUNG (UNLIMITED ACTIVE)`.
3. **Mengganti Nomor WhatsApp Pengirim (Change Sender)**:
   - Jika ingin mengganti dengan nomor HP pengirim lain, klik tombol **`Reset Sesi (Ganti Nomor Pengirim)`** pada banner atas halaman `notifikasi_wa.php`.
   - Sesi WhatsApp pengirim lama akan diputuskan dan QR Code baru akan ditampilkan untuk di-scan menggunakan HP pengirim yang baru.

---

### 5. Cara Menambah Target Penerima Notifikasi WhatsApp (Multi-Penerima)
1. Buka halaman `http://localhost/bengkel_bpj/notifikasi_wa.php`.
2. Pada tabel **Daftar Target Penerima Notifikasi WhatsApp**, klik tombol **`+ Tambah Penerima WA Baru`**.
3. Isi **Nama Penerima**, **Nomor WhatsApp** (contoh: `081234567890`), dan **Jabatan** (Pimpinan, Bendahara, Teknisi, Operasional).
4. Klik **`Simpan Penerima WA`**.
5. Setiap pengingat otomatis atau tombol **`Broadcast Multi-Penerima Now`** ditekan, pesan akan terkirim secara otomatis ke seluruh nomor penerima yang aktif.

---

## 🔑 Akun Default & Hak Akses Pengguna

| Role | Username | Password | Hak Akses & Wewenang |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `123456` | **Akses Penuh**: Kelola Data Unit, Input/Edit/Hapus Servis, Verifikasi Nota Teknisi, Pengaturan WA & Reset DB. |
| **Teknisi** | `teknisi` | `123456` | **Pemeliharaan**: View-only data unit, **Upload Nota Servis** dari lapangan ke Admin. |
| **Pimpinan** | `pimpinan` | `123456` | **Pengawasan**: View-only seluruh laporan & alert (Bebas tombol aksi edit/hapus; nominal biaya disensor). |
| **Bendahara** | `bendahara` | `123456` | **Keuangan**: View-only servis; Akses pencatatan & update pembayaran **Pajak STNK & Uji KIR**. |

---

## 🛠️ Teknologi yang Digunakan
- **Frontend**: HTML5, CSS3 (Kurohiko Custom Dark Theme), JavaScript, Bootstrap 5, FontAwesome 6, SweetAlert2.
- **Backend**: PHP 7.4+ / PHP 8.x (PDO MySQL & SQLite), Node.js (Express & Baileys WhatsApp Web API).
- **Database**: MySQL (`db_bengkel_bpj`) / SQLite (`database.sqlite`).

---
*Dikembangkan untuk Balai Pengelolaan Jalan Wilayah Tegal - Dinas Pekerjaan Umum Bina Marga dan Cipta Karya Provinsi Jawa Tengah.*
