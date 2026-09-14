-- ALISA Database Dump - Optimized Schema
-- Balai Pengelolaan Jalan Wilayah Tegal

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL UNIQUE,
  `password` varchar(100) NOT NULL,
  `nama_lengkap` varchar(50) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `jabatan` varchar(50) DEFAULT NULL,
  `foto_profil` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `kendaraan_alat`;
CREATE TABLE `kendaraan_alat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_plat` varchar(20) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `jenis` varchar(30) NOT NULL DEFAULT 'kendaraan_roda_4',
  `kategori` varchar(50) NOT NULL,
  `merk` varchar(50) DEFAULT NULL,
  `tahun` int(11) DEFAULT NULL,
  `kup_reg` varchar(50) DEFAULT NULL,
  `no_chasis` varchar(50) DEFAULT NULL,
  `no_mesin` varchar(50) DEFAULT NULL,
  `no_bpkb` varchar(50) DEFAULT NULL,
  `kondisi` varchar(10) DEFAULT 'B',
  `status_pemilik` varchar(30) DEFAULT 'APBD BPJ TEGAL',
  `lokasi_ruas` varchar(100) DEFAULT '1. Jatinegara - Slawi',
  `penanggung_jawab` varchar(50) DEFAULT NULL,
  `interval_servis_bulan` int(11) NOT NULL DEFAULT 3,
  `jam_operasional` int(11) NOT NULL DEFAULT 0,
  `interval_jam_servis` int(11) NOT NULL DEFAULT 1000,
  `sisa_jam_servis` int(11) NOT NULL DEFAULT 1000,
  `tgl_servis_terakhir` date DEFAULT NULL,
  `tgl_servis_berikutnya` date DEFAULT NULL,
  `tgl_jatuh_tempo_pajak` date DEFAULT NULL,
  `tgl_jatuh_tempo_plat` date DEFAULT NULL,
  `tgl_jatuh_tempo_kir` date DEFAULT NULL,
  `bulan_pajak` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Baik',
  `foto` varchar(100) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `servis_checklist`;
CREATE TABLE `servis_checklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` int(11) NOT NULL,
  `item_servis` varchar(50) NOT NULL,
  `interval_servis` varchar(50) DEFAULT NULL,
  `tgl_servis_terakhir` date DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_kendaraan` (`id_kendaraan`),
  CONSTRAINT `fk_checklist_kendaraan` FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `riwayat_servis`;
CREATE TABLE `riwayat_servis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` int(11) NOT NULL,
  `tgl_servis` date NOT NULL,
  `jenis_servis` varchar(30) NOT NULL DEFAULT 'Penjadwalan',
  `nama_layanan` varchar(50) NOT NULL,
  `rincian_item` text DEFAULT NULL,
  `total_biaya` decimal(15,2) NOT NULL DEFAULT 0.00,
  `nama_bengkel` varchar(50) DEFAULT NULL,
  `foto_nota` varchar(100) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_kendaraan` (`id_kendaraan`),
  CONSTRAINT `fk_servis_kendaraan` FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pembayaran_pajak_kir`;
CREATE TABLE `pembayaran_pajak_kir` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` int(11) NOT NULL,
  `jenis_pembayaran` varchar(30) NOT NULL DEFAULT 'Pajak STNK',
  `tgl_bayar` date NOT NULL,
  `tgl_jatuh_tempo_baru` date NOT NULL,
  `nominal_biaya` decimal(15,2) NOT NULL DEFAULT 0.00,
  `foto_bukti` varchar(100) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_kendaraan` (`id_kendaraan`),
  CONSTRAINT `fk_pajak_kendaraan` FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `riwayat_perubahan_plat`;
CREATE TABLE `riwayat_perubahan_plat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` int(11) NOT NULL,
  `plat_lama` varchar(20) NOT NULL,
  `plat_baru` varchar(20) NOT NULL,
  `tgl_perubahan` datetime DEFAULT CURRENT_TIMESTAMP,
  `keterangan` text DEFAULT NULL,
  `diubah_oleh` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_kendaraan` (`id_kendaraan`),
  CONSTRAINT `fk_plat_kendaraan` FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pengaturan_whatsapp`;
CREATE TABLE `pengaturan_whatsapp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_token` varchar(100) DEFAULT NULL,
  `target_phone` varchar(20) DEFAULT NULL,
  `notif_h30_aktif` tinyint(1) DEFAULT 1,
  `notif_h7_aktif` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `log_pemakaian_alat`;
CREATE TABLE `log_pemakaian_alat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` int(11) NOT NULL,
  `jam_dipakai` int(11) NOT NULL,
  `total_jam_setelah_pakai` int(11) NOT NULL,
  `sisa_jam_servis_setelah_pakai` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `nama_user` varchar(50) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;