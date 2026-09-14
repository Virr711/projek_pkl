<?php
/**
 * ALISA - Helper Functions & Schedule Calculators
 * Balai Pengelolaan Jalan Wilayah Tegal (POLITEKNIK PURBAYA)
 */

function current_user_role() {
    return strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? 'admin');
}

function check_auto_whatsapp_daily($pdo) {
    // Automatic background checker stub
    return true;
}

function can_edit_data() {
    $role = current_user_role();
    return ($role === 'admin');
}

function can_upload_nota() {
    $role = current_user_role();
    return ($role === 'admin' || $role === 'teknisi');
}

function get_kondisi_badge($kondisi) {
    switch (strtoupper($kondisi)) {
        case 'B':
            return '<span class="badge bg-success text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i> Baik (B)</span>';
        case 'RR':
            return '<span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-bold" style="background-color: #facc15 !important;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Rusak Ringan (RR)</span>';
        case 'RB':
            return '<span class="badge bg-danger text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-ban me-1"></i> Rusak Berat (RB)</span>';
        default:
            return '<span class="badge bg-secondary text-white px-2.5 py-1 rounded-pill">' . htmlspecialchars($kondisi) . '</span>';
    }
}

function get_lokasi_ruas_list() {
    return [
        '1. Jatinegara - Slawi',
        '2. Slawi - Jatibarang',
        '3. Jatibarang - Ketanggungan',
        '4. Ketanggungan - Kersana - Bantarsari',
        '5. Kersana - Bandungsari',
        '6. Bandungsari - Salem',
        '7. Bandungsari - Penanggapan',
        '8. Bumiayu - Salem',
        '9. Sirampog - Bumiayu',
        '10. Morongso - Tuwel - Sirampog',
        '11. Salem - Perbatasan Kab. Cilacap',
        '12. Workshop / POOL BPJ WILAYAH TEGAL',
        '13. Tempat Lain (Input Custom)'
    ];
}

function get_role_badge($role) {
    switch ($role) {
        case 'admin':
            return '<span class="badge bg-danger text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-user-shield me-1"></i> Admin</span>';
        case 'teknisi':
            return '<span class="badge bg-info text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-wrench me-1"></i> Teknisi</span>';
        case 'pimpinan':
            return '<span class="badge bg-primary text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-user-tie me-1"></i> Pimpinan</span>';
        case 'bendahara':
            return '<span class="badge bg-success text-white px-2.5 py-1 rounded-pill"><i class="fa-solid fa-wallet me-1"></i> Bendahara</span>';
        default:
            return '<span class="badge bg-secondary text-white px-2.5 py-1 rounded-pill">Guest</span>';
    }
}

function format_tgl_indo($date_str) {
    if (empty($date_str) || $date_str === '0000-00-00') return '-';
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $time = strtotime($date_str);
    if (!$time) return '-';
    $d = date('j', $time);
    $m = $months[(int)date('n', $time)];
    $y = date('Y', $time);
    return "{$d} {$m} {$y}";
}

function format_rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// User Role Privacy Helper: Hides cost details for Pimpinan
function format_rupiah_privacy($angka) {
    $current_role = $_SESSION['user_role'] ?? 'admin';
    if ($current_role === 'pimpinan') {
        return '<span class="text-muted fst-italic"><i class="fa-solid fa-eye-slash me-1"></i> [Rahasia Operasional]</span>';
    }
    return format_rupiah($angka);
}

// Calculate Next Service Date (Month-based Interval)
function hitung_tgl_servis_berikutnya($tgl_terakhir, $interval_bulan) {
    if (empty($tgl_terakhir) || $tgl_terakhir === '0000-00-00') return date('Y-m-d');
    $date = new DateTime($tgl_terakhir);
    $interval_bulan = max(1, (int)$interval_bulan);
    $date->modify("+{$interval_bulan} month");
    return $date->format('Y-m-d');
}

