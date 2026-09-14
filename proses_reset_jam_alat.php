<?php
/**
 * ALISA - Handler Reset Jam Operasional Peralatan & Alat Berat (Setelah Servis)
 * Balai Pengelolaan Jalan Wilayah Tegal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!can_edit_data()) {
    $_SESSION['flash_error'] = "Akses ditolak! Hanya Admin dan Teknisi yang dapat mereset jam operasional alat.";
    header("Location: data_unit_peralatan.php");
    exit;
}

$pdo = get_db();
$id_kendaraan = (int)($_REQUEST['id_kendaraan'] ?? $_REQUEST['id'] ?? 0);
$catatan = trim($_REQUEST['catatan'] ?? 'Reset Jam Operasional (Servis Selesai)');
$redirect_url = $_REQUEST['redirect_url'] ?? 'data_unit_peralatan.php';

if ($id_kendaraan <= 0) {
    $_SESSION['flash_error'] = "Mohon pilih peralatan yang valid untuk di-reset.";
    header("Location: " . $redirect_url);
    exit;
}

$res = reset_jam_operasional_alat($pdo, $id_kendaraan, $catatan);

if ($res) {
    $_SESSION['flash_success'] = "✅ Berhasil mereset jam operasional unit {$res['nama_unit']} ({$res['kode_plat']}) ke 0 Jam. Sisa jam servis telah kembali ke {$res['sisa_jam']} Jam.";
} else {
    $_SESSION['flash_error'] = "Gagal mereset jam operasional unit peralatan.";
}

header("Location: " . $redirect_url);
exit;
