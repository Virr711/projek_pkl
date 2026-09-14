<?php
/**
 * ALISA - Reset & Initializer Database Script
 * Balai Pengelolaan Jalan Wilayah Tegal - POLITEKNIK PURBAYA
 * Resets database to complete 71 units (Data 1), Servis Matrix (Data 2), Pajak Matrix (Data 3), & Equipment Hours
 */

require_once __DIR__ . '/config/database.php';

// 0. Backup existing real user-input data before reset
$saved_servis       = [];
$saved_pajak        = [];
$saved_penerima_wa  = [];
$saved_nota_teknisi = [];
$saved_plat_hist    = [];
$saved_log_alat     = [];

try {
    $existing_pdo = get_db();
    $saved_servis       = $existing_pdo->query("SELECT * FROM riwayat_servis")->fetchAll(PDO::FETCH_ASSOC);
    $saved_pajak        = $existing_pdo->query("SELECT * FROM pembayaran_pajak_kir")->fetchAll(PDO::FETCH_ASSOC);
    $saved_penerima_wa  = $existing_pdo->query("SELECT * FROM penerima_whatsapp")->fetchAll(PDO::FETCH_ASSOC);
    $saved_nota_teknisi = $existing_pdo->query("SELECT * FROM nota_teknisi")->fetchAll(PDO::FETCH_ASSOC);
    $saved_plat_hist    = $existing_pdo->query("SELECT * FROM riwayat_perubahan_plat")->fetchAll(PDO::FETCH_ASSOC);
    $saved_log_alat     = $existing_pdo->query("SELECT * FROM log_pemakaian_alat")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silently skip if DB/table doesn't exist yet
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdo->exec("DROP DATABASE IF EXISTS `" . DB_NAME . "`");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $driver_name = 'mysql';
    initialize_clean_schema($pdo, $driver_name);
} catch (Exception $e) {
    $pdo = get_db();
    $driver_name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

$sqlite_file = __DIR__ . '/database.sqlite';
if (file_exists($sqlite_file)) {
    @unlink($sqlite_file);
}

// Ensure matrix checklist table exists
if ($driver_name === 'mysql') {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `servis_checklist` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `id_kendaraan` int(11) NOT NULL,
      `item_servis` varchar(50) NOT NULL,
      `interval_servis` varchar(50) DEFAULT NULL,
      `tgl_servis_terakhir` date DEFAULT NULL,
      `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `id_kendaraan` (`id_kendaraan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS servis_checklist (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      id_kendaraan INTEGER NOT NULL,
      item_servis TEXT NOT NULL,
      interval_servis TEXT DEFAULT NULL,
      tgl_servis_terakhir TEXT DEFAULT NULL,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ");
}

// Clear any existing data safely
$tables_to_clear = ['servis_checklist', 'pembayaran_pajak_kir', 'riwayat_servis', 'log_pemakaian_alat', 'riwayat_perubahan_plat', 'kendaraan_alat', 'pengaturan_whatsapp', 'nota_teknisi', 'penerima_whatsapp', 'users'];
if ($driver_name === 'mysql') {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
} else {
    $pdo->exec("PRAGMA foreign_keys = OFF;");
}

foreach ($tables_to_clear as $tbl) {
    try {
        $pdo->exec("DELETE FROM `$tbl`;");
    } catch (Exception $e) {
        // Table might not exist yet on fresh init, safely skip
    }
}

if ($driver_name === 'mysql') {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
} else {
    $pdo->exec("PRAGMA foreign_keys = ON;");
}

// 1. Seed Users (4 Actors)
$pwd = password_hash('123456', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `jabatan`) VALUES
(1, 'admin', ?, 'Admin', 'admin', 'Admin Pengelola ALISA'),
(2, 'teknisi', ?, 'Teknisi', 'teknisi', 'Teknisi Armada & Pemeliharaan'),
(3, 'pimpinan', ?, 'Pimpinan', 'pimpinan', 'Pimpinan Balai BPJ Tegal'),
(4, 'bendahara', ?, 'Bendahara', 'bendahara', 'Bendahara Balai (Pajak & Keuangan)')");
$stmt->execute([$pwd, $pwd, $pwd, $pwd]);

// 2. Seed WA Settings & Restore Multi-Recipients
$pdo->exec("INSERT INTO `pengaturan_whatsapp` (`id`, `api_token`, `target_phone`, `notif_h30_aktif`, `notif_h7_aktif`) VALUES (1, '', '082225352170', 1, 1);");

if (!empty($saved_penerima_wa)) {
    $stmt_r_wa = $pdo->prepare("INSERT INTO `penerima_whatsapp` (`id`, `nama_penerima`, `nomor_whatsapp`, `jabatan`, `is_aktif`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($saved_penerima_wa as $pwa) {
        $stmt_r_wa->execute([
            $pwa['id'],
            $pwa['nama_penerima'],
            $pwa['nomor_whatsapp'],
            $pwa['jabatan'] ?? 'Operasional',
            $pwa['is_aktif'] ?? 1,
            $pwa['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }
} else {
    $pdo->exec("INSERT INTO `penerima_whatsapp` (`nama_penerima`, `nomor_whatsapp`, `jabatan`, `is_aktif`) VALUES ('Pimpinan / Operasional', '082225352170', 'Pimpinan', 1);");
}

// 3. Seed Master Units (Data 1)
$roda_6 = [
    ['H-8134-VW', 'RANSUS / CRANE', 'kendaraan_roda_6', 'Ransus / Crane', 'ISUZU', 2020, 'NHCFVR34PEJ000354', '6HK1655047', 'BPKB-H8134VW', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Pak Daim (Teknisi Operasional)', '2025-12-15', '2029-08-09', 12],
    ['H-9598-VW', 'RANSUS / CRANE', 'kendaraan_roda_6', 'Ransus / Crane', 'ISUZU', 2020, 'NHCFVR34PEJ000354', '6HK1655047', 'BPKB-H9598VW', 'B', '1. Jatinegara - Slawi', 'Petugas Operasional Ruas 1', '2025-08-09', '2029-08-09', 8],
    ['H-8095 XW', 'DUMP TRUCK NCR', 'kendaraan_roda_6', 'Dump Truck', 'ISUZU', 2018, 'MHCNK66LY3J003816', 'W003816', 'BPKB-H8095XW', 'RB', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Workshop', '2025-08-09', '2028-07-14', 8],
    ['H 8030 XG', 'DUMP TRUCK', 'kendaraan_roda_6', 'Dump Truck', 'MITSUBISHI', 2017, 'FJ40283742', '2F288824', 'BPKB-H8030XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Workshop', '2025-07-14', '2025-09-30', 7],
    ['H 8075 XG', 'DUMP TRUCK', 'kendaraan_roda_6', 'Dump Truck', 'ISUZU', 2017, 'MHCNR71HNJ070117', 'B070117', 'BPKB-H8075XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Workshop', '2025-09-30', '2025-09-30', 9],
    ['H 8318 XG', 'TRUCK TOWING', 'kendaraan_roda_6', 'Truck Towing', 'ISUZU', 2019, 'MHCNR71HNJ130495', 'B130495', 'BPKB-H8318XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Towing', '2025-08-11', '2027-08-10', 8, 'PR/213/D.03'],
    ['H 8321 XG', 'DUMP TRUCK', 'kendaraan_roda_6', 'Dump Truck', 'ISUZU', 2019, 'MHCNR71HNJ129980', 'B129980', 'BPKB-H8321XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Workshop', '2025-08-27', '2027-08-26', 8, 'PR/212/D.31']
];

$roda_4 = [
    ['H 1952 XR', 'TERIOS F700RG-TSMT', 'kendaraan_roda_4', 'Minibus / Operasional', 'DAIHATSU', 2020, 'MHKG2CJ1JEKO26233', 'DEP6933', 'BPKB-H1952XR', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Operasional', '2025-08-07', '2029-08-07', 8],
    ['H 8114 XG', 'GRAN MAX', 'kendaraan_roda_4', 'Pick Up', 'DAIHATSU', 2018, 'MHKP3CAIJJFK102285', '-', 'BPKB-H8114XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Operasional', '2025-12-06', '2025-12-06', 12],
    ['H 8115 XG', 'GRAN MAX', 'kendaraan_roda_4', 'Pick Up', 'DAIHATSU', 2018, 'MHKP3CAIJJFK102281', '3SZDFS0595', 'BPKB-H8115XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Operasional', '2025-12-06', '2025-12-06', 12],
    ['H 8468 AZ', 'GRAN MAX', 'kendaraan_roda_4', 'Pick Up', 'DAIHATSU', 2019, 'MHKP3CA1JJK175037', '3SZDGR0404', 'BPKB-H8468AZ', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Operasional', '2025-10-01', '2028-10-01', 10],
    ['H 8469 AZ', 'GRAN MAX', 'kendaraan_roda_4', 'Pick Up', 'DAIHATSU', 2019, 'MHKP3CA1JJK174748', '3SZDGP9781', 'BPKB-H8469AZ', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Operasional', '2025-10-01', '2028-10-01', 10],
    ['H 8293 XG', 'MBRG/PICK UP', 'kendaraan_roda_4', 'Pick Up', 'SUZUKI', 2019, 'MHYHDC61TNJ216917', 'K15BT1368685', 'BPKB-H8293XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Operasional', '2025-04-26', '2027-04-26', 4],
    ['H 8289 XG', 'MBRG/PICK UP', 'kendaraan_roda_4', 'Pick Up', 'SUZUKI', 2019, 'MHYHDC61TNJ216966', 'K15BT1368486', 'BPKB-H8289XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Operasional', '2025-04-26', '2027-04-26', 4]
];

$roda_2 = [
    ['H 6192 XG', 'SEPEDA MOTOR VARIO', 'kendaraan_roda_2', 'Sepeda Motor', 'HONDA', 2019, 'MHIJFH119FK490454', 'JFH1E1488965', 'BPKB-H6192XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Lapangan', '2025-07-29', '2025-07-29', 7],
    ['H 6426 XG', 'SEPEDA MOTOR REVO', 'kendaraan_roda_2', 'Sepeda Motor', 'HONDA', 2018, 'MHIJBK318FK1221980', 'JB1C3E1122610', 'BPKB-H6426XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Lapangan', '2025-12-06', '2025-12-06', 12],
    ['H 6429 XG', 'SEPEDA MOTOR REVO', 'kendaraan_roda_2', 'Sepeda Motor', 'HONDA', 2018, 'MHIJBK318FK122062', 'JB1C3E1123574', 'BPKB-H6429XG', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Lapangan', '2025-12-06', '2025-12-06', 12],
    ['H 9749 AZ', 'SEPEDA MOTOR CRV', 'kendaraan_roda_2', 'Sepeda Motor', 'HONDA', 2020, 'MH1KD1111KK080209', 'KDIIE1079490', 'BPKB-H9749AZ', 'B', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Lapangan', '2025-10-20', '2029-10-21', 10]
];

$roda_3 = [
    ['H 6426 XG (VIAR)', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2015, 'MGRVR20TAFL920224', 'YX200FMG15206318', 'M05446021', 'RB', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Pemeliharaan', '2025-12-06', '2025-12-06', 12],
    ['H 6427 XG', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2015, 'MGRVR20TAFL920218', 'YX200FMG15206259', 'M05446015', 'B', '2. Slawi - Jatibarang', 'Petugas Pemeliharaan Ruas', '2025-12-06', '2025-12-06', 12],
    ['H-6791 XR', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2018, 'MGRVR20TAJL202510', 'YX200FMG18202405', '0042957721', 'RB', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Pemeliharaan', '2025-05-25', '2028-05-24', 5],
    ['H-6792 XR', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2018, 'MGRVR20TAJL202015', 'YX200FMG18201871', '0042957711', 'B', '9. Sirampog - Bumiayu', 'Petugas Pemeliharaan Ruas', '2025-05-25', '2028-05-24', 5],
    ['H 6567 XG', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2021, 'MGRVR20TAML201181', 'YX200FMG21200363', '0066627241', 'B', '5. Kersana - Bandungsari', 'Petugas Pemeliharaan Ruas', '2025-03-16', '2026-03-16', 3],
    ['H 6582 XG', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2021, 'MGRVR20TAML201154', 'YX200FMG21200474', '0066627201', 'RB', '12. Workshop / POOL BPJ WILAYAH TEGAL', 'Petugas Pemeliharaan', '2025-03-16', '2026-03-16', 3],
    ['H 6577 XG', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2021, 'MGRVR20TAML201158', 'YX200FMG21200451', '0066627121', 'B', '8. Bumiayu - Salem', 'Petugas Pemeliharaan Ruas', '2025-03-16', '2026-03-16', 3],
    ['H 6563 XG', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2021, 'MGRVR20TAML201152', 'YX200FMG21200390', '0066627021', 'B', '4. Ketanggungan - Kersana - Bantarsari', 'Petugas Pemeliharaan Ruas', '2025-03-16', '2026-03-16', 3],
    ['H 6219 XR', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2022, 'MGRVR15TAML200931', 'YX161FMG22210188', 'S047073551', 'B', '10. Morongso - Tuwel - Sirampog', 'Petugas Pemeliharaan Ruas', '2025-05-08', '2027-05-08', 5],
    ['H 6220 XR', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2022, 'MGRVR15TAML200980', 'YX161FMG22210699', 'S047073531', 'B', '3. Jatibarang - Ketanggungan', 'Petugas Pemeliharaan Ruas', '2025-05-08', '2027-05-08', 5],
    ['H 6244 XR', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2022, 'MGRVR15TAML201113', 'YX161FMG22210462', 'S047073841', 'B', '8. Bumiayu - Salem', 'Petugas Pemeliharaan Ruas', '2025-05-08', '2027-05-08', 5],
    ['H 6712 XR', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2023, 'MGRVR30TAPL100252', 'YX300FMG22101524', 'T031284461', 'B', '6. Bandungsari - Salem', 'Petugas Pemeliharaan Ruas', '2025-04-06', '2028-04-06', 4],
    ['H 6723 XR', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2023, 'MGRVR30TAPL100310', 'YX300FMG23000018', 'T031284571', 'B', '1. Jatinegara - Slawi', 'Petugas Pemeliharaan Ruas', '2025-04-06', '2028-04-06', 4],
    ['H 6183 XZ', 'SPM RODA TIGA VIAR', 'kendaraan_roda_3', 'Roda Tiga Viar', 'VIAR', 2023, 'MGRVR30TAPL300936', 'YX300FMG23300554', 'UO31954331', 'B', '7. Bandungsari - Penanggapan', 'Petugas Pemeliharaan Ruas', '2025-01-18', '2029-01-18', 1]
];

$peralatan = [
    ["PR/086/D.86", "TANDEM VIBRATION ROLLER (4 TON)", "peralatan", "Vibration Roller / Alat Berat", "HAMM HD 13 VV / 4 TON", 2016, "PR/086/D.86", "H.2013294", "Kubota A - 52212", "RB", "8. Bumiayu - Salem", "Operator Alat Berat", 0, 1000, 1000],
    ["PR/086/D.21", "TANDEM VIBRATION ROLLER", "peralatan", "Vibration Roller / Alat Berat", "HAMM", 2016, "PR/086/D.21", "H 1992086", "Kubota D 1005 - 1 DN 8857", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 850, 1000, 150],
    ["PR/086/D.19", "TANDEM ROLLER", "peralatan", "Vibration Roller / Alat Berat", "BOMAG", 2015, "PR/086/D.19", "-", "-", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 920, 1000, 80],
    ["PR/086/D.53", "TANDEM ROLLER", "peralatan", "Vibration Roller / Alat Berat", "BOMAG", 2015, "PR/086/D.53", "-", "-", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 400, 1000, 600],
    ["PR/088/D.03", "VIBRATION ROLLER", "peralatan", "Vibration Roller / Alat Berat", "TACOM TMR 75", 2011, "PR/088/D.03", "1.001.00`1", "E.75-90.0099", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 0, 1000, 1000],
    ["PR/086/D.76", "VIBRATION ROLLER (2,5 TON)", "peralatan", "Vibration Roller / Alat Berat", "BOMAG BW - 100 AD.4", 2014, "PR/086/D.76", "861880251169", "7EP 5604-4710 K4-CD-15", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 940, 1000, 60],
    ["PR/086/D.77", "VIBRATION ROLLER (2,5 TON)", "peralatan", "Vibration Roller / Alat Berat", "BOMAG BW - 100 AD.4", 2014, "PR/086/D.77", "861880251170", "7EP 5684-4710 K4-CD-15", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 0, 1000, 1000],
    ["PR/088/D.29", "VIBRATION ROLLER", "peralatan", "Vibration Roller / Alat Berat", "BOMAG", 2015, "PR/088/D.29", "-", "-", "RB", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 0, 1000, 1000],
    ["PR/088/D.30", "VIBRATION ROLLER", "peralatan", "Vibration Roller / Alat Berat", "BOMAG", 2015, "PR/088/D.30", "-", "-", "RB", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 0, 1000, 1000],
    ["PR/052/D.07", "WHEEL LOADER", "peralatan", "Wheel Loader / Alat Berat", "KOMATSU WA 150 - 5", 2015, "PR/052/D.07", "77452", "26497794", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 500, 1000, 500],
    ["PR/281/D. 05", "MINI EXCAVATOR", "peralatan", "Excavator / Alat Berat", "TAKEUCHI TB 150 C", 2014, "PR/281/D. 05", "JGJ-1008025", "J-2127", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Operator Alat Berat", 980, 1000, 20],
    ["PR/088/D.22", "PLATE COMPACTOR STAMPER", "peralatan", "Stamper / Peralatan", "Atlas Copco", 2015, "PR/088/D.22", "-", "GC.BPT 1831255", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/251/D.31", "CONCRET MIXER", "peralatan", "Concrete Mixer", "KUBOTA RD65DIH - IS", 2018, "PR/251/D.31", "-", "KI - AJS0061", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/251/D.32", "CONCRET MIXER", "peralatan", "Concrete Mixer", "KUBOTA RD65DIH - IS", 2018, "PR/251/D.32", "-", "KI - AJS0044", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/251/D.33", "CONCRET MIXER", "peralatan", "Concrete Mixer", "KUBOTA RD65DIH - IS", 2018, "PR/251/D.33", "-", "KI - AJS0067", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/255/D.31", "CONCRETE CUTTER", "peralatan", "Concrete Cutter", "Atlas Copco ORKA", 2018, "PR/255/D.31", "-", "GCBCT - 2450133", "RR", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/255/D.32", "CONCRETE CUTTER", "peralatan", "Concrete Cutter", "Atlas Copco ORKA", 2018, "PR/255/D.32", "-", "GCBCT - 240132", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/255/D.33", "CONCRETE CUTTER", "peralatan", "Concrete Cutter", "Atlas Copco ORKA", 2018, "PR/255/D.33", "-", "GCBCT - 2219174", "RR", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/PUMP/D.08", "POMPA AIR WATER PUMP", "peralatan", "Pompa Air", "MULTI PRO", 2015, "PR/PUMP/D.08", "-", "090603132 LT", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["24/152/D.44", "ASPHALT SPRAYER", "peralatan", "Asphalt Sprayer", "Bukaka BAS 850-TA", 2018, "24/152/D.44", "-", "RD 65 - AJJ3562", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["24/152/D.45", "ASPHALT SPRAYER", "peralatan", "Asphalt Sprayer", "Bukaka BAS 850-TA", 2018, "24/152/D.45", "-", "RD 65 - AJJ3552", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["24/152/D.46", "ASPHALT SPRAYER", "peralatan", "Asphalt Sprayer", "Bukaka BAS 850-TA", 2018, "24/152/D.46", "-", "RD 65 - AJE2164", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/301/D.13", "JACK HEMMER", "peralatan", "Jack Hammer", "BOSH", 2018, "PR/301/D.13", "-", "GC - 180250877", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/301/D.14", "GENERATOR SET", "peralatan", "Generator Set", "GENERAL", 2018, "PR/301/D.14", "-", "802000115", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/301/D.15", "JACK HEMMER / GENSET", "peralatan", "Generator Set", "GENERAL / BOSH", 2018, "PR/301/D.15", "-", "GC - 180250962", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["10.01.01.05.009", "MESIN LAS LISTRIK", "peralatan", "Mesin Las", "MULTI PRO MMA 200 G - KR", 2018, "10.01.01.05.009", "-", "802000231", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.251", "CHAIN SAW", "peralatan", "Chain Saw", "MULTI PRO CS-2258/2 YSL", 2022, "PR/019/D.251", "-", "-", "RB", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/088/D.28", "PLATE COMPACTOR STAMPER", "peralatan", "Stamper / Peralatan", "MATRIK C90A", 2022, "PR/088/D.28", "-", "HONDA GX 160 GCAWH 1733881", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/301/D.29", "GENSET", "peralatan", "Generator Set", "MATSUMOTO PLATINUM MGG-3990ET", 2022, "PR/301/D.29", "-", "2112240026", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.373", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.373", "-", "GCAMT 6966388", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.374", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.374", "-", "GCAMT 7052253", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.375", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.375", "-", "GCAMT 7052214", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.376", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.376", "-", "GCAMT 7052236", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.377", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.377", "-", "GCAMT 7052211", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.378", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.378", "-", "GCAMT 7052252", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.379", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.379", "-", "GCAMT 7052231", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.380", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.380", "-", "GCAMT 6966454", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000],
    ["PR/019/D.381", "GRASS CUTTER", "peralatan", "Grass Cutter", "Honda UMR 435N", 2022, "PR/019/D.381", "-", "GCAMT 6966453", "B", "12. Workshop / POOL BPJ WILAYAH TEGAL", "Petugas Lapangan", 0, 1000, 1000]
];

$pdo->beginTransaction();

$stmt_ins = $pdo->prepare("
INSERT INTO `kendaraan_alat` 
(`kode_plat`, `nama`, `jenis`, `kategori`, `merk`, `tahun`, `kup_reg`, `no_chasis`, `no_mesin`, `no_bpkb`, `kondisi`, `lokasi_ruas`, `penanggung_jawab`, `tgl_servis_terakhir`, `tgl_servis_berikutnya`, `tgl_jatuh_tempo_pajak`, `tgl_jatuh_tempo_plat`, `bulan_pajak`, `jam_operasional`, `interval_jam_servis`, `sisa_jam_servis`, `interval_servis_bulan`) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($roda_6 as $u) {
    $tgl_last = '2026-06-15';
    $tgl_next = '2026-09-15';
    $kup = $u[15] ?? null;
    $stmt_ins->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $kup, $u[6], $u[7], $u[8], $u[9], $u[10], $u[11], $tgl_last, $tgl_next, $u[12], $u[13], $u[14], 0, 0, 0, 6]);
}

foreach ($roda_4 as $u) {
    $tgl_last = '2026-06-20';
    $tgl_next = '2026-09-20';
    $stmt_ins->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], null, $u[6], $u[7], $u[8], $u[9], $u[10], $u[11], $tgl_last, $tgl_next, $u[12], $u[13], $u[14], 0, 0, 0, 6]);
}

foreach ($roda_2 as $u) {
    $tgl_last = '2026-07-01';
    $tgl_next = '2026-10-01';
    $stmt_ins->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], null, $u[6], $u[7], $u[8], $u[9], $u[10], $u[11], $tgl_last, $tgl_next, $u[12], $u[13], $u[14], 0, 0, 0, 6]);
}

foreach ($roda_3 as $u) {
    $tgl_last = '2026-07-10';
    $tgl_next = '2026-10-10';
    $stmt_ins->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], null, $u[6], $u[7], $u[8], $u[9], $u[10], $u[11], $tgl_last, $tgl_next, $u[12], $u[13], $u[14], 0, 0, 0, 6]);
}

foreach ($peralatan as $u) {
    $tgl_last = '2026-06-30';
    $jam_op = $u[12] ?? 0;
    $int_jam = $u[13] ?? 1000;
    $sisa_jam = $u[14] ?? 1000;
    $stmt_ins->execute([$u[0], $u[1], $u[2], $u[3], $u[4], $u[5], $u[6], $u[7], $u[8], null, $u[9], $u[10], $u[11], $tgl_last, '0000-00-00', null, null, null, $jam_op, $int_jam, $sisa_jam, 0]);
}

// 4. Seed Checklist Items (Data 2)
$checklist_items = [
    ["Ganti oli mesin", "5.000–10.000 km / 6 bulan"],
    ["Ganti/Filter oli", "10.000 km / 6–12 bulan"],
    ["Pemeriksaan Rem", "5.000 km / 3–6 bulan"],
    ["Pemeriksaan Ban", "Setiap bulan"],
    ["Pemeriksaan Aki/Baterai", "Setiap bulan"],
    ["Pemeriksaan Air Radiator/Coolant", "Setiap bulan"],
    ["Pemeriksaan Filter Udara", "10.000–20.000 km"],
    ["Pemeriksaan Oli Transmisi", "20.000–40.000 km"],
    ["Pemeriksaan Oli Garden", "20.000–40.000 km"],
    ["Pemeriksaan Kaki-kaki", "10.000 km / 6 bulan"],
    ["Pemeriksaan Lampu dan Kelistrikan", "Setiap bulan"],
    ["Pemeriksaan Wiper dan Air Washer", "Setiap bulan"],
    ["Servis AC", "6–12 bulan"],
    ["Spooring dan Balancing", "10.000–20.000 km / sesuai kondisi"]
];

$all_units = $pdo->query("SELECT id FROM kendaraan_alat")->fetchAll();
$stmt_chk = $pdo->prepare("INSERT INTO `servis_checklist` (`id_kendaraan`, `item_servis`, `interval_servis`, `tgl_servis_terakhir`) VALUES (?, ?, ?, ?)");

foreach ($all_units as $u) {
    $uid = $u['id'];
    foreach ($checklist_items as $ci) {
        $tgl_chk = date('Y-m-d', strtotime('-' . rand(10, 90) . ' days'));
        $stmt_chk->execute([$uid, $ci[0], $ci[1], $tgl_chk]);
    }
}

$pdo->commit();

// 5. Seed / Restore License Plate Change History
if (!empty($saved_plat_hist)) {
    $stmt_rplat_res = $pdo->prepare("INSERT INTO riwayat_perubahan_plat (id_kendaraan, plat_lama, plat_baru, tgl_perubahan, keterangan, diubah_oleh) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($saved_plat_hist as $sph) {
        $stmt_rplat_res->execute([
            $sph['id_kendaraan'],
            $sph['plat_lama'],
            $sph['plat_baru'],
            $sph['tgl_perubahan'] ?? date('Y-m-d H:i:s'),
            $sph['keterangan'] ?? '',
            $sph['diubah_oleh'] ?? 'Admin'
        ]);
    }
} else {
    $stmt_rplat = $pdo->prepare("INSERT INTO riwayat_perubahan_plat (id_kendaraan, plat_lama, plat_baru, tgl_perubahan, keterangan, diubah_oleh) VALUES (?, ?, ?, ?, ?, ?)");

    $unit_dump = $pdo->query("SELECT id FROM kendaraan_alat WHERE kode_plat LIKE '%8095%' LIMIT 1")->fetch();
    if ($unit_dump) {
        $stmt_rplat->execute([$unit_dump['id'], 'H-8095 W', 'H-8095 XW', '2023-08-14 10:30:00', 'Ganti Plat Nopol 5-Tahunan (Perpanjangan STNK Kaleng)', 'Bendahara / Admin']);
    }

    $unit_gm1 = $pdo->query("SELECT id FROM kendaraan_alat WHERE kode_plat LIKE '%8468%' LIMIT 1")->fetch();
    if ($unit_gm1) {
        $stmt_rplat->execute([$unit_gm1['id'], 'H 9577 AZ', 'H 8468 AZ', '2023-10-01 09:00:00', 'Pergantian Nopol (Pajak STNK 5-Tahunan)', 'Bendahara / Admin']);
    }

    $unit_gm2 = $pdo->query("SELECT id FROM kendaraan_alat WHERE kode_plat LIKE '%8469%' LIMIT 1")->fetch();
    if ($unit_gm2) {
        $stmt_rplat->execute([$unit_gm2['id'], 'H 9583 AZ', 'H 8469 AZ', '2023-10-01 09:15:00', 'Pergantian Nopol (Pajak STNK 5-Tahunan)', 'Bendahara / Admin']);
    }
}

// 5b. Restore Nota Teknisi (Uploaded Receipts from Teknisi)
if (!empty($saved_nota_teknisi)) {
    $stmt_r_nota = $pdo->prepare("INSERT INTO nota_teknisi (id, id_kendaraan, tgl_nota, jenis_servis, nama_layanan, total_biaya, nama_bengkel, foto_nota, catatan, dikirim_oleh, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($saved_nota_teknisi as $nt) {
        $stmt_r_nota->execute([
            $nt['id'],
            $nt['id_kendaraan'],
            $nt['tgl_nota'],
            $nt['jenis_servis'] ?? 'Penjadwalan',
            $nt['nama_layanan'],
            $nt['total_biaya'],
            $nt['nama_bengkel'] ?? '',
            $nt['foto_nota'],
            $nt['catatan'] ?? '',
            $nt['dikirim_oleh'] ?? 'Teknisi',
            $nt['status'] ?? 'Menunggu Verifikasi',
            $nt['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }
}

// 5c. Restore Equipment Usage Log
if (!empty($saved_log_alat)) {
    $stmt_r_log = $pdo->prepare("INSERT INTO log_pemakaian_alat (id, id_kendaraan, jam_dipakai, total_jam_setelah_pakai, sisa_jam_servis_setelah_pakai, id_user, nama_user, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($saved_log_alat as $sla) {
        $stmt_r_log->execute([
            $sla['id'],
            $sla['id_kendaraan'],
            $sla['jam_dipakai'],
            $sla['total_jam_setelah_pakai'],
            $sla['sisa_jam_servis_setelah_pakai'],
            $sla['id_user'] ?? null,
            $sla['nama_user'] ?? 'Teknisi Operasional',
            $sla['catatan'] ?? '',
            $sla['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }
}

// 6. Reset & Seed Official BPJ Service History Baseline (3 Real Receipts + Any Additional User Entries)
$u1 = $pdo->query("SELECT id FROM kendaraan_alat WHERE kode_plat LIKE '%086/D.77%' OR kup_reg LIKE '%086/D.77%' LIMIT 1")->fetchColumn() ?: 39;
$u2 = $pdo->query("SELECT id FROM kendaraan_alat WHERE jenis = 'kendaraan_roda_3' LIMIT 1")->fetchColumn() ?: 19;
$u3 = $pdo->query("SELECT id FROM kendaraan_alat WHERE nama LIKE '%EXCAVATOR%' OR kode_plat LIKE '%PR/281%' LIMIT 1")->fetchColumn() ?: 43;

$default_3_servis = [
    [
        'id_kendaraan' => $u1,
        'tgl_servis' => '2026-09-02',
        'jenis_servis' => 'Penjadwalan',
        'nama_layanan' => 'Servis Rutin Berkala & Ganti Filter Solar',
        'rincian_item' => "1. Unit Jasa Servis Bomag 3 Ton PR/086/D.77 (Rp 850.000)\n2. Unit Jasa Servis Bomag 3 Ton PR/086/D.76 (Rp 850.000)\n3. 4 pc Filter Solar @ Rp 30.000 (Rp 120.000)",
        'total_biaya' => 1820000.00,
        'nama_bengkel' => 'SANTOSO TEKNIK',
        'foto_nota' => 'nota_1788492723_5354.png',
        'catatan' => 'Servis berkala alat berat Vibration Roller Bomag 3 Ton BPJ Wilayah Tegal',
        'created_at' => '2026-09-02 10:00:00'
    ],
    [
        'id_kendaraan' => $u2,
        'tgl_servis' => '2026-09-03',
        'jenis_servis' => 'Darurat',
        'nama_layanan' => 'Servis Kabel Body & Perbaikan Derek',
        'rincian_item' => "1. Servis Kabel Body + Derek (Rp 150.000)",
        'total_biaya' => 150000.00,
        'nama_bengkel' => 'ADO TEHNIK',
        'foto_nota' => 'nota_1788492951_4729.png',
        'catatan' => 'Perbaikan kelistrikan kabel body & layanan derek motor Viar di Salem',
        'created_at' => '2026-09-03 11:00:00'
    ],
    [
        'id_kendaraan' => $u3,
        'tgl_servis' => '2026-07-20',
        'jenis_servis' => 'Penjadwalan',
        'nama_layanan' => 'Servis Dinamo Excavator Pindad',
        'rincian_item' => "1. Pembayaran belanja servis dinamo excavator pindad PR/085/D.04 pemeliharaan rutin jalan kondisi baik di BPJ Wilayah Tegal Ex BPJ Wilayah Pekalongan (Rp 935.000)",
        'total_biaya' => 935000.00,
        'nama_bengkel' => 'CV. SIBER SIAP SENTOSA',
        'foto_nota' => 'nota_1788493206_7819.jpeg',
        'catatan' => 'Surat Bukti Pengeluaran Pemeliharaan Rutin Jalan di Wilayah Tegal (Kode Reg: 1.03.0.00.0.00.14.0016.10.1.01.0045.5.1.2.3.4.2)',
        'created_at' => '2026-07-20 14:00:00'
    ]
];

$final_servis_to_insert = $default_3_servis;

// If there were existing records before reset, keep only non-dummy real user entries
if (!empty($saved_servis)) {
    foreach ($saved_servis as $rs) {
        $bengkel = strtoupper($rs['nama_bengkel'] ?? '');
        $is_default = (strpos($bengkel, 'SANTOSO') !== false || strpos($bengkel, 'ADO') !== false || strpos($bengkel, 'SIBER') !== false);
        $has_photo = !empty($rs['foto_nota']);
        
        // If it's a real user-added record (has photo and not one of the default 3), append it
        if ($has_photo && !$is_default) {
            $final_servis_to_insert[] = [
                'id_kendaraan' => $rs['id_kendaraan'],
                'tgl_servis' => $rs['tgl_servis'],
                'jenis_servis' => $rs['jenis_servis'],
                'nama_layanan' => $rs['nama_layanan'],
                'rincian_item' => $rs['rincian_item'],
                'total_biaya' => $rs['total_biaya'],
                'nama_bengkel' => $rs['nama_bengkel'],
                'foto_nota' => $rs['foto_nota'],
                'catatan' => $rs['catatan'],
                'created_at' => $rs['created_at'] ?? date('Y-m-d H:i:s')
            ];
        }
    }
}

$stmt_r_serv = $pdo->prepare("INSERT INTO riwayat_servis (id_kendaraan, tgl_servis, jenis_servis, nama_layanan, rincian_item, total_biaya, nama_bengkel, foto_nota, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($final_servis_to_insert as $rs) {
    $stmt_r_serv->execute([
        $rs['id_kendaraan'],
        $rs['tgl_servis'],
        $rs['jenis_servis'],
        $rs['nama_layanan'],
        $rs['rincian_item'],
        $rs['total_biaya'],
        $rs['nama_bengkel'],
        $rs['foto_nota'],
        $rs['catatan'],
        $rs['created_at'] ?? date('Y-m-d H:i:s')
    ]);

    // Sync unit service date
    $u_id = $rs['id_kendaraan'];
    $tgl_servis = $rs['tgl_servis'];
    $stmt_u = $pdo->prepare("SELECT interval_servis_bulan FROM kendaraan_alat WHERE id = ?");
    $stmt_u->execute([$u_id]);
    $interval = (int)($stmt_u->fetchColumn() ?: 3);
    $tgl_berikutnya = date('Y-m-d', strtotime("+$interval months", strtotime($tgl_servis)));
    $pdo->prepare("UPDATE kendaraan_alat SET tgl_servis_terakhir = ?, tgl_servis_berikutnya = ? WHERE id = ?")
        ->execute([$tgl_servis, $tgl_berikutnya, $u_id]);
}

// 7. Restore Pajak Records (Only real entries with uploaded proof photos)
if (!empty($saved_pajak)) {
    $stmt_r_pajak = $pdo->prepare("INSERT INTO pembayaran_pajak_kir (id_kendaraan, jenis_pembayaran, tgl_bayar, tgl_jatuh_tempo_baru, nominal_biaya, foto_bukti, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($saved_pajak as $rp) {
        if (!empty($rp['foto_bukti'])) {
            $stmt_r_pajak->execute([
                $rp['id_kendaraan'],
                $rp['jenis_pembayaran'],
                $rp['tgl_bayar'],
                $rp['tgl_jatuh_tempo_baru'],
                $rp['nominal_biaya'],
                $rp['foto_bukti'],
                $rp['catatan'],
                $rp['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Berhasil Di Reset - ALISA</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="background-color: #0f172a; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px;">
    <div style="max-width: 520px; width: 100%; text-align: center; background-color: #1e293b; color: #ffffff; padding: 45px 35px; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.08);">
        <div style="font-size: 58px; margin-bottom: 20px;">✅</div>
        <h2 style="font-size: 22px; font-weight: 800; color: #10b981; margin-bottom: 12px; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1.3;">
            DATABASE BERHASIL DI RESET
        </h2>
        <p style="color: #94a3b8; font-size: 15px; margin-bottom: 32px; font-weight: 500;">
            Silahkan kembali ke halaman beranda ALISA.
        </p>
        <a href="index.php" style="display: inline-block; padding: 14px 32px; background-color: #0284c7; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 15px; border-radius: 50px; box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4); transition: all 0.2s;">
            Kembali ke Beranda ALISA &rarr;
        </a>
    </div>
</body>
</html>