// ALISA Schedule Calculator (H-30 Weekly vs H-7 Daily Logic & RB Exclusions)
function get_alisa_schedule_info($tgl_target, $label_type = 'Servis', $kondisi = 'B', $jenis = 'kendaraan') {
    // Rule 1: Rusak Berat (RB) units do not receive notifications
    if ($kondisi === 'RB') {
        return [
            'status' => 'Rusak Berat (Non-Aktif)',
            'badge_class' => 'bg-danger text-white',
            'icon' => 'fa-ban',
            'label' => 'Rusak Berat (Tanpa Notif)',
            'days_left' => 999,
            'is_h30' => false,
            'is_h7' => false,
            'is_overdue' => false,
            'notify_today' => false
        ];
    }

    // Rule 2: Peralatan (Alat Berat) uses Hour-Based logic (1,000 Hours target, <= 100 Hours alert)
    if ($jenis === 'peralatan') {
        return get_peralatan_jam_info([
            'kondisi' => $kondisi,
            'jam_operasional' => $tgl_target['jam_operasional'] ?? 0,
            'interval_jam_servis' => $tgl_target['interval_jam_servis'] ?? 1000,
            'sisa_jam_servis' => $tgl_target['sisa_jam_servis'] ?? 1000
        ]);
    }

    if (empty($tgl_target) || $tgl_target == '0000-00-00') {
        return [
            'status' => 'Belum Diatur',
            'badge_class' => 'bg-secondary text-white',
            'icon' => 'fa-circle-question',
            'label' => 'Tidak Ada Jadwal',
            'days_left' => 999,
            'is_h30' => false,
            'is_h7' => false,
            'is_overdue' => false,
            'notify_today' => false
        ];
    }

    $today = new DateTime(date('Y-m-d'));
    $target = new DateTime($tgl_target);
    $diff = $today->diff($target);
    $days_left = (int)$diff->format("%r%a");

    if ($days_left < 0) {
        return [
            'status' => 'Jadwal Terlewat',
            'badge_class' => 'bg-danger text-white',
            'icon' => 'fa-exclamation-triangle',
            'label' => "Terlewat " . abs($days_left) . " Hari!",
            'days_left' => $days_left,
            'is_h30' => true,
            'is_h7' => true,
            'is_overdue' => true,
            'notify_today' => true
        ];
    } elseif ($days_left <= 7) {
        return [
            'status' => 'Mendekati (H-7)',
            'badge_class' => 'bg-danger text-white',
            'icon' => 'fa-bell',
            'label' => ($days_left === 0) ? "Jatuh Tempo Hari Ini!" : "H-{$days_left}",
            'days_left' => $days_left,
            'is_h30' => true,
            'is_h7' => true,
            'is_overdue' => false,
            'notify_today' => true
        ];
    } elseif ($days_left <= 30) {
        $day_of_week = date('N');
        $is_weekly_trigger = ($days_left === 30 || $days_left === 23 || $days_left === 16 || $days_left === 9 || $day_of_week == 1);

        return [
            'status' => 'Persiapan (H-30)',
            'badge_class' => 'bg-warning text-white',
            'icon' => 'fa-calendar-days',
            'label' => "H-{$days_left}",
            'days_left' => $days_left,
            'is_h30' => true,
            'is_h7' => false,
            'is_overdue' => false,
            'notify_today' => $is_weekly_trigger
        ];
    } else {
        return [
            'status' => 'Baik',
            'badge_class' => 'bg-success text-white',
            'icon' => 'fa-check-circle',
            'label' => "{$days_left} Hari Lagi",
            'days_left' => $days_left,
            'is_h30' => false,
            'is_h7' => false,
            'is_overdue' => false,
            'notify_today' => false
        ];
    }
}

// Get WhatsApp Settings
function get_whatsapp_setting($pdo) {
    $stmt = $pdo->query("SELECT * FROM pengaturan_whatsapp LIMIT 1");
    $setting = $stmt->fetch();
    if (!$setting) {
        $pdo->exec("INSERT INTO pengaturan_whatsapp (api_token, target_phone, notif_h7_aktif) VALUES ('', '082225352170', 1)");
        $stmt = $pdo->query("SELECT * FROM pengaturan_whatsapp LIMIT 1");
        $setting = $stmt->fetch();
    }
    return $setting;
}

