<?php
/**
 * ALISA - Beranda Executive Dashboard
 * Balai Pengelolaan Jalan Wilayah Tegal (POLITEKNIK PURBAYA)
 */

require_once __DIR__ . '/includes/header.php';

// 1. Fetch Summary Stats for Top 4 Cards
// Total Kendaraan (non-peralatan)
$stmt_knd = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE jenis != 'peralatan'");
$total_kendaraan = (int)$stmt_knd->fetchColumn();

// Total Peralatan (peralatan)
$stmt_alat = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE jenis = 'peralatan'");
$total_peralatan = (int)$stmt_alat->fetchColumn();

// Servis Bulan Ini (transaksi riwayat_servis bulan berjalan)
$stmt_m_servis = $pdo->query("SELECT COUNT(*) FROM riwayat_servis WHERE MONTH(tgl_servis) = MONTH(CURRENT_DATE()) AND YEAR(tgl_servis) = YEAR(CURRENT_DATE())");
$servis_bulan_ini = (int)$stmt_m_servis->fetchColumn();

// Menunggu Servis (unit status Mendekati Servis atau tgl_servis_berikutnya <= CURRENT_DATE())
$stmt_wait_servis = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE status = 'Mendekati Servis' OR (tgl_servis_berikutnya IS NOT NULL AND tgl_servis_berikutnya != '0000-00-00' AND tgl_servis_berikutnya <= CURRENT_DATE())");
$menunggu_servis = (int)$stmt_wait_servis->fetchColumn();

