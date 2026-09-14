<?php
require_once __DIR__ . '/includes/header.php';

// Strict Use Case Role Guard: Admin, Bendahara & Teknisi
if ($current_role !== 'admin' && $current_role !== 'bendahara' && $current_role !== 'teknisi' && $current_role !== 'pimpinan') {
    echo "<script>alert('Akses Ditolak: Fitur Pembayaran Pajak & KIR khusus untuk Admin, Bendahara, Teknisi & Pimpinan.'); window.location='index.php';</script>";
    exit;
}

// Handle Delete Payment History Entry (Admin ONLY)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!can_edit_data()) {
        echo "<script>alert('Akses Ditolak: Hanya Admin yang dapat menghapus catatan pembayaran.'); window.location='pembayaran_pajak_kir.php';</script>";
        exit;
    }
    $del_id = (int)$_GET['id'];
    
    $stmt_find = $pdo->prepare("SELECT * FROM pembayaran_pajak_kir WHERE id = ?");
    $stmt_find->execute([$del_id]);
    $pay_entry = $stmt_find->fetch();

    if ($pay_entry) {
        if (!empty($pay_entry['foto_bukti'])) {
            $file_path = __DIR__ . '/uploads/pajak/' . $pay_entry['foto_bukti'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        $stmt_del = $pdo->prepare("DELETE FROM pembayaran_pajak_kir WHERE id = ?");
        $stmt_del->execute([$del_id]);

        echo "<script>alert('Catatan riwayat pembayaran Pajak / KIR berhasil dihapus!'); window.location='pembayaran_pajak_kir.php';</script>";
        exit;
    }
}

$id_kendaraan_filter = (int)($_GET['id_kendaraan'] ?? 0);
$cat_pajak_filter = $_GET['cat'] ?? 'semua';

// Fetch All Units for Dropdown
$stmt_all_units = $pdo->query("SELECT * FROM kendaraan_alat WHERE jenis != 'peralatan' ORDER BY nama ASC");
$all_units_list = $stmt_all_units->fetchAll();

// Fetch Units for Data 3 Matrix Table
$sql_pajak_matrix = "SELECT * FROM kendaraan_alat WHERE jenis != 'peralatan'";
if ($cat_pajak_filter === 'roda6') {
    $sql_pajak_matrix .= " AND jenis = 'kendaraan_roda_6'";
} elseif ($cat_pajak_filter === 'roda4') {
    $sql_pajak_matrix .= " AND jenis = 'kendaraan_roda_4'";
} elseif ($cat_pajak_filter === 'roda3') {
    $sql_pajak_matrix .= " AND jenis = 'kendaraan_roda_3'";
} elseif ($cat_pajak_filter === 'roda2') {
    $sql_pajak_matrix .= " AND jenis = 'kendaraan_roda_2'";
}
$sql_pajak_matrix .= " ORDER BY FIELD(jenis, 'kendaraan_roda_6', 'kendaraan_roda_4', 'kendaraan_roda_3', 'kendaraan_roda_2'), kode_plat ASC";
$matrix_pajak_units = $pdo->query($sql_pajak_matrix)->fetchAll();

// Build map of latest tax payment nominal per vehicle for +10% prediction
$latest_pajak_map = [];
$stmt_lp = $pdo->query("
    SELECT id_kendaraan, nominal_biaya 
    FROM pembayaran_pajak_kir 
    ORDER BY tgl_bayar ASC
");
while ($row_lp = $stmt_lp->fetch()) {
    $latest_pajak_map[(int)$row_lp['id_kendaraan']] = (float)$row_lp['nominal_biaya'];
}

// Build map of units that have paid tax in CURRENT YEAR
$current_year = (int)date('Y');
$paid_current_year_map = [];
$stmt_pcurr = $pdo->prepare("
    SELECT DISTINCT id_kendaraan 
    FROM pembayaran_pajak_kir 
    WHERE (jenis_pembayaran = 'Pajak STNK' OR jenis_pembayaran LIKE '%Pajak%')
      AND (YEAR(tgl_bayar) = ? OR YEAR(tgl_jatuh_tempo_baru) > ?)
");
$stmt_pcurr->execute([$current_year, $current_year]);
while ($row_pcurr = $stmt_pcurr->fetch()) {
    $paid_current_year_map[(int)$row_pcurr['id_kendaraan']] = true;
}

// Fetch Payment History
$sql_pay = "SELECT p.*, k.nama, k.kode_plat FROM pembayaran_pajak_kir p JOIN kendaraan_alat k ON p.id_kendaraan = k.id WHERE 1=1";
$params_pay = [];

if ($id_kendaraan_filter > 0) {
    $sql_pay .= " AND p.id_kendaraan = ?";
    $params_pay[] = $id_kendaraan_filter;
}
$sql_pay .= " ORDER BY p.tgl_bayar DESC";

$stmt_hist = $pdo->prepare($sql_pay);
$stmt_hist->execute($params_pay);
$payments = $stmt_hist->fetchAll();

// Map payments per vehicle unit for detail popup modal
$payments_by_unit = [];
$stmt_all_pay = $pdo->query("SELECT p.*, k.nama, k.kode_plat FROM pembayaran_pajak_kir p JOIN kendaraan_alat k ON p.id_kendaraan = k.id ORDER BY p.tgl_bayar DESC");
while ($p = $stmt_all_pay->fetch()) {
    $uid = (int)$p['id_kendaraan'];
    if (!isset($payments_by_unit[$uid])) {
        $payments_by_unit[$uid] = [];
    }
    $payments_by_unit[$uid][] = [
        'tgl_bayar' => format_tgl_indo($p['tgl_bayar']),
        'jenis_pembayaran' => $p['jenis_pembayaran'],
        'tgl_jatuh_tempo_baru' => format_tgl_indo($p['tgl_jatuh_tempo_baru']),
        'nominal_biaya' => format_rupiah_privacy($p['nominal_biaya']),
        'foto_bukti' => $p['foto_bukti'],
        'catatan' => $p['catatan'] ?: '-'
    ];
}

// Calculate Grand Total Pajak/KIR
$grand_total_pajak = 0;
foreach ($payments as $pay) {
    $grand_total_pajak += (float)$pay['nominal_biaya'];
}

// Handle Submit Payment Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_payment') {
    if (!can_edit_data()) {
        echo "<script>alert('Akses Ditolak: Hanya Admin yang dapat mencatat pembayaran pajak.'); window.location='pembayaran_pajak_kir.php';</script>";
        exit;
    }
    $id_kendaraan = (int)($_POST['id_kendaraan'] ?? 0);
    $jenis_pembayaran = $_POST['jenis_pembayaran'] ?? 'Pajak STNK';
    $tgl_bayar = $_POST['tgl_bayar'] ?? date('Y-m-d');
    $tgl_jatuh_tempo_baru = $_POST['tgl_jatuh_tempo_baru'] ?? date('Y-m-d');
    $tgl_plat_baru = $_POST['tgl_plat_baru'] ?? null;
    $nominal_biaya = (float)($_POST['nominal_biaya'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');

    $foto_bukti = null;
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/pajak/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION);
        $foto_bukti = 'pajak_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
        move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_dir . $foto_bukti);
    }

    $sql_ins = "INSERT INTO pembayaran_pajak_kir (id_kendaraan, jenis_pembayaran, tgl_bayar, tgl_jatuh_tempo_baru, nominal_biaya, foto_bukti, catatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_ins = $pdo->prepare($sql_ins);
    $stmt_ins->execute([$id_kendaraan, $jenis_pembayaran, $tgl_bayar, $tgl_jatuh_tempo_baru, $nominal_biaya, $foto_bukti, $catatan]);

    $stmt_fetch = $pdo->prepare("SELECT * FROM kendaraan_alat WHERE id = ?");
    $stmt_fetch->execute([$id_kendaraan]);
    $unit = $stmt_fetch->fetch();

    $kode_plat_baru = strtoupper(trim($_POST['kode_plat_baru'] ?? ''));

    if ($unit) {
        // Record plate change if new Nopol supplied and different
        if (!empty($kode_plat_baru) && strcasecmp($unit['kode_plat'], $kode_plat_baru) !== 0) {
            catat_perubahan_plat($pdo, $id_kendaraan, $unit['kode_plat'], $kode_plat_baru, 'Pergantian Nopol 5-Tahunan pada Pembayaran Pajak STNK');
            $stmt_upd_nopol = $pdo->prepare("UPDATE kendaraan_alat SET kode_plat = ? WHERE id = ?");
            $stmt_upd_nopol->execute([$kode_plat_baru, $id_kendaraan]);
            $unit['kode_plat'] = $kode_plat_baru;
        }

        if ($jenis_pembayaran === 'Pajak STNK') {
            $bulan_new = (int)date('n', strtotime($tgl_jatuh_tempo_baru));
            if (!empty($tgl_plat_baru)) {
                $stmt_upd = $pdo->prepare("UPDATE kendaraan_alat SET tgl_jatuh_tempo_pajak = ?, bulan_pajak = ?, tgl_jatuh_tempo_plat = ? WHERE id = ?");
                $stmt_upd->execute([$tgl_jatuh_tempo_baru, $bulan_new, $tgl_plat_baru, $id_kendaraan]);
            } else {
                $stmt_upd = $pdo->prepare("UPDATE kendaraan_alat SET tgl_jatuh_tempo_pajak = ?, bulan_pajak = ? WHERE id = ?");
                $stmt_upd->execute([$tgl_jatuh_tempo_baru, $bulan_new, $id_kendaraan]);
            }
        } else {
            $stmt_upd = $pdo->prepare("UPDATE kendaraan_alat SET tgl_jatuh_tempo_kir = ? WHERE id = ?");
            $stmt_upd->execute([$tgl_jatuh_tempo_baru, $id_kendaraan]);
        }

        $setting_wa = get_whatsapp_setting($pdo);
        $tgl_new_fmt = format_tgl_indo($tgl_jatuh_tempo_baru);
        $wa_msg = "*✅ UPDATE PEMBAYARAN {$jenis_pembayaran} BERHASIL - ALISA*\n";
        $wa_msg .= "Armada: *{$unit['nama']} ({$unit['kode_plat']})*\n";
        $wa_msg .= "Nominal Biaya: " . format_rupiah($nominal_biaya) . "\n";
        $wa_msg .= "Jatuh Tempo Baru: *{$tgl_new_fmt}*\n";
        $wa_msg .= "_Pembayaran telah dicatat oleh Bendahara / Admin ALISA._";

        send_whatsapp_msg($setting_wa['api_token'], $setting_wa['target_phone'], $wa_msg);
    }

    echo "<script>alert('Pembayaran " . $jenis_pembayaran . " berhasil dicatat!'); window.location='pembayaran_pajak_kir.php';</script>";
    exit;
}

$months_list = [
    1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
    5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
    9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
];
?>

<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="data_unit.php" class="text-info text-decoration-none"><i class="fa-solid fa-truck-monster me-1"></i> Data Unit</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page">Submenu Pajak</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-file-invoice-dollar text-info me-2"></i> Pembayaran Pajak STNK</h4>
            <p class="text-white-50 small mb-0">Kelola jadwal & pembayaran pajak bulanan serta jatuh tempo plat nomor 5-tahunan.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="data_unit.php" class="btn btn-outline-secondary text-white rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Data Unit
            </a>
            <?php if (can_edit_data()): ?>
                <button type="button" class="btn btn-bpj-primary shadow" data-bs-toggle="modal" data-bs-target="#modalPembayaran">
                    <i class="fa-solid fa-plus me-2"></i> Catat Pembayaran Pajak
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Custom CSS for Matrix Detail Buttons -->
    <style>
    .btn-detail-pajak {
        transition: all 0.2s ease-in-out;
        border: 1px solid rgba(56, 189, 248, 0.6) !important;
    }
    .btn-detail-pajak:hover {
        transform: scale(1.1);
        background-color: #0284c7 !important;
        color: #ffffff !important;
        box-shadow: 0 0 10px rgba(2, 132, 199, 0.75) !important;
    }
    </style>

    <!-- Data 3 Pajak Matrix Card -->
    <div class="card-custom p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <h6 class="fw-bold text-white mb-0"><i class="fa-solid fa-calendar-check text-info me-2"></i> Matriks Jadwal Pajak Bulanan & Plat Nomor 5-Tahunan (Data 3)</h6>
            <div class="nav nav-pills gap-1">
                <a href="pembayaran_pajak_kir.php?cat=semua" class="btn btn-sm <?= ($cat_pajak_filter === 'semua') ? 'btn-primary' : 'btn-outline-secondary text-white' ?> rounded-pill px-3">Semua</a>
                <a href="pembayaran_pajak_kir.php?cat=roda6" class="btn btn-sm <?= ($cat_pajak_filter === 'roda6') ? 'btn-primary' : 'btn-outline-secondary text-white' ?> rounded-pill px-3">Roda 6</a>
                <a href="pembayaran_pajak_kir.php?cat=roda4" class="btn btn-sm <?= ($cat_pajak_filter === 'roda4') ? 'btn-primary' : 'btn-outline-secondary text-white' ?> rounded-pill px-3">Roda 4</a>
                <a href="pembayaran_pajak_kir.php?cat=roda3" class="btn btn-sm <?= ($cat_pajak_filter === 'roda3') ? 'btn-primary' : 'btn-outline-secondary text-white' ?> rounded-pill px-3">Roda 3 (Viar)</a>
                <a href="pembayaran_pajak_kir.php?cat=roda2" class="btn btn-sm <?= ($cat_pajak_filter === 'roda2') ? 'btn-primary' : 'btn-outline-secondary text-white' ?> rounded-pill px-3">Roda 2</a>
            </div>
        </div>

        <div class="table-responsive" style="max-height: 650px;">
            <table class="table table-bordered table-hover align-middle mb-0 border-secondary">
                <thead class="bg-dark text-center align-middle sticky-top">
                    <tr>
                        <th style="width: 40px;" class="bg-dark text-white">NO</th>
                        <th style="min-width: 180px;" class="bg-dark text-white text-start">JENIS KENDARAAN</th>
                        <th style="min-width: 120px;" class="bg-dark text-info">NOPOL</th>
                        <?php foreach ($months_list as $m_num => $m_name): ?>
                            <th style="min-width: 105px;" class="bg-dark text-white font-monospace small p-1"><?= substr($m_name, 0, 3) ?></th>
                        <?php endforeach; ?>
                        <th style="min-width: 130px;" class="bg-dark text-warning">PLAT NOMOR (5 TH)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($matrix_pajak_units)): ?>
                        <tr><td colspan="16" class="text-center text-muted py-4">Belum ada armada untuk kategori ini.</td></tr>
                    <?php else: ?>
                        <?php 
                        $today_ts = strtotime(date('Y-m-d'));
                        $h30_ts = strtotime('+30 days');
                        
                        foreach ($matrix_pajak_units as $idx => $u): 
                            $b_pajak = (int)($u['bulan_pajak'] ?: (empty($u['tgl_jatuh_tempo_pajak']) ? 0 : date('n', strtotime($u['tgl_jatuh_tempo_pajak']))));
                            
                            // Calculate +10% predicted tax for next year
                            $nominal_terakhir = $latest_pajak_map[(int)$u['id']] ?? 0;
                            if ($nominal_terakhir <= 0) {
                                if ($u['jenis'] === 'kendaraan_roda_6') $nominal_terakhir = 2500000;
                                elseif ($u['jenis'] === 'kendaraan_roda_4') $nominal_terakhir = 1500000;
                                elseif ($u['jenis'] === 'kendaraan_roda_3') $nominal_terakhir = 350000;
                                else $nominal_terakhir = 250000;
                            }
                            $prediksi_pajak_10pct = $nominal_terakhir * 1.10;

                            // Determine Dynamic Date Badge Color for CURRENT YEAR ($current_year)
                            $tgl_pajak_str = $u['tgl_jatuh_tempo_pajak'];
                            $is_paid_current_year = !empty($paid_current_year_map[(int)$u['id']]);

                            if ($is_paid_current_year) {
                                // 🟢 HIJAU: Sudah dibayar pada tahun ini ($current_year)
                                $badge_bg_class = 'bg-success text-white';
                                $status_label = 'Sudah Dibayar di Tahun ' . $current_year;
                            } else {
                                // Belum dibayar pada tahun ini ($current_year)
                                $m_pajak = !empty($tgl_pajak_str) && $tgl_pajak_str !== '0000-00-00' ? date('m', strtotime($tgl_pajak_str)) : sprintf('%02d', $b_pajak);
                                $d_pajak = !empty($tgl_pajak_str) && $tgl_pajak_str !== '0000-00-00' ? date('d', strtotime($tgl_pajak_str)) : '15';
                                $due_date_curr_year = sprintf('%04d-%02d-%02d', $current_year, $m_pajak, $d_pajak);
                                $due_ts = strtotime($due_date_curr_year);

                                if ($due_ts >= $today_ts && $due_ts <= $h30_ts) {
                                    // 🟡 KUNING: Jatuh tempo dalam 30 hari di tahun ini (misal September saat ini)
                                    $badge_bg_class = 'bg-warning text-dark fw-bold';
                                    $status_label = 'Mendekati Jatuh Tempo (H-30 Hari)';
                                } else {
                                    // 🔴 MERAH: Belum dibayar di tahun ini ($current_year) -> Termasuk Oktober, November, Desember jika belum bayar di tahun ini
                                    $badge_bg_class = 'bg-danger text-white fw-bold';
                                    $status_label = 'Belum Dibayar di Tahun ' . $current_year;
                                }
                            }

                            $unit_payload = [
                                'id' => (int)$u['id'],
                                'nama' => $u['nama'],
                                'kode_plat' => $u['kode_plat'],
                                'kategori' => $u['kategori'],
                                'lokasi_ruas' => $u['lokasi_ruas'],
                                'tgl_pajak' => format_tgl_indo($u['tgl_jatuh_tempo_pajak']),
                                'tgl_plat' => format_tgl_indo($u['tgl_jatuh_tempo_plat']),
                                'nominal_terakhir' => format_rupiah_privacy($nominal_terakhir),
                                'prediksi_pajak' => format_rupiah_privacy($prediksi_pajak_10pct)
                            ];
                        ?>
                            <tr>
                                <td class="text-center fw-bold text-white-50"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-white text-start">
                                    <?= htmlspecialchars($u['nama']) ?>
                                    <div class="text-white-50 small"><?= htmlspecialchars($u['kategori']) ?></div>
                                </td>
                                <td class="fw-bold text-info font-monospace text-nowrap text-center"><?= htmlspecialchars($u['kode_plat']) ?></td>

                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td class="text-center p-1.5 text-nowrap">
                                        <?php if ($m === $b_pajak && !empty($u['tgl_jatuh_tempo_pajak']) && $u['tgl_jatuh_tempo_pajak'] !== '0000-00-00'): ?>
                                            <span class="badge <?= $badge_bg_class ?> px-2.5 py-1.5 font-monospace shadow-sm tax-date-badge-interactive" style="font-size: 0.78rem;" title="Klik untuk Rincian Pajak & Prediksi Tahun Depan" onclick='openDetailPajakModal(<?= json_encode($unit_payload, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <?= date('d/m', strtotime($u['tgl_jatuh_tempo_pajak'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-white-50">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>

                                <td class="text-center fw-bold text-warning font-monospace text-nowrap">
                                    <?php if (!empty($u['tgl_jatuh_tempo_plat']) && $u['tgl_jatuh_tempo_plat'] !== '0000-00-00'): ?>
                                        <span class="badge bg-warning text-dark px-2.5 py-1" style="font-size: 0.8rem;">
                                            <?= date('d/m/Y', strtotime($u['tgl_jatuh_tempo_plat'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- History Log Table with Vehicle Filter Dropdown & Hapus Action -->
    <div class="card-custom p-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-3">
            <h6 class="fw-bold text-white m-0"><i class="fa-solid fa-clock-rotate-left text-info me-2"></i> Riwayat Pembayaran Pajak</h6>
            
            <form method="GET" action="" class="d-flex align-items-center gap-2" style="min-width: 320px;">
                <label class="form-label mb-0 fw-bold small text-nowrap text-white-50"><i class="fa-solid fa-filter me-1"></i> Filter Unit:</label>
                <select name="id_kendaraan" id="selectFilterPajakUnit" class="form-select form-select-sm border-secondary text-white bg-dark" onchange="this.form.submit()">
                    <option value="0">-- Semua Unit Kendaraan --</option>
                    <?php foreach ($all_units_list as $un): ?>
                        <option value="<?= $un['id'] ?>" <?= ($id_kendaraan_filter === (int)$un['id']) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($un['kode_plat']) ?>] <?= htmlspecialchars($un['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($id_kendaraan_filter > 0): ?>
                    <a href="pembayaran_pajak_kir.php" class="btn btn-sm btn-outline-secondary rounded-circle text-white" title="Reset Filter"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-nowrap" style="width: 12%;">Tgl Bayar</th>
                        <th class="text-nowrap" style="width: 22%;">Nopol / Unit</th>
                        <th class="text-nowrap" style="width: 15%;">Jenis Pembayaran</th>
                        <th class="text-nowrap" style="width: 16%;">Jatuh Tempo Baru</th>
                        <th class="text-nowrap" style="width: 15%;">Nominal Biaya</th>
                        <th class="text-nowrap" style="width: 10%;">Bukti Bayar</th>
                        <th class="text-nowrap text-end" style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat pembayaran Pajak tercatat untuk unit yang dipilih.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td class="fw-semibold text-nowrap text-white"><?= format_tgl_indo($pay['tgl_bayar']) ?></td>
                                <td>
                                    <div class="fw-bold text-white font-monospace"><?= htmlspecialchars($pay['kode_plat']) ?></div>
                                    <div class="text-white-50 small"><?= htmlspecialchars($pay['nama']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-info text-white"><?= htmlspecialchars($pay['jenis_pembayaran']) ?></span>
                                </td>
                                <td class="fw-bold text-info text-nowrap"><?= format_tgl_indo($pay['tgl_jatuh_tempo_baru']) ?></td>
                                <td class="fw-bold text-info text-nowrap"><?= format_rupiah_privacy($pay['nominal_biaya']) ?></td>
                                <td class="text-nowrap">
                                    <?php if (!empty($pay['foto_bukti'])): ?>
                                        <a href="uploads/pajak/<?= htmlspecialchars($pay['foto_bukti']) ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-pill">
                                            <i class="fa-solid fa-image me-1"></i> Bukti
                                        </a>
                                    <?php else: ?>
                                        <span class="text-white-50 small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if (can_edit_data()): ?>
                                        <a href="pembayaran_pajak_kir.php?action=delete&id=<?= $pay['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan riwayat pembayaran ini?')" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="Hapus Riwayat Pembayaran Ini">
                                            <i class="fa-solid fa-trash me-1"></i> Hapus
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-extrabold text-white py-3">
                            <i class="fa-solid fa-calculator text-info me-2"></i> REKAP TOTAL PEMBAYARAN PAJAK (SUBTOTAL UNIT):
                        </td>
                        <td class="fw-extrabold fs-5 text-info py-3" colspan="3">
                            <?= format_rupiah_privacy($grand_total_pajak) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal Catat Pembayaran -->
<div class="modal fade" id="modalPembayaran" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-secondary bg-dark text-white">
            <div class="modal-header border-secondary" style="background-color: #0284c7 !important;">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-file-invoice-dollar text-white me-2"></i> Form Pembayaran Pajak</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_payment">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-info"><i class="fa-solid fa-search me-1"></i> Pilih Unit Armada (Ketik Nopol / Nama) <span class="text-danger">*</span></label>
                            <select name="id_kendaraan" id="pay_id_kendaraan" class="form-select border-secondary text-white bg-dark" required>
                                <option value="">-- Ketik / Pilih Unit --</option>
                                <?php foreach ($all_units_list as $u): ?>
                                    <option value="<?= $u['id'] ?>">[<?= htmlspecialchars($u['kode_plat']) ?>] <?= htmlspecialchars($u['nama']) ?> &bull; <?= htmlspecialchars($u['lokasi_ruas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-white">Jenis Perpanjangan <span class="text-danger">*</span></label>
                            <select name="jenis_pembayaran" class="form-select border-secondary text-white bg-dark" required>
                                <option value="Pajak STNK">Pajak STNK Tahunan</option>
                                <option value="Uji KIR">Uji KIR Berkala</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-white">Tanggal Bayar <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_bayar" class="form-control border-secondary text-white bg-dark" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-info">Jatuh Tempo Baru Setelah Dibayar <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_jatuh_tempo_baru" class="form-control border-secondary text-white bg-dark" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-warning">Jatuh Tempo Plat 5-Tahunan Baru (Opsional)</label>
                            <input type="date" name="tgl_plat_baru" class="form-control border-secondary text-white bg-dark" placeholder="Jika ganti kaleng 5 tahunan">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-warning"><i class="fa-solid fa-clock-rotate-left me-1"></i> Nopol Baru / Ganti Plat (Opsional)</label>
                            <input type="text" name="kode_plat_baru" class="form-control font-monospace border-secondary text-white bg-dark" placeholder="Contoh: H 8030 YZ (Jika Nopol berubah)">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-info">Nominal Biaya (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="nominal_biaya" id="input_nominal_biaya" class="form-control font-monospace fs-5 text-info fw-bold border-secondary bg-dark" placeholder="Contoh: 2500000" required oninput="calcPajakPrediksi(this.value)">
                            <div id="box_prediksi_pajak" class="mt-1 text-warning small fw-bold d-none">
                                <i class="fa-solid fa-chart-line me-1 text-info"></i> Prediksi Pajak Tahun Depan (+10%): <span id="text_prediksi_pajak" class="font-monospace text-info fs-6 fw-extrabold">Rp 0</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-white">Foto Bukti Resi / STNK / Bukti KIR</label>
                            <input type="file" name="foto_bukti" class="form-control border-secondary text-white bg-dark" accept="image/*,.pdf">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-white">Catatan Pembayaran</label>
                            <input type="text" name="catatan" class="form-control border-secondary text-white bg-dark" placeholder="Catatan pembayaran oleh Bendahara / Admin...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-bpj-primary px-4 shadow">
                        <i class="fa-solid fa-check me-2"></i> Simpan Pembayaran & Kirim WA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Pajak Unit & Prediksi Tahun Depan -->
<div class="modal fade" id="modalDetailPajakUnit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-secondary" style="background-color: #0284c7 !important;">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fa-solid fa-receipt me-2"></i> Rincian Pajak & Prediksi Tahun Depan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Unit Header Summary Box -->
                <div class="row g-3 mb-4 p-3 rounded-3 border border-secondary modal-info-box">
                    <div class="col-md-6">
                        <span class="text-white-50 small d-block">Armada Kendaraan / Peralatan:</span>
                        <h5 class="fw-extrabold text-white mb-1" id="m_pajak_nama">-</h5>
                        <span class="fw-bold text-info font-monospace fs-5" id="m_pajak_nopol">-</span>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-white-50 small d-block">Masa Berlaku Pajak STNK Saat Ini:</span>
                        <h6 class="fw-bold text-warning mb-1" id="m_pajak_tgl_stnk">-</h6>
                        <span class="badge bg-secondary font-monospace" id="m_pajak_tgl_plat">Plat: -</span>
                    </div>
                </div>

                <!-- Highlight Card Prediksi Tahun Depan (+10%) -->
                <div class="p-3 rounded-3 border border-info modal-summary-box mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fw-bold text-white small d-block mb-1">
                                <i class="fa-solid fa-arrow-trend-up text-info me-1 fs-6"></i> PREDIKSI HARGA PAJAK TAHUN DEPAN (+10%):
                            </span>
                            <div class="text-white-50 small">
                                Berdasarkan pembayaran pajak terakhir (<span id="m_pajak_nominal_terakhir" class="fw-bold text-white font-monospace">Rp 0</span>) + 10% inflasi/estimasi resmi
                            </div>
                        </div>
                        <h3 class="fw-extrabold text-info font-monospace m-0" id="m_pajak_prediksi_val">Rp 0</h3>
                    </div>
                </div>

                <!-- Table Riwayat Pembayaran Yang Sudah Dibayar -->
                <h6 class="fw-bold text-white mb-2">
                    <i class="fa-solid fa-clock-rotate-left text-warning me-2"></i> Riwayat Pembayaran Pajak Yang Sudah Dibayar:
                </h6>
                <div class="table-responsive rounded-3 border border-secondary mb-3">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-dark text-nowrap">
                            <tr>
                                <th>Tgl Bayar</th>
                                <th>Jenis Pembayaran</th>
                                <th>Jatuh Tempo Baru</th>
                                <th>Nominal Dibayar</th>
                                <th>Bukti Resi</th>
                            </tr>
                        </thead>
                        <tbody id="m_pajak_history_body">
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat pembayaran tercatat.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
const allUnitPayments = <?= json_encode($payments_by_unit) ?>;

function openDetailPajakModal(data) {
    document.getElementById('m_pajak_nama').innerText = data.nama + ' (' + data.kategori + ')';
    document.getElementById('m_pajak_nopol').innerText = data.kode_plat;
    document.getElementById('m_pajak_tgl_stnk').innerText = data.tgl_pajak;
    document.getElementById('m_pajak_tgl_plat').innerText = 'Plat 5 Th: ' + data.tgl_plat;
    document.getElementById('m_pajak_nominal_terakhir').innerText = data.nominal_terakhir;
    document.getElementById('m_pajak_prediksi_val').innerText = data.prediksi_pajak;

    let tbody = document.getElementById('m_pajak_history_body');
    let history = allUnitPayments[data.id] || [];
    
    if (history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat pembayaran pajak yang di-input untuk unit ini.</td></tr>';
    } else {
        let html = '';
        history.forEach(function(h) {
            let resiBtn = h.foto_bukti ? `<a href="uploads/pajak/${h.foto_bukti}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-2.5"><i class="fa-solid fa-image me-1"></i> Bukti</a>` : '-';
            html += `<tr>
                <td class="fw-bold text-white text-nowrap">${h.tgl_bayar}</td>
                <td><span class="badge bg-warning text-dark fw-bold">${h.jenis_pembayaran}</span></td>
                <td class="text-info font-monospace text-nowrap">${h.tgl_jatuh_tempo_baru}</td>
                <td class="fw-bold text-warning text-nowrap">${h.nominal_biaya}</td>
                <td>${resiBtn}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    var modal = new bootstrap.Modal(document.getElementById('modalDetailPajakUnit'));
    modal.show();
}

function calcPajakPrediksi(val) {
    let num = parseFloat(val);
    let box = document.getElementById('box_prediksi_pajak');
    let text = document.getElementById('text_prediksi_pajak');
    if (!isNaN(num) && num > 0) {
        let prediksi = num * 1.10;
        let formatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(prediksi);
        text.innerText = formatted;
        box.classList.remove('d-none');
    } else {
        box.classList.add('d-none');
    }
}

// Enable Searchable Select2 Dropdown for Modal Form & Table Filter
$(document).ready(function() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('#pay_id_kendaraan').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalPembayaran'),
            placeholder: '-- Ketik Nopol / Nama Unit Armada --',
            allowClear: true,
            width: '100%'
        });

        $('#selectFilterPajakUnit').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Semua Unit Kendaraan --',
            width: '100%'
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
