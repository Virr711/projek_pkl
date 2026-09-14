<?php
/**
 * ALISA - Aplikasi Logistik Inspeksi & Servis Armada
 * Balai Pengelolaan Jalan Wilayah Tegal - Tahun 2026
 * Database Initializer with Kendaraan, Peralatan, VIAR (Roda 3), & RB Condition.
 */

define('DB_NAME', 'db_bengkel_bpj');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_HOST', '127.0.0.1');

function get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $driver = 'mysql';
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
    } catch (Exception $e) {
        $driver = 'sqlite';
        $sqlite_file = __DIR__ . '/../database.sqlite';
        $pdo = new PDO("sqlite:" . $sqlite_file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    initialize_clean_schema($pdo, $driver);
    auto_migrate_schema_columns($pdo, $driver);
    return $pdo;
}

function auto_migrate_schema_columns($pdo, $driver) {
    if ($driver !== 'mysql') {
        return;
    }
    try {
        // 1. Check & Alter kendaraan_alat
        $stmt_k = $pdo->query("SHOW COLUMNS FROM `kendaraan_alat`");
        if ($stmt_k) {
            $cols = $stmt_k->fetchAll(PDO::FETCH_COLUMN);

            $add_cols = [
                'kup_reg' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `kup_reg` VARCHAR(50) NULL AFTER `tahun`",
                'no_chasis' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `no_chasis` VARCHAR(50) NULL AFTER `kup_reg`",
                'no_mesin' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `no_mesin` VARCHAR(50) NULL AFTER `no_chasis`",
                'no_bpkb' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `no_bpkb` VARCHAR(50) NULL AFTER `no_mesin`",
                'kondisi' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `kondisi` VARCHAR(10) DEFAULT 'B' AFTER `no_bpkb`",
                'status_pemilik' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `status_pemilik` VARCHAR(30) DEFAULT 'APBD' AFTER `kondisi`",
                'lokasi_ruas' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `lokasi_ruas` VARCHAR(100) DEFAULT '1. Jatinegara - Slawi' AFTER `status_pemilik`",
                'jam_operasional' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `jam_operasional` INT NOT NULL DEFAULT 0 AFTER `interval_servis_bulan`",
                'interval_jam_servis' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `interval_jam_servis` INT NOT NULL DEFAULT 1000 AFTER `jam_operasional`",
                'sisa_jam_servis' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `sisa_jam_servis` INT NOT NULL DEFAULT 1000 AFTER `interval_jam_servis`",
                'tgl_jatuh_tempo_pajak' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `tgl_jatuh_tempo_pajak` DATE NULL AFTER `tgl_servis_berikutnya`",
                'tgl_jatuh_tempo_plat' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `tgl_jatuh_tempo_plat` DATE NULL AFTER `tgl_jatuh_tempo_pajak`",
                'tgl_jatuh_tempo_kir' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `tgl_jatuh_tempo_kir` DATE NULL AFTER `tgl_jatuh_tempo_plat`",
                'bulan_pajak' => "ALTER TABLE `kendaraan_alat` ADD COLUMN `bulan_pajak` INT NULL AFTER `tgl_jatuh_tempo_kir`"
            ];

            foreach ($add_cols as $col_name => $sql) {
                if (!in_array($col_name, $cols)) {
                    $pdo->exec($sql);
                }
            }

            // Convert enum 'jenis' to varchar(30) if necessary
            $pdo->exec("ALTER TABLE `kendaraan_alat` MODIFY COLUMN `jenis` VARCHAR(30) NOT NULL DEFAULT 'kendaraan_roda_4'");
            // Set default interval_servis_bulan to 6 for kendaraan
            $pdo->exec("ALTER TABLE `kendaraan_alat` MODIFY COLUMN `interval_servis_bulan` INT NOT NULL DEFAULT 6");
        }

        // 2. Check & Create log_pemakaian_alat
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `log_pemakaian_alat` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `id_kendaraan` INT NOT NULL,
                `jam_dipakai` INT NOT NULL,
                `total_jam_setelah_pakai` INT NOT NULL,
                `sisa_jam_servis_setelah_pakai` INT NOT NULL,
                `id_user` INT NULL,
                `nama_user` VARCHAR(50) NULL,
                `catatan` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 3. Check & Alter riwayat_servis
        $stmt_r = $pdo->query("SHOW COLUMNS FROM `riwayat_servis`");
        if ($stmt_r) {
            $cols_r = $stmt_r->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('nama_layanan', $cols_r)) {
                $pdo->exec("ALTER TABLE `riwayat_servis` ADD COLUMN `nama_layanan` VARCHAR(50) NOT NULL DEFAULT 'Servis Rutin Berkala'");
            }
            if (!in_array('foto_nota', $cols_r)) {
                $pdo->exec("ALTER TABLE `riwayat_servis` ADD COLUMN `foto_nota` VARCHAR(100) NULL");
            }
        }

        // 3. Check & Alter pengaturan_whatsapp
        $stmt_w = $pdo->query("SHOW COLUMNS FROM `pengaturan_whatsapp`");
        if ($stmt_w) {
            $cols_w = $stmt_w->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('target_phone', $cols_w)) {
                $pdo->exec("ALTER TABLE `pengaturan_whatsapp` ADD COLUMN `target_phone` VARCHAR(20) NULL");
            }
            if (!in_array('api_token', $cols_w)) {
                $pdo->exec("ALTER TABLE `pengaturan_whatsapp` ADD COLUMN `api_token` VARCHAR(100) NULL");
            }
        }

        // 4. Check & Alter users
        $stmt_u = $pdo->query("SHOW COLUMNS FROM `users`");
        if ($stmt_u) {
            $cols_u = $stmt_u->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('foto_profil', $cols_u)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `foto_profil` VARCHAR(100) NULL AFTER `jabatan`");
            }
        }

        // 5. Ensure nota_teknisi table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `nota_teknisi` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `id_kendaraan` INT NOT NULL,
                `tgl_nota` DATE NOT NULL,
                `jenis_servis` VARCHAR(30) NOT NULL DEFAULT 'Penjadwalan',
                `nama_layanan` VARCHAR(100) NOT NULL,
                `total_biaya` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `nama_bengkel` VARCHAR(100) NULL,
                `foto_nota` VARCHAR(100) NOT NULL,
                `catatan` TEXT NULL,
                `dikirim_oleh` VARCHAR(50) NOT NULL DEFAULT 'Teknisi',
                `status` VARCHAR(30) NOT NULL DEFAULT 'Menunggu Verifikasi',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 6. Ensure penerima_whatsapp table (Multi-Target Recipients)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `penerima_whatsapp` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nama_penerima` VARCHAR(100) NOT NULL,
                `nomor_whatsapp` VARCHAR(30) NOT NULL,
                `jabatan` VARCHAR(50) NULL DEFAULT 'Operasional',
                `is_aktif` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $cnt_pen = $pdo->query("SELECT COUNT(*) FROM `penerima_whatsapp`")->fetchColumn();
        if ($cnt_pen == 0) {
            $pdo->exec("INSERT INTO `penerima_whatsapp` (`nama_penerima`, `nomor_whatsapp`, `jabatan`, `is_aktif`) VALUES ('Pimpinan / Operasional', '082225352170', 'Pimpinan', 1)");
        }
    } catch (Exception $e) {
        // Silently continue if error
    }
}

function initialize_clean_schema($pdo, $driver) {
    if ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(30) NOT NULL UNIQUE,
                `password` VARCHAR(100) NOT NULL,
                `nama_lengkap` VARCHAR(50) NOT NULL,
                `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
                `jabatan` VARCHAR(50) NULL,
                `foto_profil` VARCHAR(100) NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `kendaraan_alat` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `kode_plat` VARCHAR(20) NOT NULL,
                `nama` VARCHAR(50) NOT NULL,
                `jenis` VARCHAR(30) NOT NULL DEFAULT 'kendaraan_roda_4',
                `kategori` VARCHAR(50) NOT NULL,
                `merk` VARCHAR(50) NULL,
                `tahun` INT NULL,
                `kup_reg` VARCHAR(50) NULL,
                `no_chasis` VARCHAR(50) NULL,
                `no_mesin` VARCHAR(50) NULL,
                `no_bpkb` VARCHAR(50) NULL,
                `kondisi` VARCHAR(10) DEFAULT 'B',
                `status_pemilik` VARCHAR(30) DEFAULT 'APBD BPJ TEGAL',
                `lokasi_ruas` VARCHAR(100) DEFAULT '1. Jatinegara - Slawi',
                `penanggung_jawab` VARCHAR(50) NULL,
                `interval_servis_bulan` INT NOT NULL DEFAULT 3,
                `tgl_servis_terakhir` DATE NULL,
                `tgl_servis_berikutnya` DATE NULL,
                `tgl_jatuh_tempo_pajak` DATE NULL,
                `tgl_jatuh_tempo_plat` DATE NULL,
                `tgl_jatuh_tempo_kir` DATE NULL,
                `bulan_pajak` INT NULL,
                `status` VARCHAR(20) DEFAULT 'Baik',
                `foto` VARCHAR(100) NULL,
                `catatan` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `riwayat_servis` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `id_kendaraan` INT NOT NULL,
                `tgl_servis` DATE NOT NULL,
                `jenis_servis` VARCHAR(30) NOT NULL DEFAULT 'Penjadwalan',
                `nama_layanan` VARCHAR(50) NOT NULL,
                `rincian_item` TEXT NULL,
                `total_biaya` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `nama_bengkel` VARCHAR(50) NULL,
                `foto_nota` VARCHAR(100) NULL,
                `catatan` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `pembayaran_pajak_kir` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `id_kendaraan` INT NOT NULL,
                `jenis_pembayaran` VARCHAR(30) NOT NULL DEFAULT 'Pajak STNK',
                `tgl_bayar` DATE NOT NULL,
                `tgl_jatuh_tempo_baru` DATE NOT NULL,
                `nominal_biaya` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `foto_bukti` VARCHAR(100) NULL,
                `catatan` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `riwayat_perubahan_plat` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `id_kendaraan` INT NOT NULL,
                `plat_lama` VARCHAR(20) NOT NULL,
                `plat_baru` VARCHAR(20) NOT NULL,
                `tgl_perubahan` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `keterangan` TEXT NULL,
                `diubah_oleh` VARCHAR(50) NULL,
                FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `pengaturan_whatsapp` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `api_token` VARCHAR(100) NULL,
                `target_phone` VARCHAR(20) NULL,
                `notif_h30_aktif` TINYINT(1) DEFAULT 1,
                `notif_h7_aktif` TINYINT(1) DEFAULT 1,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `nota_teknisi` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `id_kendaraan` INT NOT NULL,
                `tgl_nota` DATE NOT NULL,
                `jenis_servis` VARCHAR(30) NOT NULL DEFAULT 'Penjadwalan',
                `nama_layanan` VARCHAR(100) NOT NULL,
                `total_biaya` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `nama_bengkel` VARCHAR(100) NULL,
                `foto_nota` VARCHAR(100) NOT NULL,
                `catatan` TEXT NULL,
                `dikirim_oleh` VARCHAR(50) NOT NULL DEFAULT 'Teknisi',
                `status` VARCHAR(30) NOT NULL DEFAULT 'Menunggu Verifikasi',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`id_kendaraan`) REFERENCES `kendaraan_alat`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `penerima_whatsapp` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nama_penerima` VARCHAR(100) NOT NULL,
                `nomor_whatsapp` VARCHAR(30) NOT NULL,
                `jabatan` VARCHAR(50) NULL DEFAULT 'Operasional',
                `is_aktif` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nota_teknisi (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_kendaraan INTEGER NOT NULL,
                tgl_nota TEXT NOT NULL,
                jenis_servis TEXT NOT NULL DEFAULT 'Penjadwalan',
                nama_layanan TEXT NOT NULL,
                total_biaya REAL NOT NULL DEFAULT 0.0,
                nama_bengkel TEXT,
                foto_nota TEXT NOT NULL,
                catatan TEXT,
                dikirim_oleh TEXT NOT NULL DEFAULT 'Teknisi',
                status TEXT NOT NULL DEFAULT 'Menunggu Verifikasi',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                nama_lengkap TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin',
                jabatan TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS kendaraan_alat (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kode_plat TEXT NOT NULL,
                nama TEXT NOT NULL,
                jenis TEXT NOT NULL DEFAULT 'kendaraan_roda_4',
                kategori TEXT NOT NULL,
                merk TEXT,
                tahun INTEGER,
                kup_reg TEXT,
                no_chasis TEXT,
                no_mesin TEXT,
                no_bpkb TEXT,
                kondisi TEXT DEFAULT 'B',
                status_pemilik TEXT DEFAULT 'APBD',
                lokasi_ruas TEXT DEFAULT '1. Jatinegara - Slawi',
                penanggung_jawab TEXT,
                interval_servis_bulan INTEGER NOT NULL DEFAULT 6,
                jam_operasional INTEGER DEFAULT 0,
                interval_jam_servis INTEGER DEFAULT 1000,
                sisa_jam_servis INTEGER DEFAULT 1000,
                tgl_servis_terakhir TEXT,
                tgl_servis_berikutnya TEXT,
                tgl_jatuh_tempo_pajak TEXT,
                tgl_jatuh_tempo_plat TEXT,
                tgl_jatuh_tempo_kir TEXT,
                bulan_pajak INTEGER,
                status TEXT DEFAULT 'Baik',
                foto TEXT,
                catatan TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS riwayat_servis (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_kendaraan INTEGER NOT NULL,
                tgl_servis TEXT NOT NULL,
                jenis_servis TEXT NOT NULL DEFAULT 'Penjadwalan',
                nama_layanan TEXT NOT NULL,
                rincian_item TEXT,
                total_biaya REAL NOT NULL DEFAULT 0.0,
                nama_bengkel TEXT,
                foto_nota TEXT,
                catatan TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS pembayaran_pajak_kir (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_kendaraan INTEGER NOT NULL,
                jenis_pembayaran TEXT NOT NULL DEFAULT 'Pajak STNK',
                tgl_bayar TEXT NOT NULL,
                tgl_jatuh_tempo_baru TEXT NOT NULL,
                nominal_biaya REAL NOT NULL DEFAULT 0.0,
                foto_bukti TEXT,
                catatan TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS riwayat_perubahan_plat (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                id_kendaraan INTEGER NOT NULL,
                plat_lama TEXT NOT NULL,
                plat_baru TEXT NOT NULL,
                tgl_perubahan DATETIME DEFAULT CURRENT_TIMESTAMP,
                keterangan TEXT,
                diubah_oleh TEXT
            );

            CREATE TABLE IF NOT EXISTS pengaturan_whatsapp (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                api_token TEXT,
                target_phone TEXT,
                notif_h30_aktif INTEGER DEFAULT 1,
                notif_h7_aktif INTEGER DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    auto_migrate_schema_columns($pdo, $driver);
    seed_alisa_pure_single_data($pdo);
}

function seed_alisa_pure_single_data($pdo) {
    // 1. Seed & Sync Users (Exact 4 Actors)
    $users = [
        ['admin', password_hash('123456', PASSWORD_DEFAULT), 'Admin', 'admin', 'Admin Pengelola ALISA'],
        ['teknisi', password_hash('123456', PASSWORD_DEFAULT), 'Teknisi', 'teknisi', 'Teknisi Armada & Pemeliharaan'],
        ['pimpinan', password_hash('123456', PASSWORD_DEFAULT), 'Pimpinan', 'pimpinan', 'Pimpinan Balai BPJ Tegal'],
        ['bendahara', password_hash('123456', PASSWORD_DEFAULT), 'Bendahara', 'bendahara', 'Bendahara Balai (Pajak & Keuangan)']
    ];

    foreach ($users as $u) {
        $stmt_chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR role = ? LIMIT 1");
        $stmt_chk->execute([$u[0], $u[3]]);
        $exist = $stmt_chk->fetch();

        if (!$exist) {
            $stmt_ins = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role, jabatan) VALUES (?, ?, ?, ?, ?)");
            $stmt_ins->execute($u);
        }
    }

    // 2. Seed WA Settings
    $stmt_w = $pdo->query("SELECT COUNT(*) FROM pengaturan_whatsapp");
    if ($stmt_w->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO pengaturan_whatsapp (api_token, target_phone, notif_h30_aktif, notif_h7_aktif) VALUES ('', '082225352170', 1, 1)");
    }

    // 3. Sample fallback initialization if database is completely empty
    $stmt_k = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat");
    if ($stmt_k->fetchColumn() == 0) {
        // Run reset_db.php helper logic if empty
    }
}