// WhatsApp Notification Engine via Local Gateway Server (Port 3000) with Cloud Fonnte Fallback (Supports Multi-Penerima)
function get_penerima_whatsapp_list($pdo, $only_active = true) {
    try {
        $sql = "SELECT * FROM penerima_whatsapp";
        if ($only_active) {
            $sql .= " WHERE is_aktif = 1";
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $pdo->query($sql);
        $list = $stmt ? $stmt->fetchAll() : [];
        if (empty($list)) {
            $st = get_whatsapp_setting($pdo);
            if (!empty($st['target_phone'])) {
                $list = [[
                    'id' => 0,
                    'nama_penerima' => 'Pimpinan / Operasional',
                    'nomor_whatsapp' => $st['target_phone'],
                    'jabatan' => 'Pimpinan',
                    'is_aktif' => 1
                ]];
            }
        }
        return $list;
    } catch (Exception $e) {
        $st = get_whatsapp_setting($pdo);
        return [[
            'id' => 0,
            'nama_penerima' => 'Pimpinan / Operasional',
            'nomor_whatsapp' => $st['target_phone'] ?? '082225352170',
            'jabatan' => 'Pimpinan',
            'is_aktif' => 1
        ]];
    }
}

function send_whatsapp_msg($token, $target, $message) {
    $targets = [];
    if (is_array($target)) {
        $targets = $target;
    } else {
        $raw_targets = preg_split('/[\s,\n]+/', (string)$target);
        foreach ($raw_targets as $t) {
            $clean = preg_replace('/[^0-9]/', '', $t);
            if (!empty($clean)) {
                $targets[] = $clean;
            }
        }
    }

    $targets = array_unique(array_filter($targets));

    if (empty($targets)) {
        return ['success' => false, 'message' => 'Tidak ada nomor WhatsApp target yang valid.'];
    }

    $success_count = 0;
    $local_gateway_url = "http://localhost:3000/send-message";

    foreach ($targets as $single_target) {
        $target_clean = preg_replace('/[^0-9]/', '', $single_target);
        if (empty($target_clean)) continue;

        $sent_single = false;

        // Primary Method: Local UNLIMITED Gateway Server (Port 3000)
        $payload = json_encode([
            'phone' => $target_clean,
            'number' => $target_clean,
            'message' => $message
        ]);

        $ch = curl_init($local_gateway_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (($http_code === 200 || $http_code === 201) && $response) {
            $res_data = json_decode($response, true);
            if ((isset($res_data['success']) && $res_data['success'] === true) || (isset($res_data['status']) && $res_data['status'] === true)) {
                $success_count++;
                $sent_single = true;
            }
        }

        // Fallback Method: Cloud Fonnte API (If Local Gateway is offline)
        if (!$sent_single && !empty($token)) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array(
                    'target' => $target_clean,
                    'message' => $message,
                ),
                CURLOPT_HTTPHEADER => array("Authorization: $token"),
            ));
            $fonnte_res = curl_exec($curl);
            curl_close($curl);

            if ($fonnte_res) {
                $f_data = json_decode($fonnte_res, true);
                if (isset($f_data['status']) && $f_data['status'] === true) {
                    $success_count++;
                    $sent_single = true;
                }
            }
        }
    }

    if ($success_count > 0) {
        return [
            'success' => true,
            'message' => "Pesan WhatsApp berhasil terkirim ke {$success_count} target penerima!",
            'success_count' => $success_count
        ];
    }

    return ['success' => false, 'message' => 'Gagal mengirim pesan WhatsApp. Pastikan Local Server Gateway (start_wa_gateway.bat) aktif di Port 3000 atau API Token Fonnte terisi.'];
}

