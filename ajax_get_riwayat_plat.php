<?php
/**
 * ALISA - AJAX Handler Get License Plate (Nopol) History
 * Balai Pengelolaan Jalan Wilayah Tegal (POLITEKNIK PURBAYA)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID unit tidak valid']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM riwayat_perubahan_plat WHERE id_kendaraan = ? ORDER BY tgl_perubahan DESC, id DESC");
$stmt->execute([$id]);
$rows = $stmt->fetchAll();

$formatted = [];
foreach ($rows as $r) {
    $formatted[] = [
        'id' => $r['id'],
        'plat_lama' => htmlspecialchars($r['plat_lama']),
        'plat_baru' => htmlspecialchars($r['plat_baru']),
        'tgl_perubahan' => $r['tgl_perubahan'],
        'tgl_perubahan_formatted' => date('d M Y H:i', strtotime($r['tgl_perubahan'])),
        'keterangan' => htmlspecialchars($r['keterangan'] ?? ''),
        'diubah_oleh' => htmlspecialchars($r['diubah_oleh'] ?? 'Admin')
    ];
}

echo json_encode(['status' => 'success', 'data' => $formatted]);
