<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db();
$mode = $_GET['mode'] ?? 'test';

if ($mode === 'schedule_timer') {
    $minutes = (int)($_GET['minutes'] ?? 1);
    if ($minutes < 1) $minutes = 1;
    $seconds = $minutes * 60;

    // Launch background process in Windows with delay timer
    $cmd = "cmd /c start /B powershell -Command \"Start-Sleep -Seconds {$seconds}; & 'C:\\xampp\\php\\php.exe' 'C:\\xampp\\htdocs\\bengkel_bpj\\cron_whatsapp.php'\"";
    pclose(popen($cmd, "r"));

    echo json_encode([
        'success' => true,
        'message' => "Timer background BERHASIL diaktifkan! Notifikasi WhatsApp akan terkirim OTOMATIS dalam {$minutes} menit ({$seconds} detik) ke HP target tanpa perlu membuka website lagi."
    ]);
    exit;
} elseif ($mode === 'broadcast') {
    $res = broadcast_notif_h30_whatsapp($pdo, true);
    echo json_encode($res);
    exit;
} else {
    $setting = get_whatsapp_setting($pdo);
    $test_msg = "*🧪 UJI COBA NOTIFIKASI WHATSAPP - SIMAN-BPJ TEGAL*\n";
    $test_msg .= "_Balai Pengelolaan Jalan Wilayah Tegal (2026)_\n";
    $test_msg .= "--------------------------------------\n";
    $test_msg .= "Halo Tim Bengkel BPJ Wilayah Tegal!\n";
    $test_msg .= "Notifikasi WhatsApp H-7 (seminggu sebelum tanggal servis) telah aktif.\n";
    $test_msg .= "--------------------------------------\n";
    $test_msg .= "_Waktu Uji Coba: " . date('d-m-Y H:i:s') . " WIB_";

    $penerima_list = get_penerima_whatsapp_list($pdo, true);
    $target_numbers = array_column($penerima_list, 'nomor_whatsapp');
    if (empty($target_numbers)) {
        $target_numbers = [$setting['target_phone']];
    }

    $res = send_whatsapp_msg($setting['api_token'], $target_numbers, $test_msg);
    echo json_encode($res);
    exit;
}
