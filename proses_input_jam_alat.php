<?php
/**
 * ALISA - Handler Input Jam Kerja Operasional Peralatan & Alat Berat
 * Balai Pengelolaan Jalan Wilayah Tegal
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!can_edit_data()) {
    $_SESSION['flash_error'] = "Akses ditolak! Hanya Admin, Teknisi, dan Bendahara yang dapat menginput pemakaian alat.";
    header("Location: data_unit_peralatan.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_db();
    $id_kendaraan = (int)($_POST['id_kendaraan'] ?? 0);
    $jam_dipakai = (int)($_POST['jam_dipakai'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $redirect_url = $_POST['redirect_url'] ?? 'data_unit_peralatan.php';

    if ($id_kendaraan <= 0 || $jam_dipakai <= 0) {
        $_SESSION['flash_error'] = "Mohon pilih peralatan dan masukkan durasi pemakaian jam yang valid (minimal 1 jam).";
        header("Location: " . $redirect_url);
        exit;
    }

    $res = catat_pemakaian_alat($pdo, $id_kendaraan, $jam_dipakai, $catatan);

    if ($res) {
        $alert_info = "";
        if ($res['sisa_jam'] <= 100) {
            $alert_info = " ⚠️ Perhatian: Sisa jam servis tersisa {$res['sisa_jam']} Jam (Mendekati Servis 1.000 Jam)!";
        }
        $_SESSION['flash_success'] = "Berhasil mencatat pemakaian {$jam_dipakai} jam untuk unit {$res['nama_unit']} ({$res['kode_plat']}). Total Jam Operasional: {$res['total_jam']} Jam. Sisa Jam Servis: {$res['sisa_jam']} Jam.{$alert_info}";
    } else {
        $_SESSION['flash_error'] = "Gagal memperbarui data jam operasional unit peralatan.";
    }

    header("Location: " . $redirect_url);
    exit;
}

header("Location: data_unit_peralatan.php");
exit;