// Broadcast Automated H-30 and H-7 WhatsApp Notifications
function broadcast_notif_h30_whatsapp($pdo, $force_send = false) {
    $setting = get_whatsapp_setting($pdo);
    if (!$force_send && !$setting['notif_h7_aktif']) {
        return ['success' => false, 'message' => 'Pengiriman notifikasi otomatis sedang dinonaktifkan di pengaturan.', 'count' => 0];
    }

    // Fetch all non-RB units
    $stmt = $pdo->query("SELECT * FROM kendaraan_alat WHERE kondisi != 'RB' ORDER BY tgl_servis_berikutnya ASC");
    $units = $stmt->fetchAll();

    $due_today_items = [];

    foreach ($units as $u) {
        $inf_s = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
        $inf_p = get_alisa_schedule_info($u['tgl_jatuh_tempo_pajak'], 'Pajak STNK', $u['kondisi'], $u['jenis']);
        $inf_k = get_alisa_schedule_info($u['tgl_jatuh_tempo_kir'], 'Uji KIR', $u['kondisi'], $u['jenis']);

        if ($inf_s['notify_today']) {
            $due_today_items[] = "• *{$u['nama']} ({$u['kode_plat']})* - Servis: *{$inf_s['label']}* (PJ: {$u['penanggung_jawab']})";
        }
        if ($inf_p['notify_today']) {
            $due_today_items[] = "• *{$u['nama']} ({$u['kode_plat']})* - Pajak STNK: *{$inf_p['label']}*";
        }
        if ($inf_k['notify_today']) {
            $due_today_items[] = "• *{$u['nama']} ({$u['kode_plat']})* - Uji KIR: *{$inf_k['label']}*";
        }
    }

    $count = count($due_today_items);

    // Fetch All Active Target Recipients
    $penerima_list = get_penerima_whatsapp_list($pdo, true);
    $target_numbers = array_column($penerima_list, 'nomor_whatsapp');

    $first_target = !empty($target_numbers) ? $target_numbers[0] : $setting['target_phone'];
    $first_clean  = preg_replace('/[^0-9]/', '', $first_target);

    if ($count === 0) {
        return [
            'success' => false,
            'message' => 'Tidak ada armada yang mendekati H-30 (mingguan) atau H-7 (harian) pada hari ini.',
            'count' => 0,
            'direct_link' => 'https://wa.me/' . $first_clean
        ];
    }

    // Build Formatted WhatsApp Message Text
    $today_str = format_tgl_indo(date('Y-m-d'));
    $msg = "*🚨 ALISA - NOTIFIKASI PENGINGAT LOGISTIK ARMADA BPJ TEGAL*\n";
    $msg .= "Tanggal: *{$today_str}*\n";
    $msg .= "Jumlah Armada Perlu Tindakan: *{$count} Item*\n";
    $msg .= "--------------------------------------\n";
    $msg .= implode("\n", $due_today_items) . "\n";
    $msg .= "--------------------------------------\n";
    $msg .= "Mohon segera lakukan penanganan servis / pembayaran pajak STNK.\n";
    $msg .= "_Pesan otomatis oleh Sistem ALISA (POLITEKNIK PURBAYA & BPJ Tegal)_";

    $wa_direct_link = "https://wa.me/{$first_clean}?text=" . urlencode($msg);

    $res = send_whatsapp_msg($setting['api_token'], $target_numbers, $msg);
    $res['count'] = $count;
    $res['direct_link'] = $wa_direct_link;

    return $res;
}

// Schedule & Warning Calculator for Peralatan & Alat Berat (Hour-based: 1000 Jam target, <= 100 Jam alert)
function get_peralatan_jam_info($unit) {
    $kondisi = strtoupper($unit['kondisi'] ?? 'B');
    if ($kondisi === 'RB') {
        return [
            'status' => 'Rusak Berat (Non-Aktif)',
            'badge_class' => 'bg-dark text-white border border-danger',
            'icon' => 'fa-ban',
            'label' => 'Rusak Berat (Tanpa Notif)',
            'days_left' => 999,
            'sisa_jam' => 0,
            'is_h30' => false,
            'is_h7' => false,
            'is_overdue' => false,
            'is_alert' => false,
            'notify_today' => false
        ];
    }

    $jam_op = (int)($unit['jam_operasional'] ?? 0);
    $interval = (int)($unit['interval_jam_servis'] ?? 1000);
    if ($interval <= 0) $interval = 1000;

    $sisa_jam = (int)($unit['sisa_jam_servis'] ?? ($interval - ($jam_op % $interval)));
    $day_of_week = date('N'); // 1 = Senin (Mingguan)

    if ($sisa_jam <= 0) {
        return [
            'status' => 'Wajib Servis (Jam Habis)',
            'badge_class' => 'bg-danger text-white',
            'icon' => 'fa-circle-exclamation',
            'label' => 'Wajib Servis (0 Jam Sisa)',
            'days_left' => 0,
            'sisa_jam' => 0,
            'is_h30' => true,
            'is_h7' => true,
            'is_overdue' => true,
            'is_alert' => true,
            'notify_today' => true
        ];
    } elseif ($sisa_jam <= 100) {
        return [
            'status' => 'Mendekati Servis (<= 100 Jam)',
            'badge_class' => 'bg-warning text-white',
            'icon' => 'fa-triangle-exclamation',
            'label' => "Sisa {$sisa_jam} Jam",
            'days_left' => (int)ceil($sisa_jam / 10),
            'sisa_jam' => $sisa_jam,
            'is_h30' => true,
            'is_h7' => false,
            'is_overdue' => false,
            'is_alert' => true,
            'notify_today' => ($day_of_week == 1) // Notif mingguan saja (tiap hari Senin)
        ];
    } else {
        return [
            'status' => 'Baik (Jam Operasional OK)',
            'badge_class' => 'bg-success text-white',
            'icon' => 'fa-clock',
            'label' => "Sisa {$sisa_jam} Jam",
            'days_left' => 999,
            'sisa_jam' => $sisa_jam,
            'is_h30' => false,
            'is_h7' => false,
            'is_overdue' => false,
            'is_alert' => false,
            'notify_today' => false
        ];
    }
}

