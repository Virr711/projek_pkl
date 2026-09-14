<?php
/**
 * SIMAN-BPJ WhatsApp Notification Cron Dispatcher
 * URL: http://localhost/bengkel_bpj/cron_whatsapp.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();
$result = broadcast_notif_h30_whatsapp($pdo, false);
echo json_encode($result, JSON_PRETTY_PRINT);