// 2. Fetch Recent Servis Status Table Data
$stmt_recent = $pdo->query("
    SELECT r.*, k.nama, k.kode_plat, k.kategori, k.jenis, k.status as status_unit
    FROM riwayat_servis r
    JOIN kendaraan_alat k ON r.id_kendaraan = k.id
    ORDER BY r.tgl_servis DESC, r.id DESC
    LIMIT 5
");
$recent_status_servis = $stmt_recent->fetchAll();

// If riwayat_servis has fewer items, fallback to latest unit schedules to populate table
if (count($recent_status_servis) < 4) {
    $stmt_fallback_units = $pdo->query("SELECT * FROM kendaraan_alat ORDER BY tgl_servis_berikutnya ASC LIMIT 5");
    $fallback_units = $stmt_fallback_units->fetchAll();
}

// 3. Fetch Recent Notifications
$recent_notifikasi = [];
$all_units_notif = $pdo->query("SELECT * FROM kendaraan_alat WHERE kondisi != 'RB' ORDER BY tgl_servis_berikutnya ASC")->fetchAll();

foreach ($all_units_notif as $u) {
    $inf_s = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
    $inf_p = get_alisa_schedule_info($u['tgl_jatuh_tempo_pajak'], 'Pajak STNK', $u['kondisi'], $u['jenis']);
    $inf_k = get_alisa_schedule_info($u['tgl_jatuh_tempo_kir'], 'Uji KIR', $u['kondisi'], $u['jenis']);

    if ($inf_s['is_h30']) {
        $recent_notifikasi[] = [
            'text' => "Jadwal servis " . htmlspecialchars($u['kode_plat']) . " pada " . format_tgl_indo($u['tgl_servis_berikutnya']),
            'time' => $inf_s['label'],
            'icon' => 'fa-solid fa-bell text-warning',
            'bg'   => 'rgba(245, 158, 11, 0.15)'
        ];
    }
    if ($inf_p['is_h30']) {
        $recent_notifikasi[] = [
            'text' => "Pembayaran pajak " . htmlspecialchars($u['kode_plat']) . " jatuh tempo " . format_tgl_indo($u['tgl_jatuh_tempo_pajak']),
            'time' => $inf_p['label'],
            'icon' => 'fa-solid fa-file-invoice-dollar text-danger',
            'bg'   => 'rgba(239, 68, 68, 0.15)'
        ];
    }
    if ($inf_k['is_h30']) {
        $recent_notifikasi[] = [
            'text' => "Uji KIR " . htmlspecialchars($u['kode_plat']) . " jatuh tempo " . format_tgl_indo($u['tgl_jatuh_tempo_kir']),
            'time' => $inf_k['label'],
            'icon' => 'fa-solid fa-clipboard-check text-info',
            'bg'   => 'rgba(56, 189, 248, 0.15)'
        ];
    }
}
$recent_notifikasi = array_slice($recent_notifikasi, 0, 5);

// 4. Monthly Service Chart Data (Penjadwalan vs Darurat)
$current_year = date('Y');
$chart_penjadwalan = array_fill(1, 12, 0);
$chart_darurat     = array_fill(1, 12, 0);

$stmt_chart = $pdo->prepare("
    SELECT MONTH(tgl_servis) as bln, jenis_servis, COUNT(*) as jml
    FROM riwayat_servis
    WHERE YEAR(tgl_servis) = ?
    GROUP BY MONTH(tgl_servis), jenis_servis
");
$stmt_chart->execute([$current_year]);
$chart_rows = $stmt_chart->fetchAll();

foreach ($chart_rows as $c) {
    $m = (int)$c['bln'];
    if (strcasecmp($c['jenis_servis'], 'Darurat') === 0) {
        $chart_darurat[$m] = (int)$c['jml'];
    } else {
        $chart_penjadwalan[$m] += (int)$c['jml'];
    }
}

$data_penjadwalan_js = json_encode(array_values($chart_penjadwalan));
$data_darurat_js     = json_encode(array_values($chart_darurat));
?>

<!-- Custom CSS for Mock-up Exact Stat Cards -->
<style>
.dashboard-stat-card {
    border-radius: 16px !important;
    padding: 1.25rem 1.5rem !important;
    position: relative !important;
    overflow: hidden !important;
    border-top: none !important;
    border-right: none !important;
    border-bottom: none !important;
    outline: none !important;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08) !important;
    cursor: default !important;
}

.stat-card-blue {
    background: linear-gradient(135deg, rgba(2, 132, 199, 0.14) 0%, rgba(2, 132, 199, 0.04) 100%) !important;
    border-left: 5px solid #0284c7 !important;
    border-top: none !important;
    border-right: none !important;
    border-bottom: none !important;
}
.stat-card-green {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.14) 0%, rgba(16, 185, 129, 0.04) 100%) !important;
    border-left: 5px solid #10b981 !important;
    border-top: none !important;
    border-right: none !important;
    border-bottom: none !important;
}
.stat-card-yellow {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.14) 0%, rgba(245, 158, 11, 0.04) 100%) !important;
    border-left: 5px solid #f59e0b !important;
    border-top: none !important;
    border-right: none !important;
    border-bottom: none !important;
}
.stat-card-purple {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.14) 0%, rgba(139, 92, 246, 0.04) 100%) !important;
    border-left: 5px solid #8b5cf6 !important;
    border-top: none !important;
    border-right: none !important;
    border-bottom: none !important;
}