function catat_pemakaian_alat($pdo, $id_kendaraan, $jam_dipakai, $catatan = '') {
    $jam_dipakai = max(1, (int)$jam_dipakai);
    $stmt = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
    $stmt->execute([$id_kendaraan]);
    $unit = $stmt->fetch();
    if (!$unit) return false;

    $interval = (int)($unit['interval_jam_servis'] ?? 1000);
    if ($interval <= 0) $interval = 1000;

    $new_total_jam = (int)($unit['jam_operasional'] ?? 0) + $jam_dipakai;
    $current_sisa = (int)($unit['sisa_jam_servis'] ?? $interval);
    $new_sisa_jam = max(0, $current_sisa - $jam_dipakai);

    // Update kendaraan_alat
    $stmt_upd = $pdo->prepare("UPDATE kendaraan_alat SET jam_operasional = ?, sisa_jam_servis = ? WHERE id = ?");
    $stmt_upd->execute([$new_total_jam, $new_sisa_jam, $id_kendaraan]);

    // Insert log_pemakaian_alat
    $id_user = $_SESSION['user_id'] ?? null;
    $nama_user = $_SESSION['nama_lengkap'] ?? 'Teknisi Operasional';
    $stmt_log = $pdo->prepare("INSERT INTO log_pemakaian_alat (id_kendaraan, jam_dipakai, total_jam_setelah_pakai, sisa_jam_servis_setelah_pakai, id_user, nama_user, catatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_log->execute([$id_kendaraan, $jam_dipakai, $new_total_jam, $new_sisa_jam, $id_user, $nama_user, $catatan]);

    return [
        'total_jam' => $new_total_jam,
        'sisa_jam' => $new_sisa_jam,
        'nama_unit' => $unit['nama'],
        'kode_plat' => $unit['kode_plat']
    ];
}

/**
 * Record License Plate (Nopol) Change History
 */
function catat_perubahan_plat($pdo, $id_kendaraan, $plat_lama, $plat_baru, $keterangan = 'Pergantian Nopol (Pajak STNK 5-Tahunan)', $diubah_oleh = null) {
    $plat_lama = strtoupper(trim($plat_lama));
    $plat_baru = strtoupper(trim($plat_baru));
    
    if (empty($plat_lama) || empty($plat_baru) || $plat_lama === $plat_baru) {
        return false;
    }
    
    if (!$diubah_oleh) {
        $diubah_oleh = $_SESSION['nama_lengkap'] ?? ($_SESSION['user_role'] ?? 'Admin');
    }
    
    $stmt = $pdo->prepare("INSERT INTO riwayat_perubahan_plat (id_kendaraan, plat_lama, plat_baru, tgl_perubahan, keterangan, diubah_oleh) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?)");
    return $stmt->execute([(int)$id_kendaraan, $plat_lama, $plat_baru, $keterangan, $diubah_oleh]);
}

/**
 * Fetch License Plate (Nopol) Change History for a Unit
 */
function get_riwayat_plat($pdo, $id_kendaraan) {
    $stmt = $pdo->prepare("SELECT * FROM riwayat_perubahan_plat WHERE id_kendaraan = ? ORDER BY tgl_perubahan DESC, id DESC");
    $stmt->execute([(int)$id_kendaraan]);
    return $stmt->fetchAll();
}

/**
 * Convert Number into Indonesian Terbilang Words
 */
function terbilang($angka) {
    $angka = abs((float)$angka);
    $baca = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $terbilang = "";

    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } else if ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " Belas";
    } else if ($angka < 100) {
        $terbilang = terbilang($angka / 10) . " Puluh" . terbilang($angka % 10);
    } else if ($angka < 200) {
        $terbilang = " Seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $terbilang = terbilang($angka / 100) . " Ratus" . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $terbilang = " Seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $terbilang = terbilang($angka / 1000) . " Ribu" . terbilang($angka % 1000);
    } else if ($angka < 1000000000) {
        $terbilang = terbilang($angka / 1000000) . " Juta" . terbilang($angka % 1000000);
    } else if ($angka < 1000000000000) {
        $terbilang = terbilang($angka / 1000000000) . " Milyar" . terbilang(fmod($angka, 1000000000));
    }

    return trim($terbilang);
}

/**
 * Standard 14 Checklist Service Items & Interval Definition
 */
function get_standard_checklist_items() {
    return [
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
}
?>