.stat-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.stat-value {
    font-size: 2.2rem;
    font-weight: 800;
    line-height: 1;
}
.stat-unit {
    font-size: 0.85rem;
    font-weight: 500;
    margin-top: 0.25rem;
    color: #94a3b8;
}
.stat-icon {
    position: absolute;
    right: 1.25rem;
    bottom: 1rem;
    font-size: 2.5rem;
    opacity: 0.85;
}
.status-badge-selesai {
    background-color: rgba(16, 185, 129, 0.18);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.status-badge-proses {
    background-color: rgba(245, 158, 11, 0.18);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.status-badge-menunggu {
    background-color: rgba(239, 68, 68, 0.18);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
</style>

<div class="container-fluid p-0">
    <!-- Welcome Header Card -->
    <div class="card-custom welcome-header-card p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3);">
                    <i class="fa-solid fa-gauge-high fs-2 text-info"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-white mb-1">Selamat datang, <?= htmlspecialchars($user_name) ?></h4>
                    <p class="text-white-50 mb-0 small">Sistem Pemeliharaan Rutin Kendaraan dan Peralatan (BPJ Wilayah Tegal)</p>
                </div>
            </div>

            <!-- Action Button -->
            <?php if ($current_role === 'admin'): ?>
                <a href="servis_form.php" class="btn btn-bpj-primary rounded-pill px-4 py-2 shadow d-flex align-items-center gap-2">
                    <i class="fa-solid fa-wrench me-1"></i> Melakukan Servis
                </a>
            <?php elseif ($current_role === 'teknisi'): ?>
                <a href="servis_kelola.php" class="btn btn-warning rounded-pill px-4 py-2 shadow d-flex align-items-center gap-2 text-dark fw-bold" style="background-color: #eab308 !important;">
                    <i class="fa-solid fa-upload me-1"></i> Upload Nota Ke Admin
                </a>
            <?php elseif ($current_role === 'pimpinan'): ?>
                <a href="laporan.php" class="btn btn-bpj-primary rounded-pill px-4 py-2 shadow d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-contract me-1"></i> Lihat Laporan
                </a>
            <?php elseif ($current_role === 'bendahara'): ?>
                <a href="pembayaran_pajak_kir.php" class="btn btn-bpj-primary rounded-pill px-4 py-2 shadow d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i> Pembayaran Pajak
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Banner: Pending Nota Teknisi Uploads -->
    <?php 
    $cnt_nota_pending_dash = 0;
    try {
        $stmt_cnd = $pdo->query("SELECT COUNT(*) FROM nota_teknisi WHERE status = 'Menunggu Verifikasi'");
        if ($stmt_cnd) $cnt_nota_pending_dash = (int)$stmt_cnd->fetchColumn();
    } catch (Exception $e) {}
    ?>
    <?php if ($cnt_nota_pending_dash > 0): ?>
        <div class="alert alert-warning border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: linear-gradient(135deg, rgba(234, 179, 8, 0.25) 0%, rgba(245, 158, 11, 0.15) 100%); border-left: 5px solid #eab308 !important;">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-file-invoice text-warning fs-3"></i>
                <div>
                    <strong class="text-white d-block fs-6"><i class="fa-solid fa-bell text-warning me-1"></i> Perhatian: Terdapat <?= $cnt_nota_pending_dash ?> Nota Upload Teknisi Menunggu Verifikasi!</strong>
                    <span class="text-white-50 small">Teknisi telah mengunggah kwitansi/foto nota servis baru yang memerlukan peninjauan Admin.</span>
                </div>
            </div>
            <a href="servis_kelola.php" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm" style="background-color: #eab308 !important;">
                Verifikasi Nota <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>

    <!-- Preserved WhatsApp Broadcast Gateway Banner -->
    <?php if ($current_role === 'admin'): ?>
        <div class="card-custom mb-4 border-0 shadow-sm overflow-hidden wa-broadcast-banner">
            <div class="p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center wa-icon-circle" style="width: 54px; height: 54px;">
                        <i class="fa-brands fa-whatsapp fs-2 text-white"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 wa-banner-title">Gateway Center WhatsApp BPJ Tegal</h5>
                        <p class="mb-0 small wa-banner-sub">Pusat notifikasi pengingat otomatis H-30 (Mingguan) & H-7 (Harian) Servis & Pajak STNK.</p>
                    </div>
                </div>
                <a href="whatsapp_setting.php" class="btn btn-wa-broadcast rounded-pill px-4 py-2 shadow d-flex align-items-center gap-2">
                    <i class="fa-brands fa-whatsapp fs-5"></i> Kelola Notifikasi WA
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4 Top Stat Cards Row (Static Information Boards Only) -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Kendaraan -->
        <div class="col-xl-3 col-md-6">
            <div class="card-custom dashboard-stat-card stat-card-blue">
                <div class="stat-title text-info">Total Kendaraan</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value text-info"><?= $total_kendaraan ?></span>
                </div>
                <div class="stat-unit">Unit</div>
                <i class="fa-solid fa-truck-pickup stat-icon text-info"></i>
            </div>
        </div>

        <!-- Card 2: Total Peralatan -->
        <div class="col-xl-3 col-md-6">
            <div class="card-custom dashboard-stat-card stat-card-green">
                <div class="stat-title text-success">Total Peralatan</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value text-success"><?= $total_peralatan ?></span>
                </div>
                <div class="stat-unit">Unit</div>
                <i class="fa-solid fa-toolbox stat-icon text-success"></i>
            </div>
        </div>

        <!-- Card 3: Servis Bulan Ini -->
        <div class="col-xl-3 col-md-6">
            <div class="card-custom dashboard-stat-card stat-card-yellow">
                <div class="stat-title text-warning">Servis Bulan Ini</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value text-warning"><?= $servis_bulan_ini ?></span>
                </div>
                <div class="stat-unit">Unit</div>
                <i class="fa-solid fa-wrench stat-icon text-warning"></i>
            </div>
        </div>

        <!-- Card 4: Menunggu Servis -->
        <div class="col-xl-3 col-md-6">
            <div class="card-custom dashboard-stat-card stat-card-purple">
                <div class="stat-title" style="color: #a78bfa;">Menunggu Servis</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value" style="color: #a78bfa;"><?= $menunggu_servis ?></span>
                </div>
                <div class="stat-unit">Unit</div>
                <i class="fa-solid fa-clock stat-icon" style="color: #a78bfa;"></i>
            </div>
        </div>
    </div>

    <!-- Middle Section: Status Servis Terbaru & Notifikasi Terbaru (Side by Side) -->
    <div class="row g-4 mb-4">
        <!-- Left: Status Servis Terbaru -->
        <div class="col-lg-7">
            <div class="card-custom p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold m-0 text-white"><i class="fa-solid fa-clock-rotate-left text-info me-2"></i> Status Servis Terbaru</h5>
                </div>
                <div class="table-responsive flex-grow-1">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-white-50 small border-bottom border-secondary">
                                <th style="width: 45px;">No</th>
                                <th>Unit</th>
                                <th>Jenis</th>
                                <th>Status</th>
                                <th>Jadwal Servis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_status_servis)): ?>
                                <?php $no = 1; foreach ($recent_status_servis as $row): 
                                    $badge_cls = 'status-badge-selesai';
                                    $status_label = 'Selesai';
                                ?>
                                    <tr>
                                        <td class="text-white-50 small"><?= $no++ ?></td>
                                        <td class="fw-bold text-white"><?= htmlspecialchars($row['kode_plat']) ?></td>
                                        <td class="small text-white-50"><?= htmlspecialchars($row['kategori']) ?></td>
                                        <td><span class="badge <?= $badge_cls ?> rounded-pill px-3 py-1"><?= $status_label ?></span></td>
                                        <td class="small text-white font-monospace"><?= format_tgl_indo($row['tgl_servis']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif (!empty($fallback_units)): ?>
                                <?php $no = 1; foreach ($fallback_units as $u): 
                                    $inf = get_alisa_schedule_info($u['tgl_servis_berikutnya'], 'Servis', $u['kondisi'], $u['jenis']);
                                ?>
                                    <tr>
                                        <td class="text-white-50 small"><?= $no++ ?></td>
                                        <td class="fw-bold text-white"><?= htmlspecialchars($u['kode_plat']) ?></td>
                                        <td class="small text-white-50"><?= htmlspecialchars($u['kategori']) ?></td>
                                        <td><span class="badge <?= $inf['badge_class'] ?> rounded-pill px-3 py-1"><?= $inf['label'] ?></span></td>
                                        <td class="small text-white font-monospace"><?= format_tgl_indo($u['tgl_servis_berikutnya']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data status servis terbaru.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <a href="servis_kelola.php" class="text-info fw-bold text-decoration-none small">Lihat Semua <i class="fa-solid fa-chevron-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Right: Notifikasi Terbaru -->
        <div class="col-lg-5">
            <div class="card-custom p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold m-0 text-white"><i class="fa-solid fa-bell text-warning me-2"></i> Notifikasi Terbaru</h5>
                </div>
                <div class="flex-grow-1 d-flex flex-column gap-3">
                    <?php if (empty($recent_notifikasi)): ?>
                        <div class="text-center text-muted py-4 my-auto">
                            <i class="fa-regular fa-bell-slash fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada notifikasi pengingat terbaru.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_notifikasi as $notif): ?>
                            <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 w-100 overflow-hidden" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);">
                                <div class="d-flex align-items-center gap-2.5 flex-grow-1 me-2" style="min-width: 0;">
                                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: <?= $notif['bg'] ?>;">
                                        <i class="<?= $notif['icon'] ?> fs-6"></i>
                                    </div>
                                    <div class="small text-white text-truncate" style="min-width: 0;" title="<?= htmlspecialchars($notif['text']) ?>">
                                        <?= htmlspecialchars($notif['text']) ?>
                                    </div>
                                </div>
                                <span class="badge bg-dark border border-secondary text-white-50 flex-shrink-0 font-monospace small" style="white-space: nowrap; font-size: 0.75rem;"><?= $notif['time'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="text-end mt-3">
                    <a href="notifikasi.php" class="text-info fw-bold text-decoration-none small">Lihat Semua <i class="fa-solid fa-chevron-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Grafik Servis Per Bulan (Left) & Sebaran 13 Lokasi (Right) -->
    <div class="row g-4 mb-4">
        <!-- Left: Grafik Servis Per Bulan (Chart.js Bar Chart with Batang Biru & Batang Merah) -->
        <div class="col-lg-8">
            <div class="card-custom p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold m-0 text-white"><i class="fa-solid fa-chart-column text-info me-2"></i> Grafik Servis Per Bulan</h5>
                    <div class="d-flex align-items-center gap-3 small">
                        <span class="d-flex align-items-center gap-1 text-white-50"><span style="width: 12px; height: 12px; background: #0284c7; display: inline-block; border-radius: 3px;"></span> Penjadwalan</span>
                        <span class="d-flex align-items-center gap-1 text-white-50"><span style="width: 12px; height: 12px; background: #ef4444; display: inline-block; border-radius: 3px;"></span> Darurat</span>
                    </div>
                </div>
                <div style="height: 320px; position: relative;">
                    <canvas id="chartServisBulan"></canvas>
                </div>
            </div>
        </div>

        <!-- Right: Preserved Sebaran 13 Lokasi BPJ Tegal -->
        <div class="col-lg-4">
            <div class="card-custom p-4 h-100">
                <h6 class="fw-bold mb-3 text-white"><i class="fa-solid fa-map-location-dot text-info me-2"></i> Sebaran 13 Lokasi BPJ Tegal</h6>
                <div class="list-group list-group-flush small overflow-auto" style="max-height: 320px;">
                    <?php 
                    $ruas_list = get_lokasi_ruas_list();
                    $std_12 = array_slice($ruas_list, 0, 12);
                    foreach ($ruas_list as $ruas):
                        if ($ruas === '13. Tempat Lain (Input Custom)') {
                            $in_list = "'" . implode("','", array_map('addslashes', $std_12)) . "'";
                            $stmt_cnt = $pdo->query("SELECT COUNT(*) FROM kendaraan_alat WHERE lokasi_ruas NOT IN ($in_list) OR lokasi_ruas LIKE 'Tempat Lain%' OR lokasi_ruas = '13. Tempat Lain (Input Custom)'");
                            $cnt = $stmt_cnt->fetchColumn();
                        } else {
                            $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM kendaraan_alat WHERE lokasi_ruas = ?");
                            $stmt_cnt->execute([$ruas]);
                            $cnt = $stmt_cnt->fetchColumn();
                        }
                    ?>
                        <div class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-2 px-0 text-white border-bottom border-secondary" style="border-opacity: 0.2;">
                            <span class="text-white fw-semibold small text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($ruas) ?>"><i class="fa-solid fa-road text-info me-2"></i> <?= htmlspecialchars($ruas) ?></span>
                            <span class="badge bg-primary rounded-pill"><?= $cnt ?> Unit</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('chartServisBulan').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [
                {
                    label: 'Penjadwalan',
                    data: <?= $data_penjadwalan_js ?>,
                    backgroundColor: '#0284c7',
                    borderColor: '#0369a1',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.6
                },
                {
                    label: 'Darurat',
                    data: <?= $data_darurat_js ?>,
                    backgroundColor: '#ef4444',
                    borderColor: '#dc2626',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#f8fafc',
                    borderColor: '#334155',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { family: "'Plus Jakarta Sans', sans-serif" } }
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: 5,
                    grid: { color: 'rgba(255, 255, 255, 0.06)' },
                    ticks: {
                        color: '#94a3b8',
                        precision: 0,
                        font: { family: "'Plus Jakarta Sans', sans-serif" }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

